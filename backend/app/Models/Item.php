<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

class Item extends Model
{
    use SoftDeletes;

    public const TYPES = ['lost', 'found'];
    public const STATUSES = ['open', 'found', 'returned', 'closed'];

    protected $fillable = [
        'user_id', 'category_id', 'location_id', 'type', 'title', 'description',
        'date_reported', 'contact_info', 'image_path', 'status', 'admin_note',
        'matched_item_id', 'delete_reason',
    ];

    protected function casts(): array
    {
        return [
            'date_reported' => 'date:Y-m-d',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function matchedItem(): BelongsTo
    {
        return $this->belongsTo(Item::class, 'matched_item_id')->withTrashed();
    }

    /** Found items a student may browse. */
    public function scopeCatalog(Builder $query): Builder
    {
        return $query->where('type', 'found')->where('status', 'open');
    }

    /**
     * Applies the list filters shared by students and admins:
     * search, type, status, category_id, location_id, date_from, date_to.
     */
    public function scopeFilter(Builder $query, array $filters): Builder
    {
        if (filled($filters['search'] ?? null)) {
            $term = '%'.trim($filters['search']).'%';
            $query->where(fn (Builder $q) => $q->where('title', 'like', $term)->orWhere('description', 'like', $term));
        }

        foreach (['type' => self::TYPES, 'status' => self::STATUSES] as $field => $allowed) {
            if (in_array($filters[$field] ?? null, $allowed, true)) {
                $query->where($field, $filters[$field]);
            }
        }

        foreach (['category_id', 'location_id'] as $field) {
            if (filled($filters[$field] ?? null)) {
                $query->where($field, (int) $filters[$field]);
            }
        }

        if (filled($filters['date_from'] ?? null)) {
            $query->whereDate('date_reported', '>=', $filters['date_from']);
        }
        if (filled($filters['date_to'] ?? null)) {
            $query->whereDate('date_reported', '<=', $filters['date_to']);
        }

        return $query;
    }

    /** The disk item photos are stored on (UPLOADS_DISK). */
    public static function uploadsDisk(): string
    {
        return (string) config('unifind.uploads_disk', 'public');
    }

    /**
     * Relative /storage/... URL on the local public disk (the Vite proxy serves it),
     * absolute URL on a cloud disk such as R2.
     */
    public function imageUrl(): ?string
    {
        if (! $this->image_path) {
            return null;
        }

        $disk = self::uploadsDisk();

        return $disk === 'public'
            ? '/storage/'.ltrim($this->image_path, '/')
            : Storage::disk($disk)->url($this->image_path);
    }

    public function canBeEditedBy(User $user): bool
    {
        if ($this->trashed()) {
            return false;
        }

        return $user->isAdmin() || ($this->user_id === $user->id && $this->status === 'open');
    }
}
