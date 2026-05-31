<?php

namespace App\Models;

use Database\Factories\ScrapFactory;
use Illuminate\Database\Eloquent\Attributes\Cast;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

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
    'embedding',
    'embedding_model',
    'embedding_generated_at',
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

    public function revisions(): HasMany
    {
        return $this->hasMany(ScrapRevision::class)->latest();
    }

    /**
     * Get parent scraps similar to this one using pgvector cosine distance.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function relatedScraps(int $limit = 5): Collection
    {
        if (! $this->embedding) {
            return collect();
        }

        $vector = json_decode($this->embedding, true);

        return static::query()
            ->select(['id', 'title', 'slug', 'summary', 'status', 'source_type', 'occurred_at'])
            ->selectVectorDistance('embedding', $vector, 'distance')
            ->whereVectorSimilarTo('embedding', $vector, minSimilarity: 0.0, order: true)
            ->where('user_id', $this->user_id)
            ->where('id', '!=', $this->id)
            ->whereNull('parent_id')
            ->where('status', '!=', 'archived')
            ->whereNotNull('embedding')
            ->limit($limit)
            ->get()
            ->map(fn (self $row) => [
                'id' => $row->id,
                'title' => $row->title,
                'slug' => $row->slug,
                'summary' => $row->summary,
                'status' => $row->status,
                'sourceType' => $row->source_type,
                'occurredAt' => $row->occurred_at,
                'similarity' => round(1 - (float) $row->distance, 3),
            ]);
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
