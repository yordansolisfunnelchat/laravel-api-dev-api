<?php
# app/Jobs/SendScheduledMessage.php
namespace App\Jobs;

use App\Models\Instance;
use App\Models\Conversation;
use App\Models\Message;
use App\Services\MessageSenderService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SendScheduledMessage implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $instance;
    protected $conversation;
    protected $content;
    protected $type;
    protected $hasWaitingTime;

    public function __construct(Instance $instance, Conversation $conversation, $content, $type, $hasWaitingTime)
    {
        $this->instance = $instance;
        $this->conversation = $conversation;
        $this->content = $content;
        $this->type = $type;
        // Forzar el valor booleano
        $this->hasWaitingTime = (bool) $hasWaitingTime; // Cambio sugerido
    }

    public function handle(MessageSenderService $messageSenderService)
    {
        Log::info('Processing message with configuration', [
            'has_waiting_time' => $this->hasWaitingTime
        ]);

        $messageSenderService->sendMessage(
            $this->instance, 
            $this->conversation->customer_phone, 
            $this->content,
            $this->hasWaitingTime
        );
        
        // Message::create([
        //     'user_id' => $this->instance->user_id,
        //     'conversation_id' => $this->conversation->id,
        //     'sender' => 'agent',
        //     'content' => $this->content,
        //     'type' => 'text',
        //     'sent_at' => now(),
        // ]);
    }
}
