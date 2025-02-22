<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\QrCodeController;
use App\Http\Controllers\WebhookController;
use App\Http\Controllers\EvolutionApiController;
use App\Http\Controllers\ProductImportController;


// Route::get('/', function () {
//     return view('welcome');
// });

Route::get('/get-qr-code/{instance}', [QrCodeController::class, 'getQrCode'])->name('get-qr-code');

Route::post('/webhook/evolution-api', [WebhookController::class, 'handleEvolutionApiWebhook'])->name('webhook.evolution-api');

use App\Http\Controllers\InstanceController;

Route::get('/instances/{instance}/qr-code', [InstanceController::class, 'getQrCode']);

Route::get('/evolution/connect/{instance}', [EvolutionApiController::class, 'connectInstance']);

Route::get('/instances/{instance}/status', [InstanceController::class, 'getStatus']);

Route::middleware(['web'])->group(function () {
    Route::get('/products/import', function () {
        if (!auth()->check()) {
            return redirect('/'); // Redirige al panel de Filament si no está autenticado
        }
        return view('products.import');
    })->name('products.import');
});