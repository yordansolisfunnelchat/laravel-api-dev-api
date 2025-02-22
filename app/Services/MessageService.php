<?php

namespace App\Services;
use Illuminate\Support\Facades\Http; // Añade esta línea
use App\Models\TestInstance;
use App\Models\Instance;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\Agent;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Carbon\Carbon;
use App\Jobs\ProcessDelayedMessages;
use App\Services\AgentIntegrationService;


class MessageService
{
    protected $pythonApiService;
    protected $messageSenderService;
    protected $transcriptionService;
    protected $deduplicationWindow = 60;

    public function __construct(
        PythonApiService $pythonApiService,
        MessageSenderService $messageSenderService,
        TranscriptionService $transcriptionService
    ) {
        $this->pythonApiService = $pythonApiService;
        $this->messageSenderService = $messageSenderService;
        $this->transcriptionService = $transcriptionService;
    }

    // public function handleNewMessage(Instance $instance, $data)
    public function handleNewMessage(TestInstance $instance, array $data)

    {
        Log::info('1. 🔵 Obteniendo datos para ser procesado....', [
            'instance' => $instance->name, 
            'data' => $data
        ]);

        $messageData = $data['data'] ?? [];
        if (empty($messageData)) {
            Log::error('No message data found in webhook payload');
            return;
        }

        $isFromMe = $messageData['key']['fromMe'] ?? false;
        if ($isFromMe) {
            Log::info('Mensaje enviado por nosotros, ignorando');
            return;
        }

        if ($this->isDuplicateMessage($messageData)) {
            return;
        }

        $this->handleCustomerMessage($instance, $messageData);
    }

    private function isDuplicateMessage($messageData)
    {
        $messageId = $messageData['key']['id'] ?? '';
        $messageTimestamp = $messageData['messageTimestamp'] ?? '';
        $content = $messageData['message']['conversation'] ?? '';

        Log::info('🍪 Procesando mensaje entrante en memoria', [
            'messageId' => $messageId,
            'messageTimestamp' => $messageTimestamp,
            'content' => $content
        ]);

        $contentHash = md5($content . $messageTimestamp);
        $messageKey = "msg_id:{$messageId}";
        $contentKey = "msg_content:{$contentHash}";
        
        if (Cache::has($messageKey)) {
            Log::info('❌ Mensaje duplicado detectado por ID', [
                'messageId' => $messageId,
            ]);
            return true;
        }

        if (Cache::has($contentKey)) {
            Log::info('❌ Mensaje duplicado detectado por contenido', [
                'content' => $content,
                'timestamp' => $messageTimestamp
            ]);
            return true;
        }

        Cache::put($messageKey, true, $this->deduplicationWindow);
        Cache::put($contentKey, true, $this->deduplicationWindow);
        
        return false;
    }

    // private function handleCustomerMessage(Instance $instance, $messageData)
    private function handleCustomerMessage( TestInstance $instance, $messageData)

    {
        Log::info('1. 🔵 Procesando mensaje de cliente....');
        $customerPhone = $this->extractCustomerPhone($messageData);
        $messageType = $this->determineMessageType($messageData);
        $messageContent = $this->extractMessageContent($messageData, $messageType);
        
        Log::info('2. 🔵 Procesando mensaje de cliente....', [
            'instance' => $instance->name,
            'customerPhone' => $customerPhone,
            'messageType' => $messageType
        ]);

        if (!$messageContent) {
            Log::error('No se pudo extraer contenido del mensaje');
            return;
        }
        Log::info('3. 🔵 Procesando mensaje de cliente....', [
            'instance_id' => $instance->id,
            'instance' => $instance->name,
            'customerPhone' => $customerPhone,
            'messageType' => $messageType,
            'messageContent' => $messageContent
        ]);

        # aqui
/*        
    Obtener o crear una conversación para el cliente basándose en su número de teléfono y el user_id
    Buscar un agente activo asociado con la instancia
*/  
        # numero del 573113634658xxx user_id 1
        $conversation = $this->getOrCreateConversation($customerPhone, $instance->user_id);
        
        Log::info( "Se obtiene la conversación", [
            'conversation' => $conversation
        ]);

        $agent = Agent::where('instance_id', $instance->name)
                     ->where('status', true)
                     ->first();


       /*
          {
    "App\\Models\\Agent": {
      "id": 6,
      "user_id": 1,
      "instance_id": "instances_test_d",
      "name": "JhordanFtines",
      "custom_instructions": "Agente de ventas jhordan, capaz de ayudar en informacion ventas",
      "status": true,
      "created_at": "2025-02-10T00:18:04.000000Z",
      "updated_at": "2025-02-10T00:18:04.000000Z",
      "activation_mode": "always",
      "keywords": [
        "soporte",
        "ventas"
      ],
      "pause_condition": "string",
      "has_waiting_time": true,
      "sync_status": "synced",
      "sync_error": null
    }
  } */              

        Log::info("✅ Agente encontrado", [
            'agent' => $agent
        ]);


        /**
         * {"conversation":{"App\\Models\\Conversation":
         * {"customer_phone":"573113634658",
         * "user_id":1,
         * "status":"active",
         * "updated_at":"2025-02-10T01:25:50.000000Z",
         * "created_at":"2025-02-10T01:25:50.000000Z",
         * "id":1}}} 
         * 
         */
        Log::info("✅ Conversación encontrada", [
            'conversation_id' => $conversation->id
        ]);
        // por aqui ...
        if (!$this->shouldProcessMessage($conversation, $agent, $messageData)) {
            Log::info('Message not processed due to conditions not met', [
                'conversation_id' => $conversation->id
            ]);
            return;
        }

        $message = $this->createMessage(
            $instance->user_id,
            $conversation->id,
            $messageContent,
            $messageType,
            'customer'
        );

        $this->processMessage($instance, $conversation, $message, $agent);
    }

    // private function processMessage(Instance $instance, Conversation $conversation, Message $message, ?Agent $agent)
    private function processMessage(TestInstance $instance, Conversation $conversation, Message $message, ?Agent $agent)

    {
        if (!$agent) {
            Log::info('No active agent found for instance', ['instance_id' => $instance->id]);
            return;
        }

        if ($conversation->status === 'paused') {
            Log::info('Conversation is paused, not responding', ['conversation_id' => $conversation->id]);
            return;
        }

        if ($conversation->agent_id === null) {
            if ($agent->activation_mode === 'keywords' && 
                !$this->containsKeywords($message->content, $agent->keywords)) {
                Log::info('Message does not contain keywords', [
                    'conversation_id' => $conversation->id
                ]);
                return;
            }
            $conversation->agent_id = $agent->id;
            $conversation->save();
            Log::info('Agent assigned to conversation', [
                'agent_id' => $agent->id,
                'conversation_id' => $conversation->id
            ]);
        }

        // $pythonData = [
        //     'NUMERO' => $conversation->customer_phone,
        //     'TEXTO' => $message->content,
        //     'user_id' => $instance->user_id,
        //     'conversation_id' => $conversation->id
        // ];

        // $this->triggerDelayedProcessing($instance, $conversation, $pythonData);

    // Enviar a FastAPI para procesamiento
    $pythonData = [
        'NUMERO' => $conversation->customer_phone,
        'TEXTO' => $message->content,
        'user_id' => $instance->user_id,
        'conversation_id' => $conversation->id,
        'instance_name' => $instance->name,
        'has_waiting_time' => $agent->has_waiting_time
    ];
    // Enviar directamente a FastAPI en lugar de usar ProcessDelayedMessages
    $this->pythonApiService->sendMessage($pythonData);

    }
    
    // public function processDelayedMessages(Instance $instance, Conversation $conversation, array $pythonData = [])
    public function processDelayedMessages(TestInstance $instance, Conversation $conversation, array $pythonData = [])

    {
        $cacheKey = "processing_conversation_{$conversation->id}";
    
        if (!Cache::has($cacheKey)) {
            if (!Cache::add($cacheKey, true, 60)) {
                return;
            }
        }
    
        try {
            Log::info('🎯 Iniciando procesamiento de mensajes', [
                'conversation_id' => $conversation->id,
                'python_data' => $pythonData
            ]);
    
            // Usar los datos de Python proporcionados o buscar mensajes no procesados
            if (empty($pythonData)) {
                $messages = Message::where('conversation_id', $conversation->id)
                    ->where('processed', false)
                    ->orderBy('created_at', 'asc')
                    ->get();
    
                if ($messages->isEmpty()) {
                    Log::info('No hay mensajes para procesar');
                    return;
                }
    
                $pythonData = [
                    'NUMERO' => $conversation->customer_phone,
                    'TEXTO' => $messages->pluck('content')->implode("\n"),
                    // 'user_id' => $instance->user_id,
                    'user_id' => $instance->user_id,
                    
                    'conversation_id' => $conversation->id
                ];
            }
    
            // Enviar a Python
            $pythonResponse = $this->pythonApiService->sendMessage($pythonData);
            
            if ($pythonResponse && isset($pythonResponse['responses'])) {
                $this->processResponses($instance, $conversation->customer_phone, $pythonResponse['responses']);
                
                // Marcar mensajes como procesados
                if (!empty($messages)) {
                    Message::whereIn('id', $messages->pluck('id'))->update(['processed' => true]);
                }
            }
    
        } catch (\Exception $e) {
            Log::error('Error en processDelayedMessages', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        } finally {
            Cache::forget($cacheKey);
        }
    }
    
    private function processResponses(TestInstance $instance, $customerPhone, $responses)
    // private function processResponses(Instance $instance, $customerPhone, $responses)
    {
        foreach ($responses as $response) {
            if (isset($response['responses']) && is_array($response['responses'])) {
                foreach ($response['responses'] as $nestedResponse) {
                    if (isset($nestedResponse['content'])) {
                        $this->messageSenderService->sendMessage(
                            $instance,
                            $customerPhone,
                            $nestedResponse['content'],
                            true
                        );
                    }
                }
            } elseif (isset($response['content'])) {
                $this->messageSenderService->sendMessage(
                    $instance,
                    $customerPhone,
                    $response['content'],
                    true
                );
            }
            usleep(500000);
        }
    }
    
    // private function triggerDelayedProcessing(Instance $instance, Conversation $conversation, array $pythonData)
    private function triggerDelayedProcessing(TestInstance $instance, Conversation $conversation, array $pythonData)
    {
        $cacheKey = "processing_conversation_{$conversation->id}";
    
        if (!Cache::has($cacheKey)) {
            Cache::put($cacheKey, true, 60);
            
            $agent = Agent::where('instance_id', $instance->id)
                         ->where('status', true)
                         ->first();
            
            $delayTime = ($agent && !$agent->has_waiting_time) ? 0 : 15;
    
            try {
                // Encolar el job con los datos de Python
                $job = new ProcessDelayedMessages($instance, $conversation, $pythonData);
                dispatch($job->delay(Carbon::now()->addSeconds($delayTime)));
    
                Log::info('🎯 Job encolado correctamente', [
                    'conversation_id' => $conversation->id,
                    'delay_time' => $delayTime,
                    'python_data' => $pythonData
                ]);
            } catch (\Exception $e) {
                Log::error('❌ Error al encolar job', [
                    'conversation_id' => $conversation->id,
                    'error' => $e->getMessage()
                ]);
                Cache::forget($cacheKey);
            }
        } else {
            Log::info('⏳ Procesamiento ya programado', [
                'conversation_id' => $conversation->id
            ]);
        }
    }

    // Funciones auxiliares
    // private function getOrCreateConversation($customerPhone, $userId)
    // {

    //     Log::info('4. 🔵 Procesando mensaje de cliente....', [
    //         'customerPhone' => $customerPhone,
    //         'userId' => $userId
    //     ]);
    //     return Conversation::firstOrCreate(
    //         ['customer_phone' => $customerPhone, 'user_id' => $userId],
    //         ['status' => 'active']
    //     );
    // }
    private function getOrCreateConversation($customerPhone, $userId)
    {
        Log::info("Recibiendo datos para crear converzacion:  -> getOrCreateConversation   ");
        return Conversation::firstOrCreate(
            ['customer_phone' => $customerPhone,
             'user_id' => $userId],
            ['status' => 'active']
        );
    }





    private function createMessage($userId, $conversationId, $content, $type, $sender)
    {
        Log::info(" 🟡 LLegando a  createMessage " );
        return Message::create([
            'user_id' => $userId,
            'conversation_id' => $conversationId,
            'sender' => $sender,
            'content' => $content,
            'type' => $type,
            'sent_at' => now(),
            // 'processed' => false,
        ]);
    }
 /// 
    private function shouldProcessMessage(Conversation $conversation, ?Agent $agent, $messageData)
    {
        Log::info(" 🟡 LLegando a  shouldProcessMessage " ); 
        $remoteJid = $messageData['key']['remoteJid'] ?? '';
        if (str_ends_with($remoteJid, '@g.us')) {
            return false;
        }
    
        if ($conversation->status === 'active') {
            return true;
        }
    
        if (!$agent || !$agent->status) {
            return false;
        }
    
        if ($agent->activation_mode === 'always') {
            return true;
        }
    
        if ($agent->activation_mode === 'keywords') {
            $messageContent = $this->extractTextContent($messageData);
            return $this->containsKeywords($messageContent, $agent->keywords);
        }
    
        return false;
    }

    private function extractCustomerPhone($messageData)
    {
        $remoteJid = $messageData['key']['remoteJid'] ?? '';
        return explode('@', $remoteJid)[0] ?? '';
    }

    private function determineMessageType($messageData)
    {
        $message = $messageData['message'] ?? [];
        
        if (isset($message['conversation']) || isset($message['extendedTextMessage'])) {
            return 'text';
        } elseif (isset($message['imageMessage'])) {
            return 'image';
        } elseif (isset($message['videoMessage'])) {
            return 'video';
        } elseif (isset($message['audioMessage'])) {
            return 'audio';
        } elseif (isset($message['documentMessage'])) {
            return 'document';
        }
        
        return 'unknown';
    }

    private function extractMessageContent($messageData, $messageType)
    {
        $message = $messageData['message'] ?? [];
        
        switch ($messageType) {
            case 'text':
                return $message['conversation'] ?? 
                       $message['extendedTextMessage']['text'] ?? '';
            case 'audio':
                return $this->handleAudioMessage($messageData);
            case 'image':
                return $message['imageMessage']['caption'] ?? 'Image received';
            case 'video':
                return $message['videoMessage']['caption'] ?? 'Video received';
            case 'document':
                return $message['documentMessage']['fileName'] ?? 'Document received';
            default:
                Log::warning('Unknown message type', ['type' => $messageType]);
                return null;
        }
    }

    private function extractTextContent($messageData)
    {
        $message = $messageData['message'] ?? [];
        
        if (isset($message['conversation'])) {
            return $message['conversation'];
        }
        
        if (isset($message['extendedTextMessage']['text'])) {
            return $message['extendedTextMessage']['text'];
        }

        return '';
    }

    private function handleAudioMessage($messageData)
    {
        $audioUrl = $messageData['message']['mediaUrl'] ?? $messageData['mediaUrl'] ?? null;
        
        if ($audioUrl) {
            Log::info('Attempting to transcribe audio', ['url' => $audioUrl]);
            try {
                $transcription = $this->transcriptionService->transcribe($audioUrl);
                if ($transcription !== null) {
                    Log::info('Audio transcribed successfully', ['transcription' => $transcription]);
                    return $transcription;
                }
            } catch (\Exception $e) {
                Log::error('Error transcribing audio', ['error' => $e->getMessage()]);
            }
        }
        
        return "Audio message received (transcription failed)";
    }

    private function containsKeywords(string $message, $keywords): bool
    {
        if (empty($keywords)) {
            return false;
        }

        $keywords = is_array($keywords) ? $keywords : explode(',', $keywords);
        $message = strtolower($message);

        foreach ($keywords as $keyword) {
            if (str_contains($message, strtolower(trim($keyword)))) {
                return true;
            }
        }

        return false;
    }
}