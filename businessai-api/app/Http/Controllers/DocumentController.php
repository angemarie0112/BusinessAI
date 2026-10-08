<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessDocument;
use App\Models\Document;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

class DocumentController extends Controller
{
    /**
     * Display the authenticated user's documents.
     */
    public function index(Request $request)
    {
        $documents = $request->user()
            ->documents()
            ->latest()
            ->get();

        return response()->json($documents);
    }

    /**
     * Store a newly uploaded document.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'file' => [
                'required',
                'file',
                'mimes:pdf',
                'max:10240',
            ],
        ]);

        $file = $validated['file'];

        $originalFilename = $file->getClientOriginalName();

        $name = pathinfo(
            $originalFilename,
            PATHINFO_FILENAME
        );

        $storedFilename =
            Str::uuid() . '.' . $file->getClientOriginalExtension();

        $filePath = $file->storeAs(
            'documents',
            $storedFilename,
            'local'
        );

        $document = $request->user()
            ->documents()
            ->create([
                'name' => $name,
                'original_filename' => $originalFilename,
                'file_path' => $filePath,
                'mime_type' => $file->getMimeType(),
                'file_size' => $file->getSize(),
                'status' => 'uploaded',
            ]);

        /*
         * Send the document to the processing job.
         */
        ProcessDocument::dispatch($document);

        return response()->json([
            'message' =>
                'Document uploaded successfully and queued for processing.',
            'document' => $document->fresh(),
        ], 201);
    }

    /**
     * Manually request processing for a document.
     */
    public function process(Request $request, Document $document)
    {
        /*
         * Make sure the document belongs to
         * the authenticated user.
         */
        if ($document->user_id !== $request->user()->id) {
            return response()->json([
                'message' =>
                    'You are not authorized to process this document.',
            ], 403);
        }

        /*
         * Prevent unnecessary duplicate processing.
         */
        if ($document->status === 'processing') {
            return response()->json([
                'message' =>
                    'This document is already being processed.',
                'document' => $document,
            ], 409);
        }

        /*
         * Queue the document for processing.
         */
        ProcessDocument::dispatch($document);

        return response()->json([
            'message' => 'Document queued for processing.',
            'document' => $document->fresh(),
        ], 202);
    }

    /**
     * Delete a document belonging to the authenticated user.
     *
     * This removes:
     *
     * 1. The physical PDF from Laravel storage.
     * 2. The document record from the database.
     * 3. Related document chunks through the database
     *    foreign-key cascade.
     */
    public function destroy(Request $request, Document $document)
    {
        /*
         * A user must never be able to delete
         * another user's document.
         */
        if ($document->user_id !== $request->user()->id) {
            return response()->json([
                'message' =>
                    'You are not authorized to delete this document.',
            ], 403);
        }

        /*
         * Avoid deleting a document while its processing
         * job is actively working on it.
         */
        if ($document->status === 'processing') {
            return response()->json([
                'message' =>
                    'This document is currently being processed and cannot be deleted yet.',
            ], 409);
        }

        try {
            $filePath = $document->file_path;

            /*
             * Remove the physical PDF if it still exists.
             *
             * The "local" disk is the same disk used
             * when the document was uploaded.
             */
            if (
                $filePath &&
                Storage::disk('local')->exists($filePath)
            ) {
                $deleted = Storage::disk('local')
                    ->delete($filePath);

                if (! $deleted) {
                    return response()->json([
                        'message' =>
                            'The document file could not be deleted from storage.',
                    ], 500);
                }
            }

            /*
             * Delete the database document.
             *
             * document_chunks uses cascadeOnDelete(),
             * so its chunks are removed automatically.
             */
            $document->delete();

            return response()->json([
                'message' =>
                    'Document deleted successfully.',
                'document_id' => $document->id,
            ]);
        } catch (Throwable $exception) {
            return response()->json([
                'message' =>
                    'Unable to delete the document.',
                'error' => $exception->getMessage(),
            ], 500);
        }
    }
}