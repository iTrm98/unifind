<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\Feature\Concerns\BuildsUniFindData;
use Tests\TestCase;

class StudentPermissionsTest extends TestCase
{
    use BuildsUniFindData, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->buildBaseData();
    }

    public function test_student_is_blocked_from_every_admin_route(): void
    {
        $item = $this->makeItem($this->student);
        Sanctum::actingAs($this->student);

        $routes = [
            ['patchJson', "/api/admin/items/{$item->id}/status", ['status' => 'found']],
            ['deleteJson', "/api/admin/items/{$item->id}", ['reason' => 'test']],
            ['postJson', "/api/admin/items/{$item->id}/restore", []],
            ['getJson', '/api/admin/stats', []],
            ['getJson', '/api/admin/activity', []],
            ['getJson', '/api/admin/db', []],
            ['getJson', '/api/admin/db/users', []],
            ['postJson', '/api/admin/db/categories', ['name' => 'Hack']],
            ['putJson', "/api/admin/db/categories/{$this->category->id}", ['name' => 'Hack']],
            ['deleteJson', "/api/admin/db/categories/{$this->category->id}", []],
        ];

        foreach ($routes as [$method, $uri, $data]) {
            $response = $method === 'getJson' ? $this->getJson($uri) : $this->{$method}($uri, $data);
            $response->assertStatus(403)->assertJsonStructure(['message']);
        }

        // Nothing changed.
        $this->assertDatabaseHas('items', ['id' => $item->id, 'status' => 'open', 'deleted_at' => null]);
        $this->assertDatabaseMissing('categories', ['name' => 'Hack']);
    }

    public function test_student_catalog_only_shows_open_found_items_without_private_fields(): void
    {
        $visible = $this->makeItem($this->admin, ['type' => 'found', 'title' => 'Visible found']);
        $this->makeItem($this->admin, ['type' => 'found', 'status' => 'returned', 'title' => 'Returned found']);
        $this->makeItem($this->otherStudent, ['type' => 'lost', 'title' => 'Other lost']);
        $trashed = $this->makeItem($this->admin, ['type' => 'found', 'title' => 'Deleted found']);
        $trashed->delete();

        Sanctum::actingAs($this->student);

        // The client cannot widen the catalog with type/status/trashed filters.
        $response = $this->getJson('/api/items?type=lost&status=returned&trashed=with')->assertOk();
        $this->assertSame([$visible->id], collect($response->json('data'))->pluck('id')->all());

        $row = $response->json('data.0');
        foreach (['reporter', 'contact_info', 'admin_note', 'delete_reason', 'deleted_at', 'user_id'] as $field) {
            $this->assertArrayNotHasKey($field, $row);
        }

        $this->getJson("/api/items/{$visible->id}")->assertOk()->assertJsonMissingPath('data.reporter');
    }

    public function test_student_sees_own_requests_but_gets_404_for_others(): void
    {
        $mine = $this->makeItem($this->student, ['title' => 'My lost phone', 'status' => 'found', 'admin_note' => 'Collect it from Student Affairs.']);
        $theirs = $this->makeItem($this->otherStudent);
        $deletedMine = $this->makeItem($this->student);
        $deletedMine->delete();

        Sanctum::actingAs($this->student);

        $ids = collect($this->getJson('/api/items?scope=mine')->assertOk()->json('data'))->pluck('id')->all();
        $this->assertSame([$mine->id], $ids);

        $this->getJson("/api/items/{$mine->id}")->assertOk()
            ->assertJsonPath('data.admin_note', 'Collect it from Student Affairs.')
            ->assertJsonPath('data.contact_info', '050 000 0000')
            ->assertJsonMissingPath('data.reporter');

        $this->getJson("/api/items/{$theirs->id}")->assertStatus(404)->assertExactJson(['message' => 'Not found.']);
        $this->getJson("/api/items/{$deletedMine->id}")->assertStatus(404);
        $this->putJson("/api/items/{$theirs->id}", $this->itemPayload())->assertStatus(404);
    }

    public function test_student_can_only_create_lost_requests(): void
    {
        Storage::fake('public');
        Sanctum::actingAs($this->student);

        $response = $this->post('/api/items', $this->itemPayload([
            'type' => 'found',
            'status' => 'returned',
            'user_id' => $this->otherStudent->id,
            'photo' => UploadedFile::fake()->image('umbrella.png', 400, 300),
        ]))->assertCreated();

        $response->assertJsonPath('data.type', 'lost')->assertJsonPath('data.status', 'open');
        $this->assertDatabaseHas('items', ['id' => $response->json('data.id'), 'type' => 'lost', 'user_id' => $this->student->id]);

        $path = \App\Models\Item::find($response->json('data.id'))->image_path;
        Storage::disk('public')->assertExists($path);
        $this->assertStringStartsWith('items/', $path);
        $this->assertDatabaseHas('activity_logs', ['action' => 'item.created']);
    }

    public function test_photo_upload_is_validated(): void
    {
        Storage::fake('public');
        Sanctum::actingAs($this->student);

        $this->post('/api/items', $this->itemPayload(['photo' => UploadedFile::fake()->create('doc.pdf', 100, 'application/pdf')]))
            ->assertStatus(422)->assertJsonValidationErrors('photo');
        $this->post('/api/items', $this->itemPayload(['photo' => UploadedFile::fake()->image('big.png')->size(4000)]))
            ->assertStatus(422)->assertJsonValidationErrors('photo');
        $this->postJson('/api/items', ['title' => ''])->assertStatus(422)
            ->assertJsonValidationErrors(['title', 'description', 'category_id', 'location_id', 'date_reported']);
    }

    public function test_student_can_edit_own_request_only_while_open(): void
    {
        Storage::fake('public');
        $open = $this->makeItem($this->student);
        $matched = $this->makeItem($this->student, ['status' => 'found']);

        Sanctum::actingAs($this->student);

        // Multipart update through POST + _method=PUT, with a new photo.
        $this->post("/api/items/{$open->id}", $this->itemPayload([
            '_method' => 'PUT', 'title' => 'Updated title', 'type' => 'found',
            'photo' => UploadedFile::fake()->image('new.png'),
        ]))->assertOk()->assertJsonPath('data.title', 'Updated title')->assertJsonPath('data.type', 'lost');
        $this->assertNotNull($open->fresh()->image_path);

        $this->putJson("/api/items/{$open->id}", $this->itemPayload(['remove_image' => true]))->assertOk()
            ->assertJsonPath('data.image_url', null);

        $this->putJson("/api/items/{$matched->id}", $this->itemPayload(['title' => 'Nope']))->assertStatus(403);
        $this->assertDatabaseMissing('items', ['title' => 'Nope']);
    }

    public function test_photos_use_the_configured_uploads_disk(): void
    {
        config(['unifind.uploads_disk' => 's3']);
        Storage::fake('public');
        Storage::fake('s3', ['url' => 'https://pub-test.r2.dev']);
        Sanctum::actingAs($this->student);

        $created = $this->post('/api/items', $this->itemPayload([
            'photo' => UploadedFile::fake()->image('first.png'),
        ]))->assertCreated();

        $item = \App\Models\Item::find($created->json('data.id'));
        $first = $item->image_path;
        Storage::disk('s3')->assertExists($first);
        Storage::disk('public')->assertMissing($first);
        $this->assertSame('https://pub-test.r2.dev/'.$first, $created->json('data.image_url'));

        // Replacing the photo deletes the old one from the same disk.
        $this->post("/api/items/{$item->id}", $this->itemPayload([
            '_method' => 'PUT', 'photo' => UploadedFile::fake()->image('second.png'),
        ]))->assertOk();
        $second = $item->fresh()->image_path;
        Storage::disk('s3')->assertMissing($first);
        Storage::disk('s3')->assertExists($second);

        // Removing it deletes it too.
        $this->putJson("/api/items/{$item->id}", $this->itemPayload(['remove_image' => true]))->assertOk()
            ->assertJsonPath('data.image_url', null);
        Storage::disk('s3')->assertMissing($second);
    }

    public function test_local_photos_get_a_relative_storage_url(): void
    {
        Storage::fake('public');
        Sanctum::actingAs($this->student);

        $created = $this->post('/api/items', $this->itemPayload([
            'photo' => UploadedFile::fake()->image('local.png'),
        ]))->assertCreated();

        $this->assertStringStartsWith('/storage/items/', $created->json('data.image_url'));
    }
}
