<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('ai_messages', function (Blueprint $table) {
            $table->id();

            /*
             * The conversation this message belongs to.
             *
             * Deleting a conversation automatically removes
             * all of its messages.
             */
            $table->foreignId('ai_conversation_id')
                ->constrained('ai_conversations')
                ->cascadeOnDelete();

            /*
             * Identifies who produced the message.
             *
             * Current supported values:
             *
             * user
             * assistant
             */
            $table->string('role', 20);

            /*
             * The actual question or AI answer.
             *
             * longText is useful because AI responses can
             * become much larger than normal VARCHAR fields.
             */
            $table->longText('content');

            /*
             * Only assistant messages normally need this.
             *
             * Example:
             * llama3.2:3b
             */
            $table->string('model')->nullable();

            $table->timestamps();

            /*
             * Makes loading a conversation's messages
             * efficient.
             */
            $table->index('ai_conversation_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ai_messages');
    }
};