<?php

use App\Http\Controllers\Api\BridgeController;
use App\Http\Controllers\Api\PaymentWebhookController;
use Illuminate\Support\Facades\Route;

Route::post('/payments/{provider}/webhook', PaymentWebhookController::class)
    ->whereIn('provider', ['stripe', 'paytr', 'sandbox'])->middleware('throttle:webhooks')->name('payments.webhook');
Route::prefix('bridge/v1')->middleware(['throttle:bridge', 'bridge'])->group(function () {
    Route::post('/poll', [BridgeController::class, 'poll']);
    Route::post('/ack', [BridgeController::class, 'ack']);
    Route::post('/link', [BridgeController::class, 'link']);
    Route::post('/stats', [BridgeController::class, 'stats']);
    Route::post('/punishments', [BridgeController::class, 'punishments']);
});
