<?php
# (Nuevo Job principal) ✅

namespace App\Jobs;

use App\Models\Instance;
use App\Services\MessageSenderService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessPythonResponse implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $userId;
    protected $phoneNumber;
    protected $responseData;

    /**
     * Create a new job instance.
     */
    public function __construct($userId, $phoneNumber, $responseData)
    {
        $this->userId = $userId;
        $this->phoneNumber = $phoneNumber;
        $this->responseData = $responseData;
    }

    /**
     * Execute the job.
     */
    public function handle(MessageSenderService $messageSenderService)
    {
        Log::info("📩 Procesando respuesta de Python para UserID: {$this->userId}");

        $instance = Instance::where('user_id', $this->userId)->first();
        if (!$instance) {
            Log::error("❌ Instancia no encontrada para UserID: {$this->userId}");
            return;
        }

        foreach ($this->responseData['responses'] as $response) {
            $messageSenderService->sendMessage(
                $instance,
                $this->phoneNumber,
                $response['content']
            );
        }

        Log::info("✅ Respuesta enviada correctamente a WhatsApp para UserID: {$this->userId}");
    }
}
