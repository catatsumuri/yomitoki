<?php

namespace App\Models;

use Database\Factories\DocumentFactory;
use Illuminate\Database\Eloquent\Attributes\Cast;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

#[Fillable([
    'user_id',
    'title',
    'document_type',
    'status',
    'content_markdown',
    'summary',
    'outline',
    'meta',
    'published_at',
])]
class Document extends Model
{
    /** @use HasFactory<DocumentFactory> */
    use HasFactory;

    #[Cast]
    protected function casts(): array
    {
        return [
            'outline' => 'array',
            'meta' => 'array',
            'published_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scraps(): BelongsToMany
    {
        return $this->belongsToMany(Scrap::class, 'document_scraps')
            ->withPivot(['position', 'role', 'excerpt'])
            ->orderByPivot('position');
    }
}
