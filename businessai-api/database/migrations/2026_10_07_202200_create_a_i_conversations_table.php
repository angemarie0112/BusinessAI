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
        Schema::create('ai_conversations', function (Blueprint $table) {
            $table->id();

            /*
             * The user who owns this conversation.
             *
             * If the user is deleted, their conversations
             * should also be deleted.
             */
            $table->foreignId('user_id')
                ->constrained()
                ->cascadeOnDelete();

            /*
             * The document this conversation belongs to.
             *
             * If the document is deleted, its conversations
             * should also disappear.
             */
            $table->foreignId('document_id')
                ->constrained()
                ->cascadeOnDelete();

            /*
             * Human-readable conversation title.
             *
             * Later we can automatically generate this from
             * the user's first question.
             */
            $table->string('title')->nullable();

            $table->timestamps();

            /*
             * Helpful when loading a user's conversations
             * for one particular document.
             */
            $table->index([
                'user_id',
                'document_id',
            ]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ai_conversations');
    }
};