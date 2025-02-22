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



class ProcessDelayedMessages implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 3;  // Número de intentos
    public $timeout = 120;  // Tiempo máximo de ejecución en segundos
    
    protected $instance;
    protected $conversation;

    public function __construct(Instance $instance, Conversation $conversation)
    {
        $this->instance = $instance;
        $this->conversation = $conversation;
        $this->onQueue('messages');  // Asignamos a una cola específica
    }

    public function handle(MessageService $messageService)
    {
        
    // Log al inicio
        Log::info('🎯 ProcessDelayedMessages comenzando ejecución', [
            'job_id' => $this->job ? $this->job->getJobId() : 'unknown',
            'instance' => $this->instance ? $this->instance->id : 'none',
            'conversation' => $this->conversation ? $this->conversation->id : 'none',
            'queue' => $this->queue ?? 'default'
        ]);


        try {
            if (!$this->instance || !$this->conversation) {
                Log::error('❌ Error: Instancia o conversación no válidas.', [
                    'instance' => $this->instance ? 'valid' : 'invalid',
                    'conversation' => $this->conversation ? 'valid' : 'invalid'
                ]);
                return;
            }

            $messageService->processDelayedMessages($this->instance, $this->conversation);
            
            Log::info('✅ Job ProcessDelayedMessages completado', [
                'job_id' => $this->job->getJobId(),
                'conversation_id' => $this->conversation->id
            ]);

        } catch (\Exception $e) {
            Log::error('❌ Error en ProcessDelayedMessages job', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;  // Re-lanzamos para que el job se marque como fallido
        }
    }

    public function failed(\Throwable $exception)
    {
        Log::error('❌ Job ProcessDelayedMessages falló', [
            'conversation_id' => $this->conversation->id,
            'error' => $exception->getMessage()
        ]);
    }
}