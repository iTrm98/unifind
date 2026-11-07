<?php

namespace App\Http\Controllers;

use App\Http\Resources\ItemResource;
use App\Models\ActivityLog;
use App\Models\Item;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class ItemController extends Controller
{
    private const WITH = ['category', 'location', 'user', 'matchedItem.location'];

    /**
     * Students: scope=catalog (open found items, default) or scope=mine (their own requests).
     * Admins: every item, optionally with removed ones (trashed=with|only).
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'type' => ['nullable', Rule::in(Item::TYPES)],
            'status' => ['nullable', Rule::in(Item::STATUSES)],
            'category_id' => ['nullable', 'integer'],
            'location_id' => ['nullable', 'integer'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date'],
            'scope' => ['nullable', Rule::in(['catalog', 'mine'])],
            'trashed' => ['nullable', Rule::in(['with', 'only'])],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
            'sort' => ['nullable', Rule::in(['newest', 'oldest'])],
        ]);

        $user = $request->user();
        $filters = $request->only(['search', 'type', 'status', 'category_id', 'location_id', 'date_from', 'date_to']);
        $query = Item::query()->with(self::WITH);

        if ($user->isAdmin()) {
            match ($request->input('trashed')) {
                'with' => $query->withTrashed(),
                'only' => $query->onlyTrashed(),
                default => null,
            };
        } elseif ($request->input('scope') === 'mine') {
            $query->where('user_id', $user->id);
        } else {
            // The public catalog: type and status are fixed, whatever the client sends.
            $query->catalog();
            unset($filters['type'], $filters['status']);
        }

        $query->filter($filters);

        $direction = $request->input('sort') === 'oldest' ? 'asc' : 'desc';
        $query->orderBy('date_reported', $direction)->orderBy('id', $direction);

        return ItemResource::collection(
            $query->paginate((int) $request->input('per_page', 12))->withQueryString()
        );
    }

    public function show(Request $request, int $id): ItemResource
    {
        return new ItemResource($this->findVisible($request, $id));
    }

    public function store(Request $request): JsonResponse
    {
        $user = $request->user();
        $data = $request->validate($this->rules($user->isAdmin()));

        // Students may only create lost requests; any "type" they send is ignored.
        $data['type'] = $user->isAdmin() ? ($data['type'] ?? 'found') : 'lost';
        $data['user_id'] = $user->id;
        $data['status'] = 'open';

        if ($request->hasFile('photo')) {
            $data['image_path'] = $request->file('photo')->store('items', Item::uploadsDisk());
        }
        unset($data['photo'], $data['remove_image']);

        $item = Item::create($data);
        ActivityLog::record('item.created', $item, ['type' => $item->type, 'title' => $item->title]);

        return (new ItemResource($item->load(self::WITH)))->response()->setStatusCode(201);
    }

    public function update(Request $request, int $id): ItemResource
    {
        $user = $request->user();
        $item = $this->findVisible($request, $id, includeTrashed: false);

        if (! $item->canBeEditedBy($user)) {
            abort(response()->json([
                'message' => 'This request can no longer be edited because the Lost & Found Office has already updated its status.',
            ], 403));
        }

        $data = $request->validate($this->rules($user->isAdmin()));
        if (! $user->isAdmin()) {
            unset($data['type']);
        }

        if ($request->boolean('remove_image') || $request->hasFile('photo')) {
            if ($item->image_path) {
                Storage::disk(Item::uploadsDisk())->delete($item->image_path);
            }
            $data['image_path'] = null;
        }
        if ($request->hasFile('photo')) {
            $data['image_path'] = $request->file('photo')->store('items', Item::uploadsDisk());
        }
        unset($data['photo'], $data['remove_image']);

        $item->fill($data);
        $changed = array_keys($item->getDirty());
        $item->save();

        ActivityLog::record('item.updated', $item, ['fields' => $changed]);

        return new ItemResource($item->load(self::WITH));
    }

    private function rules(bool $isAdmin): array
    {
        return [
            'type' => [$isAdmin ? 'nullable' : 'exclude', Rule::in(Item::TYPES)],
            'title' => ['required', 'string', 'min:3', 'max:80'],
            'description' => ['required', 'string', 'min:10', 'max:2000'],
            'category_id' => ['required', 'integer', 'exists:categories,id'],
            'location_id' => ['required', 'integer', 'exists:locations,id'],
            'date_reported' => ['required', 'date', 'before_or_equal:today'],
            'contact_info' => ['nullable', 'string', 'max:120'],
            'photo' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp', 'max:3072'],
            'remove_image' => ['nullable', 'boolean'],
        ];
    }

    /**
     * Returns an item the current user may see, or 404 so that other people's
     * requests are indistinguishable from ids that do not exist.
     */
    private function findVisible(Request $request, int $id, bool $includeTrashed = true): Item
    {
        $user = $request->user();

        if ($user->isAdmin()) {
            $query = Item::with(self::WITH);
            if ($includeTrashed) {
                $query->withTrashed();
            }

            return $query->findOrFail($id);
        }

        return Item::with(self::WITH)
            ->where('id', $id)
            ->where(fn (Builder $q) => $q->where('user_id', $user->id)
                ->orWhere(fn (Builder $q) => $q->catalog()))
            ->firstOrFail();
    }
}
