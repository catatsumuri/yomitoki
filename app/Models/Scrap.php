<?php

namespace App\Models;

use Database\Factories\ScrapFactory;
use Illuminate\Database\Eloquent\Attributes\Cast;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'user_id',
    'parent_id',
    'scrap_source_id',
    'source_type',
    'source_reference',
    'title',
    'slug',
    'content',
    'content_markdown',
    'summary',
    'status',
    'language',
    'occurred_at',
    'processed_at',
    'extracted_data',
    'meta',
])]
class Scrap extends Model
{
    /** @use HasFactory<ScrapFactory> */
    use HasFactory;

    /**
     * Get the user that owns the scrap.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the parent scrap when this scrap is nested under another scrap.
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /**
     * Get the child scraps nested under this scrap.
     */
    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')
            ->orderBy('occurred_at')
            ->orderBy('id');
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    #[Cast]
    protected function casts(): array
    {
        return [
            'occurred_at' => 'datetime',
            'processed_at' => 'datetime',
            'extracted_data' => 'array',
            'meta' => 'array',
        ];
    }
}
