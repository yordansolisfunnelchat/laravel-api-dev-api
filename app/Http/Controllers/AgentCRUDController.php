<?php

namespace App\Http\Controllers;

use App\Models\Agent;
use App\Models\AgentMapping;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class AgentCRUDController extends Controller
{
    public function update(Request $request, $id)
    {
        Log::info('Iniciando actualización de agente', [
            'agent_id' => $id,
            'request_data' => $request->all()
        ]);

        // Validar la solicitud
        $validatedData = $request->validate([
            'name' => 'required|string|max:255',
            'custom_instructions' => 'nullable|string',
            'activation_mode' => 'required|in:always,keywords',
            'has_waiting_time' => 'required|boolean',
            'keywords' => 'nullable|array',
            'pause_condition' => 'nullable|string',
            'status' => 'required|boolean',
        ]);

        DB::beginTransaction();
        try {
            // 1. Actualizar en Laravel
            $agent = Agent::where('id', $id)
                         ->where('user_id', $request->header('X-User-ID'))
                         ->firstOrFail();

            $agent->update($validatedData);

            // 2. Actualizar en FastAPI
            $mapping = AgentMapping::where('laravel_agent_id', $id)->firstOrFail();
            $fastApiResponse = $this->updateInFastAPI(
                $mapping->fastapi_agent_id,
                $validatedData,
                $request->header('X-User-ID')
            );

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'Agente actualizado exitosamente',
                'data' => [
                    'laravel_agent' => $agent,
                    'fastapi_response' => $fastApiResponse
                ]
            ]);

        } catch (\Exception $e) {
            DB::rollback();
            Log::error('Error actualizando agente', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Error al actualizar el agente',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function destroy($id)
    {
        Log::info('Iniciando eliminación de agente', [
            'agent_id' => $id,
            'user_id' => request()->header('X-User-ID')
        ]);

        DB::beginTransaction();
        try {
            // 1. Verificar y obtener el agente en Laravel
            $agent = Agent::where('id', $id)
                         ->where('user_id', request()->header('X-User-ID'))
                         ->firstOrFail();

            // 2. Obtener el ID de FastAPI
            $mapping = AgentMapping::where('laravel_agent_id', $id)->first();
            
            if ($mapping) {
                Log::info('Eliminando agente en FastAPI', [
                    'laravel_agent_id' => $id,
                    'fastapi_agent_id' => $mapping->fastapi_agent_id
                ]);

                try {
                    // 3. Eliminar en FastAPI
                    $fastApiResponse = $this->deleteInFastAPI(
                        $mapping->fastapi_agent_id,
                        request()->header('X-User-ID')
                    );

                    Log::info('Respuesta de FastAPI', [
                        'response' => $fastApiResponse
                    ]);
                } catch (\Exception $e) {
                    Log::error('Error al eliminar en FastAPI', [
                        'error' => $e->getMessage()
                    ]);
                    
                    // Si el error es 404, asumimos que ya no existe en FastAPI
                    if (!str_contains($e->getMessage(), 'Not Found')) {
                        throw $e;
                    }
                }
                
                // 4. Eliminar el mapeo PRIMERO
                $mapping->delete();
            }

            // 5. DESPUÉS eliminar en Laravel
            $agent->delete();

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'Agente eliminado exitosamente',
                'agent_id' => $id
            ]);

        } catch (\Exception $e) {
            DB::rollback();
            Log::error('Error eliminando agente', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Error al eliminar el agente',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    

    private function updateInFastAPI($fastApiAgentId, $data, $userId)
    {
        $fastApiUrl = config('services.fastapi.base_url') . "/api/v1/update-agents/{$fastApiAgentId}";
        
        $response = Http::withHeaders([
            'X-User-ID' => $userId,
            'X-Shared-Secret' => config('services.fastapi.shared_secret'),
        ])->put($fastApiUrl, $data);

        if (!$response->successful()) {
            throw new \Exception('Error en FastAPI: ' . $response->body());
        }

        return $response->json();
    }

    private function deleteInFastAPI($fastApiAgentId, $userId)
    {
        $fastApiUrl = config('services.fastapi.base_url') . "/api/v1/delete-agents/{$fastApiAgentId}";
        
        Log::info('Realizando petición DELETE a FastAPI', [
            'url' => $fastApiUrl,
            'fastapi_agent_id' => $fastApiAgentId,
            'user_id' => $userId
        ]);

        $response = Http::withHeaders([
            'X-User-ID' => $userId,
            'X-Shared-Secret' => config('services.fastapi.shared_secret'),
            'Accept' => 'application/json',
            'Content-Type' => 'application/json'
        ])->delete($fastApiUrl);

        if (!$response->successful() && !$response->status() === 404) {
            Log::error('Error en respuesta de FastAPI', [
                'status' => $response->status(),
                'body' => $response->body()
            ]);
            
            throw new \Exception('Error en FastAPI: ' . $response->body());
        }

        return $response->json();
    }

}