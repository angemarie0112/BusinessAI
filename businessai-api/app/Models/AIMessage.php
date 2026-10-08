<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'ai_conversation_id',
    'role',
    'content',
    'model',
])]
class AIMessage extends Model
{
    /**
     * Explicitly define the table name.
     */
    protected $table = 'ai_messages';

    /**
     * The conversation this message belongs to.
     */
    public function conversation(): BelongsTo
    {
        return $this->belongsTo(
            AIConversation::class,
            'ai_conversation_id'
        );
    }
}