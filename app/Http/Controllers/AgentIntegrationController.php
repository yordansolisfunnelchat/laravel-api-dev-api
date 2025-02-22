<?php
namespace App\Http\Controllers;

use App\Models\Agent;
// use App\Models\AgentSync; // Necesitaremos crear este modelo
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use App\Models\AgentMapping;  // Agregar esta línea

class AgentIntegrationController extends Controller
{
    public function store(Request $request)
    {
        Log::info('Iniciando proceso de creación de agente', [
            'timestamp' => now(),
            'request_data' => $request->all()
        ]);

        // 1. Primero intentamos crear en Laravel
        try {
            $agent = $this->createAgentInLaravel($request);
        } catch (\Exception $e) {
            Log::error('Falló la creación del agente en Laravel', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Error al crear el agente en Laravel',
                'error' => $e->getMessage()
            ], 500);
        }

        // 2. Si se creó en Laravel, intentamos crear en FastAPI
        try {
            $fastApiResponse = $this->createAgentInFastAPI($agent, $request);
            
            // Si todo fue exitoso
            return response()->json([
                'status' => 'success',
                'message' => 'Agente creado exitosamente en ambos servicios',
                'data' => [
                    'laravel_agent' => $agent,
                    'fastapi_agent' => $fastApiResponse
                ]
            ], 201);

        } catch (\Exception $e) {
            // Si falla FastAPI, mantenemos el registro en Laravel pero marcamos el error
            $this->markAgentSyncFailed($agent, $e->getMessage());
            
            Log::error('Falló la creación del agente en FastAPI', [
                'agent_id' => $agent->id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'status' => 'partial_success',
                'message' => 'Agente creado en Laravel pero falló en FastAPI',
                'error' => $e->getMessage(),
                'laravel_agent' => $agent
            ], 207); // 207 Multi-Status
        }
    }

    private function createAgentInLaravel(Request $request)
    {
        $validatedData = $request->validate([
            'name' => 'required|string|max:255',
            'custom_instructions' => 'nullable|string',
            'activation_mode' => 'required|in:always,keywords',
            'has_waiting_time' => 'required|boolean',
            'keywords' => 'nullable|array',
            'pause_condition' => 'nullable|string',
            'status' => 'required|boolean',
        ]);

        $userId = $request->header('X-User-ID');

        // Verificar duplicados
        $existingAgent = Agent::where('user_id', $userId)
                            ->where('name', $validatedData['name'])
                            ->first();

        if ($existingAgent) {
            throw new \Exception('Ya existe un agente con este nombre');
        }

        $agent = Agent::create([
            'user_id' => $userId,
            'instance_id' => $request->input('instance_id'), // Obtener instance_id del request

            // 'instance_id' => null, // Ajusta según tu lógica
            'name' => $validatedData['name'],
            'custom_instructions' => $validatedData['custom_instructions'],
            'activation_mode' => $validatedData['activation_mode'],
            'keywords' => $validatedData['keywords'],
            'status' => $validatedData['status'],
            'pause_condition' => $validatedData['pause_condition'],
            'has_waiting_time' => $validatedData['has_waiting_time'],
            'sync_status' => 'pending' // Nuevo campo
        ]);

        Log::info('Agente creado exitosamente en Laravel', [
            'agent_id' => $agent->id,
            'timestamp' => now()
        ]);

        return $agent;
    }

    private function createAgentInFastAPI($agent, Request $request)
    {
        $fastapiUrl = config('services.fastapi.base_url') . '/api/v1/agents';
        
        Log::info('Intentando conectar con FastAPI', [
            'url' => $fastapiUrl,
            'config_url' => config('services.fastapi.base_url'),
            'headers' => [
                'X-User-ID' => $request->header('X-User-ID'),
                'X-Shared-Secret' => substr(config('services.fastapi.shared_secret'), 0, 5) . '...',
            ]
        ]);
    
        try {
            // Aumentar el timeout para dar más tiempo a la conexión
            $response = Http::timeout(30)
                ->withHeaders([
                    'X-User-ID' => $request->header('X-User-ID'),
                    'X-Shared-Secret' => config('services.fastapi.shared_secret'),
                ])
                ->post($fastapiUrl, array_merge($request->all(), [
                    'laravel_agent_id' => $agent->id
                ]));
    
            if (!$response->successful()) {
                Log::error('Respuesta fallida de FastAPI', [
                    'status' => $response->status(),
                    'body' => $response->body()
                ]);
                throw new \Exception($response->body());
            }
    
            $fastApiResponse = $response->json();
            
            Log::info('Respuesta exitosa de FastAPI', [
                'agent_id' => $agent->id,
                'fastapi_response' => $fastApiResponse
            ]);
    
            // Crear el mapeo entre IDs
            AgentMapping::create([
                'laravel_agent_id' => $agent->id,
                'fastapi_agent_id' => $fastApiResponse['id'],
                'user_id' => $request->header('X-User-ID')
            ]);
    
            // Actualizar el estado de sincronización
            $agent->update(['sync_status' => 'synced']);
    
            return $fastApiResponse;
    
        } catch (\Exception $e) {
            Log::error('Error al conectar con FastAPI', [
                'error' => $e->getMessage(),
                'url' => $fastapiUrl,
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }  
    // private function createAgentInFastAPI($agent, Request $request)
    // {
    //     $fastapiUrl = config('services.fastapi.base_url') . '/api/v1/agents';
        
    //         // Agregar logs de diagnóstico
    //         Log::info('Configuración FastAPI', [
    //             'url' => $fastapiUrl,
    //             'base_url' => config('services.fastapi.base_url')
    //         ]);
    //         // Intentar hacer ping al host
    //         exec("ping -c 1 host.docker.internal", $output, $returnVar);
    //         Log::info('Prueba de ping', [
    //             'output' => $output,
    //             'return_var' => $returnVar
    //         ]);


    //     $response = Http::withHeaders([
    //         'X-User-ID' => $request->header('X-User-ID'),
    //         'X-Shared-Secret' => config('services.fastapi.shared_secret'),
    //     ])->post($fastapiUrl, array_merge($request->all(), [
    //         'laravel_agent_id' => $agent->id
    //     ]));
    
    //     if (!$response->successful()) {
    //         throw new \Exception('Error en FastAPI: ' . $response->body());
    //     }
    
    //     $fastApiResponse = $response->json();
    
    //     // Crear el mapeo entre IDs
    //     AgentMapping::create([
    //         'laravel_agent_id' => $agent->id,
    //         'fastapi_agent_id' => $fastApiResponse['id'],
    //         'user_id' => $request->header('X-User-ID')
    //     ]);
    
    //     // Actualizar el estado de sincronización
    //     $agent->update(['sync_status' => 'synced']);
    
    //     Log::info('Agente creado exitosamente en FastAPI', [
    //         'agent_id' => $agent->id,
    //         'fastapi_agent_id' => $fastApiResponse['id'],
    //         'fastapi_response' => $fastApiResponse,
    //         'timestamp' => now()
    //     ]);
    
    //     return $fastApiResponse;
    // }





    private function markAgentSyncFailed($agent, $error)
    {
        $agent->update([
            'sync_status' => 'failed',
            'sync_error' => $error
        ]);
    }

    // Endpoint para verificar el estado de sincronización
    public function checkSyncStatus($agentId)
    {
        $agent = Agent::findOrFail($agentId);
        
        return response()->json([
            'agent_id' => $agent->id,
            'sync_status' => $agent->sync_status,
            'sync_error' => $agent->sync_error,
            'last_sync_attempt' => $agent->updated_at
        ]);
    }

    // Endpoint para reintentar la sincronización
    public function retrySyncWithFastAPI($agentId)
    {
        $agent = Agent::findOrFail($agentId);
        
        if ($agent->sync_status === 'synced') {
            return response()->json([
                'message' => 'El agente ya está sincronizado'
            ]);
        }

        try {
            $fastApiResponse = $this->createAgentInFastAPI($agent, request());
            
            return response()->json([
                'status' => 'success',
                'message' => 'Sincronización exitosa',
                'data' => $fastApiResponse
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Falló el reintento de sincronización',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // Endpoint para listar agentes sincronizados






}
