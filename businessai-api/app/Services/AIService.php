<?php

namespace App\Services;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class AIService
{
    /**
     * Base URL of the BusinessAI FastAPI service.
     */
    private string $baseUrl = 'http://127.0.0.1:8001';

    /**
     * Check whether the AI service is running.
     */
    public function health(): array
    {
        $response = Http::timeout(5)
            ->get("{$this->baseUrl}/health");

        $this->ensureSuccessful(
            $response,
            'AI service health check failed.'
        );

        return $response->json();
    }

    /**
     * Split extracted document text into chunks.
     */
    public function chunk(
        string $text,
        int $chunkSize = 1000,
        int $overlap = 200
    ): array {
        $response = Http::timeout(60)
            ->post("{$this->baseUrl}/chunk", [
                'text' => $text,
                'chunk_size' => $chunkSize,
                'overlap' => $overlap,
            ]);

        $this->ensureSuccessful(
            $response,
            'The AI service could not chunk the document.'
        );

        $chunks = $response->json('chunks');

        if (! is_array($chunks)) {
            throw new RuntimeException(
                'The AI service returned invalid document chunks.'
            );
        }

        return $chunks;
    }

    /**
     * Generate an embedding for one piece of text.
     */
    public function embed(string $text): array
    {
        $response = Http::timeout(60)
            ->post("{$this->baseUrl}/embed", [
                'text' => $text,
            ]);

        $this->ensureSuccessful(
            $response,
            'The AI service could not generate the embedding.'
        );

        $embedding = $response->json('embedding');

        if (! is_array($embedding) || empty($embedding)) {
            throw new RuntimeException(
                'The AI service returned an invalid embedding.'
            );
        }

        return $embedding;
    }

    /**
     * Generate embeddings for multiple pieces of text.
     */
    public function embedBatch(array $texts): array
    {
        if (empty($texts)) {
            return [];
        }

        $response = Http::timeout(120)
            ->post("{$this->baseUrl}/embed/batch", [
                'texts' => array_values($texts),
            ]);

        $this->ensureSuccessful(
            $response,
            'The AI service could not generate document embeddings.'
        );

        $embeddings = $response->json('embeddings');

        if (! is_array($embeddings)) {
            throw new RuntimeException(
                'The AI service returned invalid embeddings.'
            );
        }

        if (count($embeddings) !== count($texts)) {
            throw new RuntimeException(
                'The number of embeddings does not match the number of texts.'
            );
        }

        foreach ($embeddings as $embedding) {
            if (! is_array($embedding) || count($embedding) !== 384) {
                throw new RuntimeException(
                    'The AI service returned an invalid embedding dimension.'
                );
            }
        }

        return $embeddings;
    }

    /**
     * Perform semantic search across embedded chunks.
     */
    public function search(
        string $question,
        array $chunks,
        int $topK = 3
    ): array {
        $response = Http::timeout(60)
            ->post("{$this->baseUrl}/search", [
                'question' => $question,
                'chunks' => $chunks,
                'top_k' => $topK,
            ]);

        $this->ensureSuccessful(
            $response,
            'The AI service could not perform semantic search.'
        );

        $results = $response->json('results');

        if (! is_array($results)) {
            throw new RuntimeException(
                'The AI service returned invalid search results.'
            );
        }

        return $results;
    }

    /**
     * Generate an answer using the local Ollama model.
     *
     * The FastAPI /generate endpoint receives the question
     * together with the relevant chunks found by semantic search.
     */
    public function generate(
        string $question,
        array $contextChunks
    ): array {
        $response = Http::timeout(180)
            ->post("{$this->baseUrl}/generate", [
                'question' => $question,
                'context_chunks' => $contextChunks,
            ]);

        $this->ensureSuccessful(
            $response,
            'The AI service could not generate an answer.'
        );

        $answer = $response->json('answer');

        if (! is_string($answer) || trim($answer) === '') {
            throw new RuntimeException(
                'The AI service returned an invalid answer.'
            );
        }

        return $response->json();
    }

    /**
     * Throw an exception when FastAPI returns an unsuccessful response.
     */
    private function ensureSuccessful(
        Response $response,
        string $message
    ): void {
        if ($response->failed()) {
            $detail = $response->json('detail');

            if (is_string($detail) && $detail !== '') {
                $message .= ' '.$detail;
            }

            throw new RuntimeException($message);
        }
    }
}