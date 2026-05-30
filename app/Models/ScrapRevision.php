<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'scrap_id',
    'content',
])]
class ScrapRevision extends Model
{
    public function scrap(): BelongsTo
    {
        return $this->belongsTo(Scrap::class);
    }
}
