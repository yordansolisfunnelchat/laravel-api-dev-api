<?php
# Services\ConnectionService.php
namespace App\Services;

use App\Models\Instance;
use Illuminate\Support\Facades\Log;

class ConnectionService
{
    public function handleConnectionUpdate(Instance $instance, $data)
    {
        Log::info('Handling Connection Update', ['data' => $data]);
        
        if ($instance->status === 'inactive') {
            Log::info('Instance is inactive, skipping connection update', ['instance' => $instance->name]);
            return;
        }

        $state = $data['state'] ?? $data['data']['state'] ?? 'unknown';
        $sender = $data['sender'] ?? $data['data']['sender'] ?? null;
        $phoneNumber = null;

        if ($sender) {
            $phoneNumber = explode('@', $sender)[0];
        }

        if ($state === 'open') {
            $instance->status = 'connected';
            $instance->qr_code = null;
            if ($phoneNumber) {
                $instance->phone_number = $phoneNumber;
            }
            $instance->save();

            Log::info('Connection status updated to connected', ['instance' => $instance->name, 'phone_number' => $phoneNumber]);
        } elseif ($state === 'close') {
            $instance->status = 'disconnected';
            $instance->save();

            Log::info('Connection status updated to disconnected', ['instance' => $instance->name]);
        } else {
            $instance->status = $state;
            $instance->save();

            Log::info('Connection status updated', ['instance' => $instance->name, 'status' => $state]);
        }
    }
}
