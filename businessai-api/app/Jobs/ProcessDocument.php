<?php

namespace App\Jobs;

use App\Models\Document;
use App\Services\AIService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Smalot\PdfParser\Parser;
use Throwable;

class ProcessDocument implements ShouldQueue
{
    use Queueable;

    /**
     * The document that should be processed.
     */
    public Document $document;

    /**
     * Allow enough time for PDF extraction,
     * chunking and embedding generation.
     */
    public int $timeout = 300;

    /**
     * Retry the job up to three times if it fails.
     */
    public int $tries = 3;

    /**
     * Create a new job instance.
     */
    public function __construct(Document $document)
    {
        $this->document = $document;
    }

    /**
     * Execute the job.
     */
    public function handle(AIService $aiService): void
    {
        /*
         * Refresh the model in case anything changed
         * between upload and queue execution.
         */
        $this->document->refresh();

        $this->document->update([
            'status' => 'processing',
            'error_message' => null,
        ]);

        try {
            // =========================
            // 1. FIND PDF
            // =========================

            if (! Storage::disk('local')->exists($this->document->file_path)) {
                throw new RuntimeException(
                    'The document file could not be found.'
                );
            }

            $absolutePath = Storage::disk('local')
                ->path($this->document->file_path);

            // =========================
            // 2. EXTRACT PDF TEXT
            // =========================

            $parser = new Parser();

            $pdf = $parser->parseFile($absolutePath);

            $text = trim($pdf->getText());

            if ($text === '') {
                throw new RuntimeException(
                    'No text could be extracted from this PDF.'
                );
            }

            /*
             * Save the extracted text before starting
             * the AI preparation stage.
             */
            $this->document->update([
                'extracted_text' => $text,
            ]);

            // =========================
            // 3. CHUNK DOCUMENT
            // =========================

            $chunks = $aiService->chunk(
                text: $text,
                chunkSize: 1000,
                overlap: 200,
            );

            if (empty($chunks)) {
                throw new RuntimeException(
                    'The AI service did not create any document chunks.'
                );
            }

            // =========================
            // 4. GENERATE EMBEDDINGS
            // =========================

            $embeddings = $aiService->embedBatch($chunks);

            if (count($chunks) !== count($embeddings)) {
                throw new RuntimeException(
                    'The number of embeddings does not match the number of chunks.'
                );
            }

            // =========================
            // 5. STORE CHUNKS
            // =========================

            /*
             * Use a database transaction so that we never
             * leave the document with only some chunks saved.
             */
            DB::transaction(function () use ($chunks, $embeddings) {

                /*
                 * Remove previous chunks if this document
                 * is being processed again.
                 */
                $this->document->chunks()->delete();

                foreach ($chunks as $index => $content) {
                    $this->document->chunks()->create([
                        'chunk_index' => $index,
                        'content' => $content,
                        'embedding' => $embeddings[$index],
                    ]);
                }
            });

            // =========================
            // 6. DOCUMENT IS AI READY
            // =========================

            $this->document->update([
                'status' => 'ready',
                'error_message' => null,
            ]);
        } catch (Throwable $exception) {
            /*
             * Mark the document as failed.
             *
             * Because the exception is thrown again,
             * Laravel's queue system can retry the job.
             */
            $this->document->update([
                'status' => 'failed',
                'error_message' => $exception->getMessage(),
            ]);

            throw $exception;
        }
    }
}