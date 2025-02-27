<?php

namespace App\Jobs;

use App\Models\Agent;
use App\Models\AgentMapping;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SyncAgentWithFastAPI implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $agent;
    protected $requestData;
    protected $userId;
    
    // Configuración de reintentos para el job
    public $tries = 5;          // Número máximo de intentos
    public $backoff = [5, 30, 60, 300, 600]; // Tiempo entre reintentos (en segundos)
    public $timeout = 60;       // Tiempo máximo de ejecución por intento

    /**
     * Create a new job instance.
     *
     * @param  Agent  $agent
     * @param  array  $requestData
     * @param  int    $userId
     * @return void
     */
    public function __construct(Agent $agent, array $requestData, $userId)
    {
        $this->agent = $agent;
        $this->requestData = $requestData;
        $this->userId = $userId;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        Log::info('Iniciando job de sincronización', [
            'agent_id' => $this->agent->id,
            'attempt' => $this->attempts(),
            'max_attempts' => $this->tries
        ]);

        // Verificar si el agente ya está sincronizado (podría haberse sincronizado manualmente)
        $agent = Agent::find($this->agent->id);
        if (!$agent || $agent->sync_status === 'synced') {
            Log::info('Agente ya sincronizado o eliminado, cancelando job', [
                'agent_id' => $this->agent->id
            ]);
            return;
        }

        // Incrementar contador de intentos
        $agent->increment('sync_attempts');
        $agent->update(['sync_status' => 'sync_in_progress']);

        try {
            $fastapiUrl = config('services.fastapi.base_url') . '/api/v1/agents';
            
            // Usar un timeout más largo en intentos posteriores
            $timeout = min(30 + ($this->attempts() * 15), 120); // Aumenta con cada intento, máximo 120s
            
            Log::info('Intentando crear agente en FastAPI (asíncrono)', [
                'url' => $fastapiUrl,
                'agent_id' => $agent->id,
                'attempt' => $this->attempts(),
                'timeout' => $timeout
            ]);

            $response = Http::timeout($timeout)
                ->withHeaders([
                    'X-User-ID' => $this->userId,
                    'X-Shared-Secret' => config('services.fastapi.shared_secret'),
                ])
                ->post($fastapiUrl, array_merge($this->requestData, [
                    'laravel_agent_id' => $agent->id
                ]));

            if (!$response->successful()) {
                throw new \Exception('Error en FastAPI: ' . $response->body());
            }

            $fastApiResponse = $response->json();
            
            // Crear el mapeo entre IDs
            AgentMapping::updateOrCreate(
                ['laravel_agent_id' => $agent->id],
                [
                    'fastapi_agent_id' => $fastApiResponse['id'],
                    'user_id' => $this->userId
                ]
            );

            // Actualizar el estado de sincronización
            $agent->update([
                'sync_status' => 'synced',
                'sync_error' => null
            ]);

            Log::info('Sincronización asíncrona exitosa', [
                'agent_id' => $agent->id,
                'fastapi_agent_id' => $fastApiResponse['id']
            ]);

        } catch (\Exception $e) {
            Log::error('Error en sincronización asíncrona', [
                'agent_id' => $agent->id,
                'error' => $e->getMessage(),
                'attempt' => $this->attempts()
            ]);

            // Si hemos alcanzado el máximo de intentos, marcar como fallido definitivamente
            if ($this->attempts() >= $this->tries) {
                $agent->update([
                    'sync_status' => 'failed',
                    'sync_error' => $e->getMessage()
                ]);
                
                Log::error('Sincronización fallida definitivamente después de múltiples intentos', [
                    'agent_id' => $agent->id,
                    'total_attempts' => $this->attempts()
                ]);
            } else {
                $agent->update([
                    'sync_status' => 'pending_retry',
                    'sync_error' => $e->getMessage()
                ]);
                
                // Lanzar excepción para que el job se reintente automáticamente
                throw $e;
            }
        }
    }

    /**
     * Handle a job failure.
     *
     * @param  \Throwable  $exception
     * @return void
     */
    public function failed(\Throwable $exception)
    {
        Log::error('Job de sincronización falló definitivamente', [
            'agent_id' => $this->agent->id,
            'error' => $exception->getMessage()
        ]);

        // Actualizar el agente como fallido definitivamente
        $this->agent->update([
            'sync_status' => 'failed',
            'sync_error' => $exception->getMessage()
        ]);
    }
}