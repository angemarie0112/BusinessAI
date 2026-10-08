<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'user_id',
    'document_id',
    'title',
])]
class AIConversation extends Model
{
    /**
     * Explicitly define the table name.
     *
     * Laravel would otherwise infer:
     * a_i_conversations
     */
    protected $table = 'ai_conversations';

    /**
     * The user who owns this conversation.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * The document being discussed.
     */
    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }

    /**
     * All messages belonging to this conversation.
     */
    public function messages(): HasMany
    {
        return $this->hasMany(
            AIMessage::class,
            'ai_conversation_id'
        )->orderBy('id');
    }
}