<?php
# app/Http/Controllers/WebhookController.php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Instance;
use App\Models\TestInstance;
use App\Models\WebhookEvent;
use App\Services\QrCodeService;
use App\Services\ConnectionService;
use App\Services\MessageService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Redis;
use App\Services\MessageSenderService; // Asegúrate de importar el servicio correctamente

use App\Jobs\ProcessPythonResponse;
use App\Models\Message;
use App\Models\Conversation;

class WebhookController extends Controller
{
    private $eventHandlers = [
        'QRCODE_UPDATED'      => 'handleQrCode',
        'CONNECTION_UPDATE'   => 'handleConnectionUpdate',
        'MESSAGES_UPSERT'     => 'handleMessagesUpsert',
        'CHATS_UPDATE'        => 'handleChatsUpdate',
        'CONTACTS_UPDATE'     => 'handleContactsUpdate',
    ];

    protected $qrCodeService;
    protected $connectionService;
    protected $messageService;

    public function __construct(
        QrCodeService $qrCodeService,
        ConnectionService $connectionService,
        MessageService $messageService,
        MessageSenderService $messageSenderService
    ) {
        $this->qrCodeService = $qrCodeService;
        $this->connectionService = $connectionService;
        $this->messageService = $messageService;
        $this->messageSenderService = $messageSenderService;

    }

    public function handleEvolutionApiWebhook(Request $request)
    {

        $payload = $request->all();
        Log::info('🟢 WebhookController.php recibido', ['payload' => $payload]);

        $instanceName = $payload['instance'] ?? null;
        if (!$instanceName) {
            Log::error('Instance name not provided in webhook');
            return response()->json(['error' => 'Instance name not provided'], 400);
        }

        $instance = TestInstance::where('name', $instanceName)->first();
        if (!$instance) {
            Log::error('Instance not found', ['instanceName' => $instanceName]);
            return response()->json(['error' => 'Instance not found'], 404);
        }

        // Verificar si el mensaje es fromMe
        if (isset($payload['data']['key']['fromMe']) && $payload['data']['key']['fromMe']) {
            Log::info('🔄 Mensaje enviado por nosotros, ignorando');
            return response()->json(['success' => true]);
        }

        // Verificar si es una actualización de estado
        if ($this->isStatusUpdate($payload)) {
            Log::info('📊 Actualización de estado recibida', [
                'status' => $payload['data']['status'] ?? 'unknown'
            ]);
            return response()->json(['success' => true]);
        }

        $eventType = strtoupper(str_replace('.', '_', $payload['event'] ?? ''));

        // Registrar el evento
        WebhookEvent::create([
            'instance_name' => $instanceName,
            'event_type'    => $eventType,
            'payload'       => $payload,
        ]);

        // Si es respuesta de Python, manejarla
        if (isset($payload['data']['responses'])) {
            $this->handleFinalResponse($instance, $payload['data']);
            return response()->json(['success' => true]);
        }

        // Procesar mensaje real si hay un handler
        if (isset($this->eventHandlers[$eventType])) {
            Log::info('📞📞 Procesando webhook', ['instanceName' => $instanceName, 'eventType' => $eventType]);
            $handlerMethod = $this->eventHandlers[$eventType];
            Log::info('Calling handler method', ['method' => $handlerMethod]);
            $this->$handlerMethod($instance, $payload);
        }

        return response()->json(['success' => true]);
    }


    private function isStatusUpdate($payload)
    {
        // Solo es actualización de estado si:
        // 1. No tiene contenido de mensaje
        // 2. O es explícitamente un status update
        $hasMessage = isset($payload['data']['message']['conversation']) || 
                     isset($payload['data']['message']['extendedTextMessage']);

        $isStatusMsg = isset($payload['data']['status']) && 
                      in_array($payload['data']['status'], ['sent', 'delivered', 'read']);

        return !$hasMessage || $isStatusMsg;
    }

    private function handleFinalResponse($instance, $data)
    {
        try {
            $customerPhone = $data['NUMERO'] ?? null;
            if (!$customerPhone && isset($data['key']['remoteJid'])) {
                $customerPhone = explode('@', $data['key']['remoteJid'])[0];
            }
            
            if (!$customerPhone) {
                Log::error('No se pudo extraer el número de teléfono');
                return;
            }

            foreach ($data['responses'] as $response) {
                SendScheduledMessage::dispatch(
                    $instance, 
                    $customerPhone, 
                    $response['content'],
                    // true
                    false // Sin delay adicional ya que FastAPI maneja el agrupamiento

                // )->onQueue('whatsapp-responses');
                );usleep(500000); // 500ms entre mensajes
            }

        } catch (\Exception $e) {
            Log::error('Error procesando respuesta final', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }
    }




    private function handleQrCode($instance, $data)
    {
        $this->qrCodeService->handleQrCode($instance, $data);
    }

    private function handleConnectionUpdate($instance, $data)
    {
        $this->connectionService->handleConnectionUpdate($instance, $data);
    }

    private function handleMessagesUpsert($instance, $data)
    {
        Log::info('📩 Mensaje recibido', ['instance' => $instance->name, '🔥data' => $data]);
        if (!isset($data['responses'])) {
            $this->messageService->handleNewMessage($instance, $data);
        }
    }

    private function handleChatsUpdate($instance, $data)
    {
        Log::info('Chats update received', ['instance' => $instance->name, 'data' => $data]);
    }

    private function handleContactsUpdate($instance, $data)
    {
        Log::info('Contacts update received', ['instance' => $instance->name, 'data' => $data]);
    }

public function handlePythonCallback(Request $request)
{
    Log::info('🟢 Respuesta recibida de Python', ['payload' => $request->all()]);

    try {
        $userId = $request->input('user_id');
        $phoneNumber = $request->input('phone_number');
        $responseData = $request->input('response');

        if (!$userId || !$phoneNumber || !$responseData) {
            Log::error('❌ Datos incompletos en el Webhook de Python', ['payload' => $request->all()]);
            return response()->json(['error' => 'Datos incompletos'], 400);
        }

        // Obtener la instancia asociada al usuario
        $instance = TestInstance::where('user_id', $userId)
            ->where('status', 'active')
            ->first();

        if (!$instance) {
            Log::error('❌ No se encontró instancia activa para el usuario', ['user_id' => $userId]);
            return response()->json(['error' => 'Instancia no encontrada'], 404);
        }

        // Obtener la conversación
        $conversation = Conversation::where('customer_phone', $phoneNumber)
                                 ->where('user_id', $userId)
                                 ->first();

        // Si hay respuestas, procesarlas inmediatamente
        if (isset($responseData['responses']) && is_array($responseData['responses'])) {
            foreach ($responseData['responses'] as $response) {
                $content = $response['content'] ?? null;
                if ($content) {
                    // Guardar mensaje en la base de datos
                    Message::create([
                        'user_id' => $userId,
                        'conversation_id' => $conversation->id,
                        'sender' => 'agent',
                        'content' => $content,
                        'type' => 'text',
                        'sent_at' => now()
                    ]);

                    // Enviar mensaje a WhatsApp
                    $this->messageSenderService->sendMessage(
                        $instance,
                        $phoneNumber,
                        $content,
                        false // Sin tiempo de espera adicional
                    );

                    Log::info('📤 Mensaje enviado al usuario', [
                        'phone' => $phoneNumber,
                        'content' => $content
                    ]);

                    usleep(500000); // 500ms pausa entre mensajes
                }
            }
        }

        return response()->json(['success' => true]);

    } catch (\Exception $e) {
        Log::error('❌ Error procesando respuesta de Python', [
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ]);
        return response()->json(['error' => 'Error interno'], 500);
    }
}

}


