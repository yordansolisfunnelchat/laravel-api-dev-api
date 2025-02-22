<?php

namespace App\Services;

use App\Models\Agent;
use App\Models\TestInstance;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AgentIntegrationService
{
    public function getOrCreateAgent($userId)
    {
        // 1. Obtener el agente de FastAPI
        $fastApiAgent = $this->getFastApiAgent($userId, );
        
        // 2. Obtener o validar la instancia
        $instance = $this->getOrValidateInstance($userId, $fastApiAgent['instance_id'] ?? null);
        
        // 3. Crear o actualizar el agente local
        return $this->syncLocalAgent($fastApiAgent, $userId, $instance->id);
    }

    private function getFastApiAgent($userId)
    {
        $response = Http::withHeaders([
            'X-User-ID' => (string)$userId,
            'X-Shared-Secret' => config('services.fastapi.shared_secret'),
            'X-Instance-ID' => $instanceIdentifier // Agregamos el identificador de instancia
        ])->get(config('services.fastapi.base_url') . '/api/v1/agents');

        if (!$response->successful()) {
            Log::error('Error getting agents from FastAPI', [
                'response' => $response->json(),
                'instanceIdentifier' => $instanceIdentifier
            ]);
            throw new \Exception('No se pudo obtener el agente');
        }

        $agents = $response->json()['data'];
        $agent = collect($agents)
            ->where('status', true)
            ->first();

        if (!$agent) {
            throw new \Exception('No hay agentes activos disponibles');
        }

        return $agent;
    }

    private function getOrValidateInstance($userId, $externalInstanceId = null)
    {

        log::info('🔍 Buscando instancia activa para el usuario', ['user_id' => $userId]);
        // Buscar una instancia activa para el usuario
        $instance = TestInstance::where('user_id', $userId)
            ->where('status', 'active')
            ->first();

        if (!$instance) {
            throw new \Exception('No hay instancia activa disponible para este usuario');
        }

        return $instance;
    }

    private function syncLocalAgent($fastApiAgent, $userId, $instanceId)
    {
        return Agent::updateOrCreate(
            ['id' => $fastApiAgent['id']],
            [
                'user_id' => $userId,
                'instance_id' => $instanceId,
                'name' => $fastApiAgent['name'],
                'custom_instructions' => $fastApiAgent['custom_instructions'] ?? null,
                'activation_mode' => $fastApiAgent['activation_mode'] ?? 'always',
                'keywords' => $fastApiAgent['keywords'] ?? null,
                'pause_condition' => $fastApiAgent['pause_condition'] ?? null,
                'status' => true
            ]
        );
    }
}