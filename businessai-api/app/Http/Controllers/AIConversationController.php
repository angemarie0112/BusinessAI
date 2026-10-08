<?php

namespace App\Http\Controllers;

use App\Models\AIConversation;
use App\Models\Document;
use Illuminate\Http\Request;

class AIConversationController extends Controller
{
    /**
     * Get all AI conversations belonging to the
     * authenticated user.
     */
    public function index(Request $request)
    {
        $conversations = AIConversation::query()
            ->where('user_id', $request->user()->id)
            ->with([
                'document:id,original_filename',
            ])
            ->withCount('messages')
            ->latest('updated_at')
            ->get();

        return response()->json([
            'conversations' => $conversations,
        ]);
    }

    /**
     * Create a new AI conversation for a document.
     */
    public function store(Request $request, Document $document)
    {
        /*
         * A user may only create conversations for
         * documents that belong to them.
         */
        if ($document->user_id !== $request->user()->id) {
            return response()->json([
                'message' => 'You are not authorized to access this document.',
            ], 403);
        }

        /*
         * Ask AI should only work with documents that
         * completed the entire AI preparation pipeline.
         */
        if ($document->status !== 'ready') {
            return response()->json([
                'message' => 'This document is not ready for AI conversations yet.',
            ], 422);
        }

        /*
         * Create an empty conversation.
         *
         * The title can later be generated automatically
         * from the user's first question.
         */
        $conversation = AIConversation::create([
            'user_id' => $request->user()->id,
            'document_id' => $document->id,
            'title' => 'New conversation',
        ]);

        return response()->json([
            'message' => 'Conversation created successfully.',
            'conversation' => $conversation,
        ], 201);
    }

    /**
     * Get one conversation together with its messages.
     */
    public function show(
        Request $request,
        AIConversation $conversation
    ) {
        /*
         * Prevent users from accessing conversations
         * belonging to another account.
         */
        if ($conversation->user_id !== $request->user()->id) {
            return response()->json([
                'message' => 'You are not authorized to access this conversation.',
            ], 403);
        }

        $conversation->load([
            'document:id,original_filename,status',
            'messages' => function ($query) {
                $query->orderBy('created_at')
                    ->orderBy('id');
            },
        ]);

        return response()->json([
            'conversation' => $conversation,
        ]);
    }

    /**
     * Delete one AI conversation.
     *
     * Messages belonging to the conversation are
     * automatically deleted by the database because
     * ai_messages uses cascadeOnDelete().
     */
    public function destroy(
        Request $request,
        AIConversation $conversation
    ) {
        /*
         * A user may only delete their own
         * conversations.
         */
        if ($conversation->user_id !== $request->user()->id) {
            return response()->json([
                'message' => 'You are not authorized to delete this conversation.',
            ], 403);
        }

        $conversation->delete();

        return response()->json([
            'message' => 'Conversation deleted successfully.',
        ]);
    }
}