<?php

namespace App\Http\Controllers;

use App\Models\Instance;
use App\Services\EvolutionApiService;
use Illuminate\Http\JsonResponse;

class QrCodeController extends Controller
{
    public function getQrCode(Instance $instance, EvolutionApiService $evolutionApiService): JsonResponse
    {
        $qrCode = $evolutionApiService->getQrCode($instance);
        $status = $evolutionApiService->checkInstanceStatus($instance);

        return response()->json([
            'qrCode' => $qrCode,
            'status' => $status,
        ]);
    }
}