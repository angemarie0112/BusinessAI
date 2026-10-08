<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'user_id',
    'name',
    'original_filename',
    'file_path',
    'mime_type',
    'file_size',
    'status',
    'extracted_text',
    'error_message',
])]
class Document extends Model
{
    /**
     * Get the user that owns the document.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get all chunks belonging to this document.
     */
    public function chunks(): HasMany
    {
        return $this->hasMany(DocumentChunk::class)
            ->orderBy('chunk_index');
    }

    /**
     * Get all AI conversations associated with this document.
     */
    public function aiConversations(): HasMany
    {
        return $this->hasMany(AIConversation::class)
            ->latest();
    }
}