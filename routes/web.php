<?php

use App\Http\Controllers\ConversationController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\MessageController;
use App\Http\Controllers\TagController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'Welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', [DashboardController::class, 'index'])->name('dashboard');

    Route::get('conversations', [ConversationController::class, 'index'])->name('conversations.index');
    Route::post('conversations', [ConversationController::class, 'store'])->name('conversations.store');
    Route::get('conversations/{conversation}', [ConversationController::class, 'show'])->name('conversations.show');
    Route::post('conversations/{conversation}/messages', [MessageController::class, 'store'])->name('conversations.messages.store');
    Route::post('conversations/{conversation}/messages/stream', [MessageController::class, 'stream'])->name('conversations.messages.stream');
    Route::post('conversations/{conversation}/messages/{message}/regenerate', [MessageController::class, 'regenerate'])->name('conversations.messages.regenerate');
    Route::patch('conversations/{conversation}/messages/{message}', [MessageController::class, 'updateMessage'])->name('conversations.messages.update');

    Route::post('tags', [TagController::class, 'store'])->name('tags.store');
    Route::post('conversations/{conversation}/tags/{tag}', [ConversationController::class, 'attachTag'])->name('conversations.tags.attach');
    Route::delete('conversations/{conversation}/tags/{tag}', [ConversationController::class, 'detachTag'])->name('conversations.tags.detach');
});

require __DIR__.'/settings.php';
