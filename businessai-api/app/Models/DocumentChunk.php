<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'document_id',
    'chunk_index',
    'content',
    'embedding',
])]
class DocumentChunk extends Model
{
    /**
     * Get the document that owns this chunk.
     */
    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }

    /**
     * Cast database values to PHP types.
     */
    protected function casts(): array
    {
        return [
            'embedding' => 'array',
        ];
    }
}