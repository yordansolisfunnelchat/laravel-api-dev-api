<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\HotmartWebhookController;
// use App\Http\Controllers\StripeWebhookController;
use App\Http\Controllers\UserController;

use App\Http\Controllers\InstanceController;
use App\Http\Controllers\MessageController;
use App\Http\Controllers\ProductImportController;

use App\Http\Controllers\ZapierAuthController;  
use App\Http\Controllers\AuthController;  // ✅
use App\Http\Controllers\WebhookController; //🐍

use App\Http\Controllers\AgentIntegrationController; //✅
use App\Http\Controllers\AgentCRUDController; //✅
use App\Http\Controllers\AgentMappingController; //✅
use App\Http\Controllers\TestInstanceController; //🐍
use App\Http\Controllers\WhatsAppInstanceController;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

// Evolution API Webhook routes
Route::prefix('webhook/evolution-api')->group(function () {
    Route::post('/application-startup', [WebhookController::class, 'handleEvolutionApiWebhook'])->name('webhook.evolution-api.application-startup');
    Route::post('/qrcode-updated', [WebhookController::class, 'handleEvolutionApiWebhook'])->name('webhook.evolution-api.qrcode-updated');
    Route::post('/connection-update', [WebhookController::class, 'handleEvolutionApiWebhook'])->name('webhook.evolution-api.connection-update');
    Route::post('/messages-set', [WebhookController::class, 'handleEvolutionApiWebhook'])->name('webhook.evolution-api.messages-set');
    Route::post('/messages-upsert', [WebhookController::class, 'handleEvolutionApiWebhook'])->name('webhook.evolution-api.messages-upsert');
    Route::post('/messages-update', [WebhookController::class, 'handleEvolutionApiWebhook'])->name('webhook.evolution-api.messages-update');
    Route::post('/messages-delete', [WebhookController::class, 'handleEvolutionApiWebhook'])->name('webhook.evolution-api.messages-delete');
    Route::post('/send-message', [WebhookController::class, 'handleEvolutionApiWebhook'])->name('webhook.evolution-api.send-message');
    Route::post('/contacts-set', [WebhookController::class, 'handleEvolutionApiWebhook'])->name('webhook.evolution-api.contacts-set');
    Route::post('/contacts-upsert', [WebhookController::class, 'handleEvolutionApiWebhook'])->name('webhook.evolution-api.contacts-upsert');
    Route::post('/contacts-update', [WebhookController::class, 'handleEvolutionApiWebhook'])->name('webhook.evolution-api.contacts-update');
    Route::post('/presence-update', [WebhookController::class, 'handleEvolutionApiWebhook'])->name('webhook.evolution-api.presence-update');
    Route::post('/chats-set', [WebhookController::class, 'handleEvolutionApiWebhook'])->name('webhook.evolution-api.chats-set');
    Route::post('/chats-update', [WebhookController::class, 'handleEvolutionApiWebhook'])->name('webhook.evolution-api.chats-update');
    Route::post('/chats-upsert', [WebhookController::class, 'handleEvolutionApiWebhook'])->name('webhook.evolution-api.chats-upsert');
    Route::post('/chats-delete', [WebhookController::class, 'handleEvolutionApiWebhook'])->name('webhook.evolution-api.chats-delete');
    Route::post('/groups-upsert', [WebhookController::class, 'handleEvolutionApiWebhook'])->name('webhook.evolution-api.groups-upsert');
    Route::post('/groups-update', [WebhookController::class, 'handleEvolutionApiWebhook'])->name('webhook.evolution-api.groups-update');
    Route::post('/group-participants-update', [WebhookController::class, 'handleEvolutionApiWebhook'])->name('webhook.evolution-api.group-participants-update');
    Route::post('/new-jwt', [WebhookController::class, 'handleEvolutionApiWebhook'])->name('webhook.evolution-api.new-jwt');
});

Route::post('/webhook/hotmart', [HotmartWebhookController::class, 'handle']);


// Route::post('stripe/webhook', [App\Http\Controllers\StripeWebhookController::class, 'handle']);


// use App\Http\Controllers\AuthController;
// use App\Http\Controllers\ZapierAuthController;

// Rutas públicas de autenticación ✅
Route::prefix('auth')->group(function () {
    Route::post('/login', [AuthController::class, 'login']); # ✅
    Route::post('/register', [AuthController::class, 'register']); 
});

// Rutas protegidas
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/auth/logout', [AuthController::class, 'logout']);  # ✅
    Route::get('/auth/user', [AuthController::class, 'user']); # ✅
    Route::post('/users/{user}/deactivate', [UserController::class, 'deactivate'])
        ->name('users.deactivate');
});




Route::middleware('auth:sanctum')->group(function () {
    // Rutas para instances
    Route::get('/instances', [InstanceController::class, 'index']); # ✅
    Route::get('/instances/{instance}', [InstanceController::class, 'show']); 
    
   // Rutas para mensajes
    Route::post('/messages/text', [MessageController::class, 'sendText']);
    Route::post('/messages/media', [MessageController::class, 'sendMedia']);
    Route::post('/messages/audio', [MessageController::class, 'sendAudio']);
});

Route::middleware(['web'])->group(function () {
    Route::post('/products/import/preview', [ProductImportController::class, 'preview']);
    Route::post('/products/import', [ProductImportController::class, 'import']);
});


// Rutas para test instances  ✅
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/test-instances', [TestInstanceController::class, 'store']);
    Route::get('/test-instances', [TestInstanceController::class, 'index']);
});



// nuevas rutas para recibir las respuesta de python  ✅
Route::post('/python-callback', [WebhookController::class, 'handlePythonCallback']);



// Ruta para crear agentes  ✅
Route::middleware('auth:sanctum')->group(function () {
    Route::prefix('v1/agents')->group(function () {
        Route::post('/', [AgentIntegrationController::class, 'store']);# funciona 
        Route::put('/{id}/instance', [AgentMappingController::class, 'update']);
        Route::get('/{agent}/sync-status', [AgentIntegrationController::class, 'checkSyncStatus']);
        Route::post('/{agent}/retry-sync', [AgentIntegrationController::class, 'retrySyncWithFastAPI']);
        Route::put('/{id}', [AgentCRUDController::class, 'update']);
        Route::delete('/{id}', [AgentCRUDController::class, 'destroy']);
    });
});





Route::middleware('auth:sanctum')->group(function () {
    Route::prefix('whatsapp')->group(function () {
        Route::get('/instances', [WhatsAppInstanceController::class, 'index']);
        Route::post('/instances', [WhatsAppInstanceController::class, 'store']);
        Route::get('/instances/{instanceName}', [WhatsAppInstanceController::class, 'show']);
        Route::put('/instances/{instanceName}', [WhatsAppInstanceController::class, 'update']);
        Route::delete('/instances/{instanceName}', [WhatsAppInstanceController::class, 'destroy']);
    });
});