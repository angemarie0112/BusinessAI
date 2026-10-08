<?php

use App\Http\Controllers\AIController;
use App\Http\Controllers\AIConversationController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\DocumentController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;


/*
|--------------------------------------------------------------------------
| Health
|--------------------------------------------------------------------------
*/

Route::get('/health', function () {
    return response()->json([
        'status' => 'ok',
        'message' => 'BusinessAI API is running',
    ]);
});


/*
|--------------------------------------------------------------------------
| Public authentication routes
|--------------------------------------------------------------------------
*/

Route::post(
    '/register',
    [AuthController::class, 'register']
);

Route::post(
    '/login',
    [AuthController::class, 'login']
);


/*
|--------------------------------------------------------------------------
| Protected routes
|--------------------------------------------------------------------------
*/

Route::middleware('auth:sanctum')->group(function () {

    /*
    |--------------------------------------------------------------------------
    | Authenticated user
    |--------------------------------------------------------------------------
    */

    Route::get('/user', function (Request $request) {
        return $request->user();
    });


    /*
    |--------------------------------------------------------------------------
    | Documents
    |--------------------------------------------------------------------------
    */

    // Get all documents belonging to the authenticated user
    Route::get(
        '/documents',
        [DocumentController::class, 'index']
    );

    // Upload a new document
    Route::post(
        '/documents',
        [DocumentController::class, 'store']
    );

    // Delete a document
    Route::delete(
        '/documents/{document}',
        [DocumentController::class, 'destroy']
    );

    // Manually process a document
    Route::post(
        '/documents/{document}/process',
        [DocumentController::class, 'process']
    );


    /*
    |--------------------------------------------------------------------------
    | AI Conversations
    |--------------------------------------------------------------------------
    */

    // Get all conversations belonging to the authenticated user
    Route::get(
        '/conversations',
        [AIConversationController::class, 'index']
    );

    // Create a new conversation for a specific document
    Route::post(
        '/documents/{document}/conversations',
        [AIConversationController::class, 'store']
    );

    // Get one conversation together with its messages
    Route::get(
        '/conversations/{conversation}',
        [AIConversationController::class, 'show']
    );

    Route::delete(
        '/conversations/{conversation}',
        [AIConversationController::class, 'destroy']
    );


    /*
    |--------------------------------------------------------------------------
    | AI
    |--------------------------------------------------------------------------
    */

    Route::get(
        '/ai/health',
        [AIController::class, 'health']
    );

    // Chunk a processed document
    Route::post(
        '/documents/{document}/chunk',
        [AIController::class, 'chunkDocument']
    );

    // Embed one document chunk
    Route::post(
        '/documents/{document}/chunks/{chunkIndex}/embed',
        [AIController::class, 'embedChunk']
    );

    // Embed all chunks belonging to a document
    Route::post(
        '/documents/{document}/embed',
        [AIController::class, 'embedDocument']
    );

    // Semantic document search
    Route::post(
        '/documents/{document}/search',
        [AIController::class, 'searchDocument']
    );

    // Conversational RAG
    Route::post(
        '/documents/{document}/ask',
        [AIController::class, 'askDocument']
    );


    /*
    |--------------------------------------------------------------------------
    | Authentication
    |--------------------------------------------------------------------------
    */

    Route::post(
        '/logout',
        [AuthController::class, 'logout']
    );
});