<?php

namespace App\Http\Controllers;

use App\Models\AIConversation;
use App\Models\Document;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Throwable;

class AIController extends Controller
{
    /**
     * Base URL of the Python FastAPI AI service.
     */
    private string $aiServiceUrl = 'http://127.0.0.1:8001';

    /**
     * Check whether the Python AI service is available.
     */
    public function health()
    {
        try {
            $response = Http::timeout(5)
                ->get("{$this->aiServiceUrl}/health");

            if ($response->failed()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'AI service returned an error.',
                ], 502);
            }

            return response()->json([
                'status' => 'ok',
                'message' => 'Laravel successfully connected to the AI service.',
                'ai_service' => $response->json(),
            ]);
        } catch (Throwable $exception) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unable to connect to the AI service.',
            ], 503);
        }
    }

    /**
     * Send a processed document to the Python AI service
     * and store the returned chunks.
     */
    public function chunkDocument(Request $request, Document $document)
    {
        if ($document->user_id !== $request->user()->id) {
            return response()->json([
                'message' => 'You are not authorized to access this document.',
            ], 403);
        }

        if (
            $document->status !== 'processed' ||
            empty($document->extracted_text)
        ) {
            return response()->json([
                'message' => 'This document has not been processed yet.',
            ], 422);
        }

        try {
            $response = Http::timeout(30)
                ->post("{$this->aiServiceUrl}/chunk", [
                    'text' => $document->extracted_text,
                    'chunk_size' => 1000,
                    'overlap' => 200,
                ]);

            if ($response->failed()) {
                return response()->json([
                    'message' => 'The AI service could not chunk the document.',
                    'ai_error' => $response->json(),
                ], 502);
            }

            $chunks = $response->json('chunks');

            if (! is_array($chunks)) {
                return response()->json([
                    'message' => 'The AI service returned an invalid response.',
                ], 502);
            }

            $document->chunks()->delete();

            foreach ($chunks as $index => $content) {
                $document->chunks()->create([
                    'chunk_index' => $index,
                    'content' => $content,
                ]);
            }

            return response()->json([
                'message' => 'Document chunked successfully.',
                'document_id' => $document->id,
                'chunk_count' => count($chunks),
            ]);
        } catch (Throwable $exception) {
            return response()->json([
                'message' => 'Unable to communicate with the AI service.',
                'error' => $exception->getMessage(),
            ], 503);
        }
    }

    /**
     * Generate and store an embedding for one document chunk.
     */
    public function embedChunk(
        Request $request,
        Document $document,
        int $chunkIndex
    ) {
        if ($document->user_id !== $request->user()->id) {
            return response()->json([
                'message' => 'You are not authorized to access this document.',
            ], 403);
        }

        $chunk = $document->chunks()
            ->where('chunk_index', $chunkIndex)
            ->first();

        if (! $chunk) {
            return response()->json([
                'message' => 'Chunk not found.',
            ], 404);
        }

        try {
            $response = Http::timeout(30)
                ->post("{$this->aiServiceUrl}/embed", [
                    'text' => $chunk->content,
                ]);

            if ($response->failed()) {
                return response()->json([
                    'message' => 'The AI service could not create the embedding.',
                    'ai_error' => $response->json(),
                ], 502);
            }

            $embedding = $response->json('embedding');

            if (! is_array($embedding) || empty($embedding)) {
                return response()->json([
                    'message' => 'The AI service returned an invalid embedding.',
                ], 502);
            }

            $chunk->embedding = $embedding;
            $chunk->save();

            return response()->json([
                'message' => 'Chunk embedded successfully.',
                'document_id' => $document->id,
                'chunk_id' => $chunk->id,
                'chunk_index' => $chunk->chunk_index,
                'dimensions' => count($embedding),
            ]);
        } catch (Throwable $exception) {
            return response()->json([
                'message' => 'Unable to communicate with the AI service.',
                'error' => $exception->getMessage(),
            ], 503);
        }
    }

    /**
     * Generate and store embeddings for all chunks of a document.
     */
    public function embedDocument(Request $request, Document $document)
    {
        if ($document->user_id !== $request->user()->id) {
            return response()->json([
                'message' => 'You are not authorized to access this document.',
            ], 403);
        }

        $chunks = $document->chunks()
            ->orderBy('chunk_index')
            ->get();

        if ($chunks->isEmpty()) {
            return response()->json([
                'message' => 'This document does not have any chunks yet.',
            ], 422);
        }

        try {
            $texts = $chunks
                ->pluck('content')
                ->values()
                ->all();

            $response = Http::timeout(120)
                ->post("{$this->aiServiceUrl}/embed/batch", [
                    'texts' => $texts,
                ]);

            if ($response->failed()) {
                return response()->json([
                    'message' => 'The AI service could not create embeddings.',
                    'ai_error' => $response->json(),
                ], 502);
            }

            $embeddings = $response->json('embeddings');

            if (! is_array($embeddings)) {
                return response()->json([
                    'message' => 'The AI service returned invalid embeddings.',
                ], 502);
            }

            if (count($embeddings) !== $chunks->count()) {
                return response()->json([
                    'message' => 'The number of embeddings does not match the number of chunks.',
                ], 502);
            }

            foreach ($chunks as $index => $chunk) {
                $embedding = $embeddings[$index];

                if (
                    ! is_array($embedding) ||
                    count($embedding) !== 384
                ) {
                    return response()->json([
                        'message' => "Invalid embedding received for chunk {$chunk->chunk_index}.",
                    ], 502);
                }

                $chunk->embedding = $embedding;
                $chunk->save();
            }

            return response()->json([
                'message' => 'Document embeddings generated successfully.',
                'document_id' => $document->id,
                'chunk_count' => $chunks->count(),
                'embedded_count' => count($embeddings),
                'dimensions' => 384,
            ]);
        } catch (Throwable $exception) {
            return response()->json([
                'message' => 'Unable to communicate with the AI service.',
                'error' => $exception->getMessage(),
            ], 503);
        }
    }

    /**
     * Search a document semantically using its stored chunk embeddings.
     */
    public function searchDocument(Request $request, Document $document)
    {
        if ($document->user_id !== $request->user()->id) {
            return response()->json([
                'message' => 'You are not authorized to access this document.',
            ], 403);
        }

        $validated = $request->validate([
            'question' => ['required', 'string', 'max:2000'],
            'top_k' => ['nullable', 'integer', 'min:1', 'max:10'],
        ]);

        $chunks = $document->chunks()
            ->whereNotNull('embedding')
            ->orderBy('chunk_index')
            ->get();

        if ($chunks->isEmpty()) {
            return response()->json([
                'message' => 'This document does not have embedded chunks yet.',
            ], 422);
        }

        $searchChunks = $chunks->map(function ($chunk) {
            return [
                'chunk_index' => $chunk->chunk_index,
                'content' => $chunk->content,
                'embedding' => $chunk->embedding,
            ];
        })->values()->all();

        try {
            $response = Http::timeout(60)
                ->post("{$this->aiServiceUrl}/search", [
                    'question' => $validated['question'],
                    'chunks' => $searchChunks,
                    'top_k' => $validated['top_k'] ?? 3,
                ]);

            if ($response->failed()) {
                return response()->json([
                    'message' => 'The AI service could not search the document.',
                    'ai_error' => $response->json(),
                ], 502);
            }

            return response()->json([
                'message' => 'Semantic search completed successfully.',
                'document_id' => $document->id,
                'question' => $validated['question'],
                'results' => $response->json('results'),
                'count' => $response->json('count'),
            ]);
        } catch (Throwable $exception) {
            return response()->json([
                'message' => 'Unable to communicate with the AI service.',
                'error' => $exception->getMessage(),
            ], 503);
        }
    }

    /**
     * Ask a question about a document.
     *
     * Persistent conversational RAG flow:
     *
     * 1. Validate the conversation.
     * 2. Load previous messages from the database.
     * 3. Rewrite follow-up questions using conversation history.
     * 4. Search the document using the rewritten question.
     * 5. Generate an answer using document context and history.
     * 6. Save the user question and assistant answer.
     * 7. Return the generated answer.
     */
    public function askDocument(Request $request, Document $document)
    {
        if ($document->user_id !== $request->user()->id) {
            return response()->json([
                'message' => 'You are not authorized to access this document.',
            ], 403);
        }

        $validated = $request->validate([
            'question' => ['required', 'string', 'max:2000'],
            'top_k' => ['nullable', 'integer', 'min:1', 'max:10'],
            'conversation_id' => [
                'required',
                'integer',
                'exists:ai_conversations,id',
            ],
        ]);

        $question = trim($validated['question']);
        $topK = $validated['top_k'] ?? 3;

        /*
         * Load the conversation explicitly through the
         * authenticated user.
         *
         * This prevents a user from using a conversation
         * belonging to another account.
         */
        $conversation = AIConversation::query()
            ->where('id', $validated['conversation_id'])
            ->where('user_id', $request->user()->id)
            ->first();

        if (! $conversation) {
            return response()->json([
                'message' => 'Conversation not found.',
            ], 404);
        }

        /*
         * A conversation is permanently associated with
         * one document.
         *
         * Prevent a conversation created for Document A
         * from being used to ask questions about Document B.
         */
        if ($conversation->document_id !== $document->id) {
            return response()->json([
                'message' => 'This conversation does not belong to the selected document.',
            ], 422);
        }

        /*
         * Load the most recent conversation messages.
         *
         * We limit the history sent to the AI service so
         * conversations do not grow indefinitely inside
         * every prompt.
         */
        $history = $conversation->messages()
            ->latest('id')
            ->limit(10)
            ->get()
            ->reverse()
            ->values()
            ->map(function ($message) {
                return [
                    'role' => $message->role,
                    'content' => $message->content,
                ];
            })
            ->all();

        /*
         * Load all document chunks that already have
         * embeddings.
         */
        $chunks = $document->chunks()
            ->whereNotNull('embedding')
            ->orderBy('chunk_index')
            ->get();

        if ($chunks->isEmpty()) {
            return response()->json([
                'message' => 'This document does not have embedded chunks yet.',
            ], 422);
        }

        $searchChunks = $chunks
            ->map(function ($chunk) {
                return [
                    'chunk_index' => $chunk->chunk_index,
                    'content' => $chunk->content,
                    'embedding' => $chunk->embedding,
                ];
            })
            ->values()
            ->all();

        try {
            /*
             * STEP 1:
             * Rewrite follow-up questions using the history
             * loaded from our own database.
             *
             * The rewritten question is used only for
             * semantic retrieval.
             */
            $searchQuestion = $question;

            if (! empty($history)) {
                $rewriteResponse = Http::timeout(120)
                    ->post("{$this->aiServiceUrl}/rewrite", [
                        'question' => $question,
                        'history' => $history,
                    ]);

                /*
                 * Rewriting improves retrieval but is not
                 * required for the request to succeed.
                 */
                if ($rewriteResponse->successful()) {
                    $rewrittenQuestion = $rewriteResponse->json(
                        'rewritten_question'
                    );

                    if (
                        is_string($rewrittenQuestion) &&
                        ! empty(trim($rewrittenQuestion))
                    ) {
                        $searchQuestion = trim(
                            $rewrittenQuestion
                        );
                    }
                }
            }

            /*
             * STEP 2:
             * Search the selected document.
             */
            $searchResponse = Http::timeout(60)
                ->post("{$this->aiServiceUrl}/search", [
                    'question' => $searchQuestion,
                    'chunks' => $searchChunks,
                    'top_k' => $topK,
                ]);

            if ($searchResponse->failed()) {
                return response()->json([
                    'message' => 'The AI service could not search the document.',
                    'ai_error' => $searchResponse->json(),
                ], 502);
            }

            $results = $searchResponse->json('results');

            if (! is_array($results) || empty($results)) {
                return response()->json([
                    'message' => 'No relevant document context was found.',
                ], 422);
            }

            /*
             * STEP 3:
             * Convert semantic search results into the
             * context format expected by FastAPI.
             */
            $context = collect($results)
                ->map(function ($result) {
                    return [
                        'chunk_index' => $result['chunk_index'],
                        'content' => $result['content'],
                        'score' => $result['score'] ?? null,
                    ];
                })
                ->values()
                ->all();

            /*
             * STEP 4:
             * Generate the final answer.
             *
             * We send the original question to the language
             * model. The rewritten question was only used
             * for retrieval.
             */
            $generateResponse = Http::timeout(180)
                ->post("{$this->aiServiceUrl}/generate", [
                    'question' => $question,
                    'context' => $context,
                    'history' => $history,
                ]);

            if ($generateResponse->failed()) {
                return response()->json([
                    'message' => 'The AI service could not generate an answer.',
                    'ai_error' => $generateResponse->json(),
                ], 502);
            }

            $answer = $generateResponse->json('answer');
            $model = $generateResponse->json('model');

            if (
                ! is_string($answer) ||
                empty(trim($answer))
            ) {
                return response()->json([
                    'message' => 'The AI service returned an empty answer.',
                ], 502);
            }

            $answer = trim($answer);

            /*
             * STEP 5:
             * Persist both sides of the successful exchange.
             *
             * We use a database transaction so we do not
             * accidentally save only one side of the chat.
             */
            DB::transaction(function () use (
                $conversation,
                $question,
                $answer,
                $model
            ) {
                $conversation->messages()->create([
                    'role' => 'user',
                    'content' => $question,
                    'model' => null,
                ]);

                $conversation->messages()->create([
                    'role' => 'assistant',
                    'content' => $answer,
                    'model' => $model,
                ]);

                /*
                 * Use the first question as the initial
                 * conversation title.
                 */
                if (
                    $conversation->title === null ||
                    $conversation->title === 'New conversation'
                ) {
                    $title = mb_strlen($question) > 60
                        ? mb_substr($question, 0, 60) . '...'
                        : $question;

                    $conversation->title = $title;
                }

                /*
                 * Updating updated_at makes the most recently
                 * active conversations appear first.
                 */
                $conversation->touch();

                if ($conversation->isDirty('title')) {
                    $conversation->save();
                }
            });

            /*
             * STEP 6:
             * Return the successful conversational response.
             */
            return response()->json([
                'message' => 'Answer generated successfully.',

                'conversation_id' => $conversation->id,
                'document_id' => $document->id,

                'question' => $question,

                /*
                 * Useful during development for confirming
                 * that follow-up rewriting works.
                 */
                'search_question' => $searchQuestion,

                'answer' => $answer,
                'model' => $model,

                /*
                 * Keep sources in the API response for now.
                 *
                 * The Vue UI does not have to display them.
                 */
                'sources' => collect($results)
                    ->map(function ($result) {
                        $content = trim($result['content'] ?? '');

                        return [
                            'chunk_index' => $result['chunk_index'],
                            'score' => $result['score'] ?? null,
                            'content' => $content,
                            'preview' => mb_strlen($content) > 220
                                ? mb_substr($content, 0, 220) . '...'
                                : $content,
                        ];
                    })
                    ->values()
                    ->all(),
            ]);
        } catch (Throwable $exception) {
            return response()->json([
                'message' => 'Unable to complete the AI request.',
                'error' => $exception->getMessage(),
            ], 503);
        }
    }
}