<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use App\Models\Instance;
use App\Models\Configuration;
use Illuminate\Support\Facades\Log;

class EvolutionApiService
{
    protected $baseUrl;
    protected $apiKey;

    public function __construct()
    {
        $this->baseUrl = rtrim(Configuration::get('evolution_api_base_url'), '/');
        $this->apiKey = Configuration::get('evolution_api_key');
    }

    public function createInstance(Instance $instance): bool
    {
        try {
            $url = "{$this->baseUrl}/instance/create";
            $payload = [
                'instanceName' => $instance->name,
                'webhook' => route('webhook.evolution-api'),
                'webhookByEvents' => true,
                'events' => ['qrcode', 'connection.update', 'messages.upsert'],
            ];

            $response = Http::withHeaders([
                'apikey' => $this->apiKey,
            ])->post($url, $payload);

            if ($response->successful()) {
                $instance->status = 'created';
                $instance->save();
                Log::info('Instance created successfully', ['instance_id' => $instance->id]);
                return true;
            }

            Log::error('Failed to create instance', [
                'instance_id' => $instance->id,
                'response' => $response->body(),
            ]);
            return false;
        } catch (\Exception $e) {
            Log::error('Exception while creating instance', [
                'instance_id' => $instance->id,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    public function connectInstance(Instance $instance): bool
    {
        try {
            $url = "{$this->baseUrl}/instance/connect/{$instance->name}";

            $response = Http::withHeaders([
                'apikey' => $this->apiKey,
            ])->get($url);

            if ($response->successful()) {
                $qrCodeData = $response->json();
                $instance->status = 'qr_ready';
                $instance->qr_code = $qrCodeData['base64'] ?? null;
                $instance->save();
                Log::info('Instance connected and QR code received', ['instance_id' => $instance->id]);
                return true;
            }

            Log::error('Failed to connect instance', [
                'instance_id' => $instance->id,
                'response' => $response->body(),
            ]);
            return false;
        } catch (\Exception $e) {
            Log::error('Exception while connecting instance', [
                'instance_id' => $instance->id,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    public function getQrCode(Instance $instance): ?string
    {
        $response = Http::withHeaders([
            'apikey' => $this->apiKey,
        ])->get($this->baseUrl . '/instance/qrcode/' . $instance->name);

        if ($response->successful()) {
            return $response->json('qrcode');
        }

        return null;
    }

    public function checkInstanceStatus(Instance $instance): string
    {
        $response = Http::withHeaders([
            'apikey' => $this->apiKey,
        ])->get($this->baseUrl . '/instance/status/' . $instance->name);

        if ($response->successful()) {
            $status = $response->json('status');
            $instance->status = $status;
            $instance->save();
            return $status;
        }

        return 'unknown';
    }


    public function disconnectInstance(Instance $instance): bool
    {
        try {
            $url = "{$this->baseUrl}/instance/logout/{$instance->name}";

            $response = Http::withHeaders([
                'apikey' => $this->apiKey,
            ])->delete($url);

            if ($response->successful()) {
                Log::info('Instance disconnected successfully', ['instance_id' => $instance->id]);
                return true;
            }

            Log::error('Failed to disconnect instance', [
                'instance_id' => $instance->id,
                'response' => $response->body(),
            ]);
            return false;
        } catch (\Exception $e) {
            Log::error('Exception while disconnecting instance', [
                'instance_id' => $instance->id,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    public function disconnectAndDeactivateInstance(Instance $instance): bool
    {
        try {
            $disconnected = $this->disconnectInstance($instance);

            // Si la desconexión falla, asumimos que ya está desconectada
            if (!$disconnected) {
                Log::info('Instance might already be disconnected', ['instance_id' => $instance->id]);
            }

            // Actualizamos el estado a 'inactive' independientemente del resultado de la desconexión
            $instance->status = 'inactive';
            $instance->save();
            Log::info('Instance deactivated', ['instance_id' => $instance->id]);
            return true;
        } catch (\Exception $e) {
            Log::error('Exception while deactivating instance', [
                'instance_id' => $instance->id,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    public function deleteInstance(Instance $instance): bool
    {
        try {
            $url = "{$this->baseUrl}/instance/delete/{$instance->name}";

            $response = Http::withHeaders([
                'apikey' => $this->apiKey,
            ])->delete($url);

            if ($response->successful()) {
                Log::info('Instance deleted successfully', ['instance_id' => $instance->id]);
                return true;
            }

            Log::error('Failed to delete instance', [
                'instance_id' => $instance->id,
                'response' => $response->body(),
            ]);
            return false;
        } catch (\Exception $e) {
            Log::error('Exception while deleting instance', [
                'instance_id' => $instance->id,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }
}
