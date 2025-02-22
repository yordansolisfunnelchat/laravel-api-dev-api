<?php

namespace App\Jobs;

use App\Models\Instance;
use App\Models\Conversation;
use App\Services\MessageService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use App\Models\TestInstance;


class ProcessDelayedMessages implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 3;
    public $timeout = 120;
    public $maxExceptions = 2;
    public $backoff = [10, 30, 60];

    protected $instance;
    protected $conversation;
    protected $pythonData;

    /**
     * Create a new job instance.
     */
    // public function __construct(Instance $instance, Conversation $conversation, array $pythonData = [])
    public function __construct(TestInstance $instance, Conversation $conversation, array $pythonData = [])

    {
        $this->instance = $instance;
        $this->conversation = $conversation;
        $this->pythonData = $pythonData;
        $this->onQueue('messages');

        Log::info('🎯 Job creado', [
            'conversation_id' => $conversation->id,
            'instance_id' => $instance->id,
            'python_data' => $pythonData,
            'queue' => 'messages'
        ]);
    }

    /**
     * Execute the job.
     */
    public function handle(MessageService $messageService)
    {
        Log::info('🎯 Iniciando procesamiento de mensajes retrasados', [
            'job_id' => $this->job ? $this->job->getJobId() : 'unknown',
            'instance_id' => $this->instance->id,
            'conversation_id' => $this->conversation->id,
            'python_data' => $this->pythonData
        ]);

        try {
            $this->validateData();

            // Procesar mensajes con los datos de Python
            $messageService->processDelayedMessages(
                $this->instance, 
                $this->conversation,
                $this->pythonData
            );
            
            Log::info('✅ Procesamiento completado', [
                'conversation_id' => $this->conversation->id,
                'python_data' => $this->pythonData
            ]);

        } catch (\Exception $e) {
            $this->handleError($e);
            throw $e;
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception)
    {
        Log::error('❌ Falló el procesamiento de mensajes retrasados', [
            'conversation_id' => $this->conversation->id,
            'instance_id' => $this->instance->id,
            'error' => $exception->getMessage(),
            'attempts' => $this->attempts(),
            'python_data' => $this->pythonData
        ]);
    }

    /**
     * Determine the time at which the job should timeout.
     */
    public function retryUntil()
    {
        return now()->addMinutes(5);
    }

    /**
     * Validate job data.
     */
    // private function validateData()
    // {
    //     // Recargar la instancia desde la base de datos
    //     // $freshInstance = Instance::find($this->instance->id);
    //     $freshInstance = TestInstance::find($this->instance->id);

        
        
    //     if (!$freshInstance || !$this->conversation) {
    //         Log::error('❌ Datos de job inválidos', [
    //             'instance' => $freshInstance ? 'valid' : 'invalid',
    //             'conversation' => $this->conversation ? 'valid' : 'invalid'
    //         ]);
    //         throw new \InvalidArgumentException('Datos de instancia o conversación inválidos');
    //     }
    
    //     // Validar estado de la instancia
    //     Log::info('🔍 Verificando estado de instancia', [
    //         'instance_id' => $freshInstance->id,
    //         'name' => $freshInstance->name,
    //         'status' => $freshInstance->status
    //     ]);
    
    //     if (!in_array($freshInstance->status, ['connected', 'active'])){
    //         Log::error('❌ Estado de instancia inválido', [
    //             'instance_id' => $freshInstance->id,
    //             'name' => $freshInstance->name,
    //             'current_status' => $freshInstance->status,
    //             'required_status' => 'connected'
    //         ]);
            
    //         // En lugar de lanzar excepción, intentar reconectar
    //         if ($freshInstance->status === 'disconnected') {
    //             Log::info('🔄 Intentando reconectar instancia...');
    //             $freshInstance->status = 'connected';
    //             $freshInstance->save();
                
    //             // Continuar con el procesamiento
    //             return;
    //         }
            
    //         throw new \InvalidArgumentException("La instancia no está conectada (Estado actual: {$freshInstance->status})");
    //     }
    
    //     // Validar estado de la conversación
    //     if ($this->conversation->status !== 'active') {
    //         Log::info('🔵 Conversación no está activa', [
    //             'conversation_id' => $this->conversation->id,
    //             'status' => $this->conversation->status
    //         ]);
    //         throw new \InvalidArgumentException('La conversación no está activa');
    //     }
    
    //     Log::info('✅ Validación exitosa', [
    //         'instance_id' => $freshInstance->id,
    //         'instance_status' => $freshInstance->status,
    //         'conversation_id' => $this->conversation->id,
    //         'conversation_status' => $this->conversation->status
    //     ]);
    // }


    private function validateData()
    {
        // Recargar la instancia desde la base de datos
        $freshInstance = TestInstance::find($this->instance->id);
    
        if (!$freshInstance || !$this->conversation) {
            Log::error('❌ Datos de job inválidos', [
                'instance' => $freshInstance ? 'valid' : 'invalid',
                'conversation' => $this->conversation ? 'valid' : 'invalid'
            ]);
            throw new \InvalidArgumentException('Datos de instancia o conversación inválidos');
        }
    
        Log::info('🔍 Verificando estado de instancia', [
            'instance_id' => $freshInstance->id,
            'name' => $freshInstance->name,
            'status' => $freshInstance->status
        ]);
    
        if ($freshInstance->status === 'active') {
            Log::info('✅ Estado de instancia válido', [
                'instance_id' => $freshInstance->id,
                'status' => $freshInstance->status
            ]);
        } else {
            Log::error('❌ Estado de instancia inválido', [
                'instance_id' => $freshInstance->id,
                'name' => $freshInstance->name,
                'current_status' => $freshInstance->status,
                'required_status' => 'active'
            ]);
            
            if ($freshInstance->status === 'disconnected') {
                Log::info('🔄 Intentando reconectar instancia...');
                $freshInstance->status = 'active';
                $freshInstance->save();
                return;
            }
            
            throw new \InvalidArgumentException("La instancia no está activa (Estado actual: {$freshInstance->status})");
        }
    
        // Validar estado de la conversación
        if ($this->conversation->status !== 'active') {
            Log::info('🔵 Conversación no está activa', [
                'conversation_id' => $this->conversation->id,
                'status' => $this->conversation->status
            ]);
            throw new \InvalidArgumentException('La conversación no está activa');
        }
    
        Log::info('✅ Validación exitosa', [
            'instance_id' => $freshInstance->id,
            'instance_status' => $freshInstance->status,
            'conversation_id' => $this->conversation->id,
            'conversation_status' => $this->conversation->status
        ]);
    }


    /**
     * Handle job error.
     */
    private function handleError(\Exception $e)
    {
        $context = [
            'error' => $e->getMessage(),
            'conversation_id' => $this->conversation->id,
            'instance_id' => $this->instance->id,
            'instance_status' => $this->instance->status,
            'conversation_status' => $this->conversation->status,
            'attempts' => $this->attempts(),
            'python_data' => $this->pythonData
        ];

        if ($this->attempts() >= $this->tries) {
            Log::error('❌ Error fatal en procesamiento de mensajes', $context);
        } else {
            Log::warning('⚠️ Error en procesamiento de mensajes, reintentando...', $context);
        }
    }

    /**
     * Get the tags that should be assigned to the job.
     */
    public function tags()
    {
        return [
            'process_messages',
            'instance:'.$this->instance->id,
            'conversation:'.$this->conversation->id,
        ];
    }
}