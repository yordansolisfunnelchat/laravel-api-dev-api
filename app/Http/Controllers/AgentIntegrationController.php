<?php
namespace App\Http\Controllers;


use App\Models\Agent;
use App\Models\AgentMapping;
use App\Jobs\SyncAgentWithFastAPI; // Nuevo job que crearemos
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller; // Esta es la línea que necesitas añadir

class AgentIntegrationController extends Controller
{
    /**
     * Crear un nuevo agente con manejo mejorado de errores y comunicación asíncrona
     */
    public function store(Request $request)
    {
        Log::info('Iniciando proceso de creación de agente', [
            'timestamp' => now(),
            'request_data' => $request->except(['custom_instructions']) // Evitamos loggear instrucciones completas
        ]);

        // Comenzamos transacción para asegurar consistencia
        DB::beginTransaction();
        
        try {
            // 1. Crear agente en Laravel
            $agent = $this->createAgentInLaravel($request);
            
            // 2. Intentar creación síncrona en FastAPI con timeout extendido
            try {
                // Configuramos un timeout más alto para esta operación específica
                $fastApiResponse = $this->createAgentInFastAPI($agent, $request, 15); // 15 segundos de timeout
                
                // Si llegamos aquí, la creación en FastAPI fue exitosa
                $agent->update(['sync_status' => 'synced']);
                
                DB::commit();
                
                return response()->json([
                    'status' => 'success',
                    'message' => 'Agente creado exitosamente en ambos servicios',
                    'data' => [
                        'laravel_agent' => $agent,
                        'fastapi_agent' => $fastApiResponse
                    ]
                ], 201);
                
            } catch (\Exception $e) {
                // Si hay un timeout o error de conexión, usamos enfoque asíncrono
                if (
                    str_contains($e->getMessage(), 'timed out') || 
                    str_contains($e->getMessage(), 'Connection timed out') ||
                    str_contains($e->getMessage(), 'Connection refused') ||
                    str_contains($e->getMessage(), "Can't connect to MySQL")
                ) {
                    // Marcamos para reintento asíncrono
                    $agent->update([
                        'sync_status' => 'pending_async',
                        'sync_attempts' => 0,
                        'sync_error' => null
                    ]);
                    
                    // Programar job para intentar sincronización de forma asíncrona
                    SyncAgentWithFastAPI::dispatch($agent, $request->all(), $request->header('X-User-ID'))
                        ->delay(now()->addSeconds(5)); // Reintento en 5 segundos
                    
                    DB::commit();
                    
                    return response()->json([
                        'status' => 'accepted',
                        'message' => 'Agente creado en Laravel. Sincronización con FastAPI programada en segundo plano.',
                        'agent_id' => $agent->id,
                        'check_status_url' => route('agents.sync.status', $agent->id)
                    ], 202); // 202 Accepted
                }
                
                // Para otros errores, marcamos como fallido
                $agent->update([
                    'sync_status' => 'failed',
                    'sync_error' => $e->getMessage()
                ]);
                
                DB::commit();
                
                Log::error('Falló la creación del agente en FastAPI', [
                    'agent_id' => $agent->id,
                    'error' => $e->getMessage()
                ]);
                
                return response()->json([
                    'status' => 'partial_success',
                    'message' => 'Agente creado en Laravel pero falló en FastAPI',
                    'error' => $e->getMessage(),
                    'laravel_agent' => $agent,
                    'retry_url' => route('agents.sync.retry', $agent->id)
                ], 207); // 207 Multi-Status
            }
            
        } catch (\Exception $e) {
            DB::rollback();
            
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
            'instance_id' => $request->input('instance_id', null),
            'name' => $validatedData['name'],
            'custom_instructions' => $validatedData['custom_instructions'],
            'activation_mode' => $validatedData['activation_mode'],
            'keywords' => $validatedData['keywords'],
            'status' => $validatedData['status'],
            'pause_condition' => $validatedData['pause_condition'],
            'has_waiting_time' => $validatedData['has_waiting_time'],
            'sync_status' => 'pending',
            'sync_attempts' => 0,
            'sync_error' => null
        ]);

        Log::info('Agente creado exitosamente en Laravel', [
            'agent_id' => $agent->id,
            'timestamp' => now()
        ]);

        return $agent;
    }

    private function createAgentInFastAPI($agent, Request $request, $timeout = 30)
    {
        $fastapiUrl = config('services.fastapi.base_url') . '/api/v1/agents';
        
        Log::info('Intentando conectar con FastAPI', [
            'url' => $fastapiUrl,
            'agent_id' => $agent->id,
            'timeout' => $timeout
        ]);
    
        try {
            // Usar el timeout específico para esta solicitud
            $response = Http::timeout($timeout)
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
                'fastapi_agent_id' => $fastApiResponse['id'] ?? 'no_id'
            ]);
    
            // Crear el mapeo entre IDs
            AgentMapping::create([
                'laravel_agent_id' => $agent->id,
                'fastapi_agent_id' => $fastApiResponse['id'],
                'user_id' => $request->header('X-User-ID')
            ]);
    
            return $fastApiResponse;
    
        } catch (\Exception $e) {
            Log::error('Error al conectar con FastAPI', [
                'error' => $e->getMessage(),
                'url' => $fastapiUrl
            ]);
            throw $e;
        }
    }

    // Endpoint para verificar el estado de sincronización
    public function checkSyncStatus($agentId)
    {
        $agent = Agent::findOrFail($agentId);
        
        // Verificar si hay un mapeo creado
        $mapping = AgentMapping::where('laravel_agent_id', $agentId)->first();
        
        return response()->json([
            'agent_id' => $agent->id,
            'sync_status' => $agent->sync_status,
            'sync_error' => $agent->sync_error,
            'sync_attempts' => $agent->sync_attempts,
            'fastapi_agent_id' => $mapping ? $mapping->fastapi_agent_id : null,
            'last_sync_attempt' => $agent->updated_at
        ]);
    }

    // Endpoint para reintentar la sincronización manualmente
    public function retrySyncWithFastAPI($agentId, Request $request)
    {
        $agent = Agent::findOrFail($agentId);
        
        if ($agent->sync_status === 'synced') {
            return response()->json([
                'message' => 'El agente ya está sincronizado'
            ]);
        }

        // Actualizar el contador de intentos
        $agent->increment('sync_attempts');
        $agent->update(['sync_status' => 'retrying']);

        try {
            $fastApiResponse = $this->createAgentInFastAPI($agent, $request);
            
            $agent->update([
                'sync_status' => 'synced',
                'sync_error' => null
            ]);
            
            return response()->json([
                'status' => 'success',
                'message' => 'Sincronización exitosa',
                'data' => $fastApiResponse
            ]);
        } catch (\Exception $e) {
            $agent->update([
                'sync_status' => 'failed',
                'sync_error' => $e->getMessage()
            ]);
            
            return response()->json([
                'status' => 'error',
                'message' => 'Falló el reintento de sincronización',
                'error' => $e->getMessage(),
                'attempts' => $agent->sync_attempts
            ], 500);
        }
    }

    // Método para procesar notificaciones de éxito desde FastAPI 
    // (Webhook para cuando FastAPI informa que finalmente pudo crear el agente)
    public function handleFastAPICallback(Request $request)
    {
        $validatedData = $request->validate([
            'laravel_agent_id' => 'required|integer',
            'fastapi_agent_id' => 'required|integer',
            'status' => 'required|string'
        ]);
        
        $agent = Agent::findOrFail($validatedData['laravel_agent_id']);
        
        if ($validatedData['status'] === 'success') {
            // Crear mapeo si no existe
            $mapping = AgentMapping::firstOrCreate(
                ['laravel_agent_id' => $agent->id],
                [
                    'fastapi_agent_id' => $validatedData['fastapi_agent_id'],
                    'user_id' => $agent->user_id
                ]
            );
            
            $agent->update([
                'sync_status' => 'synced',
                'sync_error' => null
            ]);
            
            return response()->json([
                'status' => 'success',
                'message' => 'Sincronización completada'
            ]);
        } else {
            $agent->update([
                'sync_status' => 'failed',
                'sync_error' => $request->input('error', 'Error desconocido desde FastAPI')
            ]);
            
            return response()->json([
                'status' => 'error',
                'message' => 'FastAPI reportó un error'
            ]);
        }
    }

/**
 * Listar todos los agentes para el usuario autenticado
 *
 * @param Request $request
 * @return \Illuminate\Http\JsonResponse
 */
public function index(Request $request)
{
    $userId = $request->header('X-User-ID');
    
    // Obtener todos los agentes del usuario con información de sincronización
    $agents = Agent::where('user_id', $userId)
        ->with('mapping')
        ->get()
        ->map(function ($agent) {
            // Añadir información sobre si está sincronizado con FastAPI
            $syncedWithFastAPI = $agent->mapping()->exists();
            $fastApiAgentId = $syncedWithFastAPI ? $agent->mapping->fastapi_agent_id : null;
            
            return [
                'id' => $agent->id,
                'name' => $agent->name,
                'activation_mode' => $agent->activation_mode,
                'status' => $agent->status,
                'keywords' => $agent->keywords,
                'custom_instructions' => $agent->custom_instructions,
                'pause_condition' => $agent->pause_condition,
                'has_waiting_time' => $agent->has_waiting_time,
                'instance_id' => $agent->instance_id,
                'sync_status' => $agent->sync_status,
                'sync_error' => $agent->sync_error,
                'synced_with_fastapi' => $syncedWithFastAPI,
                'fastapi_agent_id' => $fastApiAgentId,
                'created_at' => $agent->created_at,
                'updated_at' => $agent->updated_at
            ];
        });
    
    return response()->json([
        'status' => 'success',
        'message' => 'Agentes obtenidos exitosamente',
        'data' => [
            'agents' => $agents,
            'total' => $agents->count()
        ]
    ]);
}

}


// use App\Models\Agent;
// // use App\Models\AgentSync; // Necesitaremos crear este modelo
// use Illuminate\Http\Request;
// use Illuminate\Support\Facades\Http;
// use Illuminate\Support\Facades\Log;
// use Illuminate\Support\Facades\DB;
// use App\Models\AgentMapping;  // Agregar esta línea

// class AgentIntegrationController extends Controller
// {
//     public function store(Request $request)
//     {
//         Log::info('Iniciando proceso de creación de agente', [
//             'timestamp' => now(),
//             'request_data' => $request->all()
//         ]);

//         // 1. Primero intentamos crear en Laravel
//         try {
//             $agent = $this->createAgentInLaravel($request);
//         } catch (\Exception $e) {
//             Log::error('Falló la creación del agente en Laravel', [
//                 'error' => $e->getMessage(),
//                 'trace' => $e->getTraceAsString()
//             ]);

//             return response()->json([
//                 'status' => 'error',
//                 'message' => 'Error al crear el agente en Laravel',
//                 'error' => $e->getMessage()
//             ], 500);
//         }

//         // 2. Si se creó en Laravel, intentamos crear en FastAPI
//         try {
//             $fastApiResponse = $this->createAgentInFastAPI($agent, $request);
            
//             // Si todo fue exitoso
//             return response()->json([
//                 'status' => 'success',
//                 'message' => 'Agente creado exitosamente en ambos servicios',
//                 'data' => [
//                     'laravel_agent' => $agent,
//                     'fastapi_agent' => $fastApiResponse
//                 ]
//             ], 201);

//         } catch (\Exception $e) {
//             // Si falla FastAPI, mantenemos el registro en Laravel pero marcamos el error
//             $this->markAgentSyncFailed($agent, $e->getMessage());
            
//             Log::error('Falló la creación del agente en FastAPI', [
//                 'agent_id' => $agent->id,
//                 'error' => $e->getMessage()
//             ]);

//             return response()->json([
//                 'status' => 'partial_success',
//                 'message' => 'Agente creado en Laravel pero falló en FastAPI',
//                 'error' => $e->getMessage(),
//                 'laravel_agent' => $agent
//             ], 207); // 207 Multi-Status
//         }
//     }

//     private function createAgentInLaravel(Request $request)
//     {
//         $validatedData = $request->validate([
//             'name' => 'required|string|max:255',
//             'custom_instructions' => 'nullable|string',
//             'activation_mode' => 'required|in:always,keywords',
//             'has_waiting_time' => 'required|boolean',
//             'keywords' => 'nullable|array',
//             'pause_condition' => 'nullable|string',
//             'status' => 'required|boolean',
//         ]);

//         $userId = $request->header('X-User-ID');

//         // Verificar duplicados
//         $existingAgent = Agent::where('user_id', $userId)
//                             ->where('name', $validatedData['name'])
//                             ->first();

//         if ($existingAgent) {
//             throw new \Exception('Ya existe un agente con este nombre');
//         }

//         $agent = Agent::create([
//             'user_id' => $userId,
//             'instance_id' => $request->input('instance_id'), // Obtener instance_id del request

//             // 'instance_id' => null, // Ajusta según tu lógica
//             'name' => $validatedData['name'],
//             'custom_instructions' => $validatedData['custom_instructions'],
//             'activation_mode' => $validatedData['activation_mode'],
//             'keywords' => $validatedData['keywords'],
//             'status' => $validatedData['status'],
//             'pause_condition' => $validatedData['pause_condition'],
//             'has_waiting_time' => $validatedData['has_waiting_time'],
//             'sync_status' => 'pending' // Nuevo campo
//         ]);

//         Log::info('Agente creado exitosamente en Laravel', [
//             'agent_id' => $agent->id,
//             'timestamp' => now()
//         ]);

//         return $agent;
//     }

//     private function createAgentInFastAPI($agent, Request $request)
//     {
//         $fastapiUrl = config('services.fastapi.base_url') . '/api/v1/agents';
        
//         Log::info('Intentando conectar con FastAPI', [
//             'url' => $fastapiUrl,
//             'config_url' => config('services.fastapi.base_url'),
//             'headers' => [
//                 'X-User-ID' => $request->header('X-User-ID'),
//                 'X-Shared-Secret' => substr(config('services.fastapi.shared_secret'), 0, 5) . '...',
//             ]
//         ]);
    
//         try {
//             // Aumentar el timeout para dar más tiempo a la conexión
//             $response = Http::timeout(30)
//                 ->withHeaders([
//                     'X-User-ID' => $request->header('X-User-ID'),
//                     'X-Shared-Secret' => config('services.fastapi.shared_secret'),
//                 ])
//                 ->post($fastapiUrl, array_merge($request->all(), [
//                     'laravel_agent_id' => $agent->id
//                 ]));
    
//             if (!$response->successful()) {
//                 Log::error('Respuesta fallida de FastAPI', [
//                     'status' => $response->status(),
//                     'body' => $response->body()
//                 ]);
//                 throw new \Exception($response->body());
//             }
    
//             $fastApiResponse = $response->json();
            
//             Log::info('Respuesta exitosa de FastAPI', [
//                 'agent_id' => $agent->id,
//                 'fastapi_response' => $fastApiResponse
//             ]);
    
//             // Crear el mapeo entre IDs
//             AgentMapping::create([
//                 'laravel_agent_id' => $agent->id,
//                 'fastapi_agent_id' => $fastApiResponse['id'],
//                 'user_id' => $request->header('X-User-ID')
//             ]);
    
//             // Actualizar el estado de sincronización
//             $agent->update(['sync_status' => 'synced']);
    
//             return $fastApiResponse;
    
//         } catch (\Exception $e) {
//             Log::error('Error al conectar con FastAPI', [
//                 'error' => $e->getMessage(),
//                 'url' => $fastapiUrl,
//                 'trace' => $e->getTraceAsString()
//             ]);
//             throw $e;
//         }
//     }  
//     // private function createAgentInFastAPI($agent, Request $request)
//     // {
//     //     $fastapiUrl = config('services.fastapi.base_url') . '/api/v1/agents';
        
//     //         // Agregar logs de diagnóstico
//     //         Log::info('Configuración FastAPI', [
//     //             'url' => $fastapiUrl,
//     //             'base_url' => config('services.fastapi.base_url')
//     //         ]);
//     //         // Intentar hacer ping al host
//     //         exec("ping -c 1 host.docker.internal", $output, $returnVar);
//     //         Log::info('Prueba de ping', [
//     //             'output' => $output,
//     //             'return_var' => $returnVar
//     //         ]);


//     //     $response = Http::withHeaders([
//     //         'X-User-ID' => $request->header('X-User-ID'),
//     //         'X-Shared-Secret' => config('services.fastapi.shared_secret'),
//     //     ])->post($fastapiUrl, array_merge($request->all(), [
//     //         'laravel_agent_id' => $agent->id
//     //     ]));
    
//     //     if (!$response->successful()) {
//     //         throw new \Exception('Error en FastAPI: ' . $response->body());
//     //     }
    
//     //     $fastApiResponse = $response->json();
    
//     //     // Crear el mapeo entre IDs
//     //     AgentMapping::create([
//     //         'laravel_agent_id' => $agent->id,
//     //         'fastapi_agent_id' => $fastApiResponse['id'],
//     //         'user_id' => $request->header('X-User-ID')
//     //     ]);
    
//     //     // Actualizar el estado de sincronización
//     //     $agent->update(['sync_status' => 'synced']);
    
//     //     Log::info('Agente creado exitosamente en FastAPI', [
//     //         'agent_id' => $agent->id,
//     //         'fastapi_agent_id' => $fastApiResponse['id'],
//     //         'fastapi_response' => $fastApiResponse,
//     //         'timestamp' => now()
//     //     ]);
    
//     //     return $fastApiResponse;
//     // }





//     private function markAgentSyncFailed($agent, $error)
//     {
//         $agent->update([
//             'sync_status' => 'failed',
//             'sync_error' => $error
//         ]);
//     }

//     // Endpoint para verificar el estado de sincronización
//     public function checkSyncStatus($agentId)
//     {
//         $agent = Agent::findOrFail($agentId);
        
//         return response()->json([
//             'agent_id' => $agent->id,
//             'sync_status' => $agent->sync_status,
//             'sync_error' => $agent->sync_error,
//             'last_sync_attempt' => $agent->updated_at
//         ]);
//     }

//     // Endpoint para reintentar la sincronización
//     public function retrySyncWithFastAPI($agentId)
//     {
//         $agent = Agent::findOrFail($agentId);
        
//         if ($agent->sync_status === 'synced') {
//             return response()->json([
//                 'message' => 'El agente ya está sincronizado'
//             ]);
//         }

//         try {
//             $fastApiResponse = $this->createAgentInFastAPI($agent, request());
            
//             return response()->json([
//                 'status' => 'success',
//                 'message' => 'Sincronización exitosa',
//                 'data' => $fastApiResponse
//             ]);
//         } catch (\Exception $e) {
//             return response()->json([
//                 'status' => 'error',
//                 'message' => 'Falló el reintento de sincronización',
//                 'error' => $e->getMessage()
//             ], 500);
//         }
//     }

//     // Endpoint para listar agentes sincronizados






// }
