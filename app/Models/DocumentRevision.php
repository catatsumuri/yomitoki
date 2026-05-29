<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'document_id',
    'title',
    'content_markdown',
])]
class DocumentRevision extends Model
{
    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }
}
