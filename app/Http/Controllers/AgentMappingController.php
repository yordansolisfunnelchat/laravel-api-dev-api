<?php

namespace App\Http\Controllers;

use App\Models\Agent;
use App\Models\AgentMapping;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class AgentMappingController extends Controller
{
    public function update(Request $request, $laravelAgentId)
    {
        // Validar la solicitud
        $request->validate([
            'instance_id' => 'required|string'
        ]);

        Log::info('Iniciando actualización de instancia de agente', [
            'laravel_agent_id' => $laravelAgentId,
            'request_data' => $request->all()
        ]);

        DB::beginTransaction();
        
        try {
            // 1. Actualizar en Laravel
            $agent = Agent::where('id', $laravelAgentId)
                         ->where('user_id', $request->header('X-User-ID'))
                         ->firstOrFail();
            
            $agent->instance_id = $request->input('instance_id');
            $agent->save();

            // 2. Obtener el ID de FastAPI
            $mapping = AgentMapping::where('laravel_agent_id', $laravelAgentId)
                                 ->firstOrFail();

            // 3. Actualizar en FastAPI
            $fastApiResponse = $this->updateInFastAPI(
                $mapping->fastapi_agent_id,
                $request->input('instance_id'),
                $request->header('X-User-ID')
            );

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'Instancia de agente actualizada exitosamente',
                'data' => [
                    'laravel_agent' => $agent,
                    'fastapi_response' => $fastApiResponse
                ]
            ]);

        } catch (\Exception $e) {
            DB::rollback();
            Log::error('Error actualizando instancia de agente', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Error al actualizar la instancia del agente',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    private function updateInFastAPI($fastApiAgentId, $instanceId, $userId)
    {
        $fastApiUrl = config('services.fastapi.base_url') . "/api/v1/edit-agents/{$fastApiAgentId}/instance";
        
        $response = Http::withHeaders([
            'X-User-ID' => $userId,
            'X-Shared-Secret' => config('services.fastapi.shared_secret'),
            'Content-Type' => 'application/json'
        ])->put($fastApiUrl, [
            'instance_id' => $instanceId
        ]);

        if (!$response->successful()) {
            throw new \Exception('Error en FastAPI: ' . $response->body());
        }

        return $response->json();
    }
}