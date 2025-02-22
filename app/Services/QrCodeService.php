<?php
# QrCodeSerce.php 
namespace App\Services;

use App\Models\Instance;
use Illuminate\Support\Facades\Log;

class QrCodeService
{
    public function handleQrCode(Instance $instance, $data)
    {
        Log::info('Handling QR Code', ['data' => $data]);
        $qrCodeData = $data['qrcode'] ?? $data['data']['qrcode'] ?? [];
        $instance->qr_code = $qrCodeData['base64'] ?? null;
        $instance->status = 'qr_ready';
        $instance->save();

        Log::info('QR Code updated', ['instance' => $instance->name, 'qr_code' => $instance->qr_code ? 'present' : 'null']);
    }
}