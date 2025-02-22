<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Models\Instance;
use App\Models\Conversation;
use App\Models\Message;

class MessageController extends Controller
{
        /**
     * Obtiene la configuración de la API Evolution desde la base de datos.
     * Retorna un array con la URL base y la clave API.
     */
    private function getEvolutionConfig()
    {
        $baseUrl = DB::table('configurations')
            ->where('key', 'evolution_api_base_url')
            ->value('value');
            
        $apiKey = DB::table('configurations')
            ->where('key', 'evolution_api_key')
            ->value('value');

        return [
            'base_url' => $baseUrl,
            'api_key' => $apiKey
        ];
    }

        /**
     * Obtiene la instancia de usuario según el ID proporcionado.
     * Si hay múltiples instancias, devuelve 'multiple'. Si no hay ninguna, devuelve null.
     */
    private function getUserInstance(Request $request, $instanceId = null)
    {
        $query = Instance::where('user_id', $request->user()->id);

        if ($instanceId) {
            return $query->where('id', $instanceId)->first();
        }

        $instances = $query->get();

        if ($instances->isEmpty()) {
            return null;
        }

        if ($instances->count() > 1) {
            return 'multiple';
        }

        return $instances->first();
    }

     /**
     * Detecta el tipo MIME de un archivo basado en su URL.
     * Puede manejar URLs en base64 o enlaces remotos.
     */
    private function detectMimeType($url)
    {
        try {
            // Para URLs base64
            if (str_starts_with($url, 'data:')) {
                $matches = [];
                if (preg_match('/^data:([\w-]+\/[\w-]+);base64,/', $url, $matches)) {
                    return $matches[1];
                }
            }

            // Para URLs remotas
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_NOBODY, true);
            curl_setopt($ch, CURLOPT_HEADER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 5);
            curl_exec($ch);
            $mimeType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
            curl_close($ch);

            if ($mimeType && strpos($mimeType, '/') !== false) {
                return trim(explode(';', $mimeType)[0]);
            }

            // Inferir por extensión
            $extension = strtolower(pathinfo(parse_url($url, PHP_URL_PATH), PATHINFO_EXTENSION));
            $commonMimeTypes = [
                'jpg' => 'image/jpeg',
                'jpeg' => 'image/jpeg',
                'png' => 'image/png',
                'gif' => 'image/gif',
                'webp' => 'image/webp',
                'pdf' => 'application/pdf',
                'doc' => 'application/msword',
                'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                'xls' => 'application/vnd.ms-excel',
                'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'ppt' => 'application/vnd.ms-powerpoint',
                'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
                'mp4' => 'video/mp4',
                '3gp' => 'video/3gpp',
                'mov' => 'video/quicktime',
                'mp3' => 'audio/mpeg',
                'wav' => 'audio/wav',
                'ogg' => 'audio/ogg',
                'm4a' => 'audio/mp4',
            ];

            return $commonMimeTypes[$extension] ?? 'application/octet-stream';

        } catch (\Exception $e) {
            Log::error('Error detecting MIME type', [
                'url' => $url,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

        /**
     * Formatea el mensaje de error en caso de una respuesta fallida de la API.
     */

    private function formatErrorMessage($responseBody)
    {
        try {
            $error = json_decode($responseBody, true);
            return $error['error'] ?? $error['message'] ?? 'Error al enviar el mensaje';
        } catch (\Exception $e) {
            return 'Error al enviar el mensaje';
        }
    }

     /**
     * Busca una conversación activa con un usuario o crea una nueva si no existe.
     * Retorna el modelo de la conversación.
     *  NOTA: Esto lo deberia generar la api de asistete 🆕
     */ 
    // 🔥
    // private function findOrCreateConversation(Request $request, $phone)
    // {
    //     // Buscar conversación existente
    //     $conversation = Conversation::where('user_id', $request->user()->id)
    //         ->where('customer_phone', $phone)
    //         ->where('status', 'active')
    //         ->first();

    //     // Si no existe, crear una nueva
    //     if (!$conversation) {
    //         $conversation = Conversation::create([
    //             'user_id' => $request->user()->id,
    //             'customer_phone' => $phone,
    //             'status' => 'active'
    //         ]);
    //     }

    //     return $conversation;
    // }

    /**
     * Crea un mensaje del sistema asociado a una conversación en la base de datos.
     *  NOTA: Esto lo deberia generar la api de asistete 🆕
     */
    
    // private function createSystemMessage($conversationId, $content, $userId, $type = 'text')
    // {
    //     return Message::create([
    //         'user_id' => $userId,
    //         'conversation_id' => $conversationId,
    //         'sender' => 'system',
    //         'content' => $content,
    //         'type' => $type,
    //         'sent_at' => now(),
    //     ]);
    // }


        /**
     * Envía un mensaje de texto a través de la API Evolution.
     */
    public function sendText(Request $request)
    {

        
        $request->validate([
            'instance_id' => 'nullable|exists:instances,id',
            'phone' => 'required|string',
            'text' => 'required|string',
            'delay' => 'nullable|integer|min:0'
        ]);

        // Encontrar o crear conversación esto lo deberia de hacer la api de asistete ya 🆕
        // $conversation = $this->findOrCreateConversation($request, $request->phone); 

        //$response = $this->sendMessageToPython($request->text, $request->phone, $request->user()->id);


        //  Buscar la instancia del usuario o devolver un error si no hay instancias disponibles
        $instance = $this->getUserInstance($request, $request->instance_id);

        if (!$instance) {
            return response()->json([
                'message' => 'No hay instancias disponibles'
            ], 404);
        }

        if ($instance === 'multiple') {
            return response()->json([
                'message' => 'No se ha seleccionado una instancia'
            ], 400);
        }

        $evolutionConfig = $this->getEvolutionConfig();
        $serverUrl = $evolutionConfig['base_url'] . '/message/sendText/' . $instance->name;

        try {

            $response = Http::withHeaders([
                'Content-Type' => 'application/json',
                'apikey' => $evolutionConfig['api_key'],
            ])->post($serverUrl, [
                'number' => $request->phone, 
                'text' => $request->text,
                'delay' => $request->delay ?? 1200,
            ]);

            if ($response->successful()) {
                Log::info('WhatsApp message sent successfully', [
                    'instance' => $instance->name,
                    'phone' => $request->phone
                ]);

                // Crear mensaje en la base de datos, esto lo deberia de hacer la api de asistete ya 🆕
                // $this->createSystemMessage(
                //     $conversation->id,
                //     $request->text,
                //     $request->user()->id
                // );

                return response()->json([
                    'message' => 'Mensaje de texto enviado con éxito'
                ]);
            } else {
                Log::error('Failed to send WhatsApp message', [
                    'instance' => $instance->name,
                    'phone' => $request->phone,
                    'response' => $response->body()
                ]);

                return response()->json([
                    'message' => $this->formatErrorMessage($response->body())
                ], $response->status());
            }
        } catch (\Exception $e) {
            Log::error('Exception while sending WhatsApp message', [
                'instance' => $instance->name,
                'phone' => $request->phone,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'message' => 'Error al enviar el mensaje'
            ], 500);
        }
    }

    public function sendMedia(Request $request)
    {
        $request->validate([
            'instance_id' => 'nullable|exists:instances,id',
            'phone' => 'required|string',
            'mediatype' => 'required|string|in:image,video,document',
            'media' => 'required|string',
            'caption' => 'nullable|string',
            'fileName' => 'nullable|string',
            'delay' => 'nullable|integer|min:0',
            'quoted' => 'nullable|array',
            'mentionsEveryOne' => 'nullable|boolean',
            'mentioned' => 'nullable|array'
        ]);

        // Encontrar o crear conversación
        $conversation = $this->findOrCreateConversation($request, $request->phone);

        $instance = $this->getUserInstance($request, $request->instance_id);

        if (!$instance) {
            return response()->json([
                'message' => 'No hay instancias disponibles'
            ], 404);
        }

        if ($instance === 'multiple') {
            return response()->json([
                'message' => 'No se ha seleccionado una instancia'
            ], 400);
        }

        $mimeType = $this->detectMimeType($request->media);
        if (!$mimeType) {
            return response()->json([
                'message' => 'No se pudo detectar el tipo MIME del archivo'
            ], 400);
        }

        $evolutionConfig = $this->getEvolutionConfig();
        $serverUrl = $evolutionConfig['base_url'] . '/message/sendMedia/' . $instance->name;

        try {
            $payload = [
                'number' => $request->phone,
                'mediatype' => $request->mediatype,
                'mimetype' => $mimeType,
                'media' => $request->media,
                'delay' => $request->delay ?? 1200,
            ];

            if ($request->has('caption')) $payload['caption'] = $request->caption;
            if ($request->has('fileName')) $payload['fileName'] = $request->fileName;
            if ($request->has('quoted')) $payload['quoted'] = $request->quoted;
            if ($request->has('mentionsEveryOne')) $payload['mentionsEveryOne'] = $request->mentionsEveryOne;
            if ($request->has('mentioned')) $payload['mentioned'] = $request->mentioned;

            $response = Http::withHeaders([
                'Content-Type' => 'application/json',
                'apikey' => $evolutionConfig['api_key'],
            ])->post($serverUrl, $payload);

            if ($response->successful()) {
                Log::info('WhatsApp media sent successfully', [
                    'instance' => $instance->name,
                    'phone' => $request->phone
                ]);

                // Crear mensaje en la base de datos
                $content = $request->caption ?? "Media de tipo: {$request->mediatype}";
                $this->createSystemMessage(
                    $conversation->id,
                    $content,
                    $request->user()->id,
                    $request->mediatype
                );

                return response()->json([
                    'message' => 'Mensaje con media enviado con éxito'
                ]);
            } else {
                Log::error('Failed to send WhatsApp media', [
                    'instance' => $instance->name,
                    'phone' => $request->phone,
                    'response' => $response->body()
                ]);

                return response()->json([
                    'message' => $this->formatErrorMessage($response->body())
                ], $response->status());
            }
        } catch (\Exception $e) {
            Log::error('Exception while sending WhatsApp media', [
                'instance' => $instance->name,
                'phone' => $request->phone,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'message' => 'Error al enviar el mensaje con media'
            ], 500);
        }
    }

    public function sendAudio(Request $request)
    {
        $request->validate([
            'instance_id' => 'nullable|exists:instances,id',
            'phone' => 'required|string',
            'audio' => 'required|string',
            'delay' => 'nullable|integer|min:0',
            'encoding' => 'nullable|boolean',
            'quoted' => 'nullable|array',
            'mentionsEveryOne' => 'nullable|boolean',
            'mentioned' => 'nullable|array'
        ]);

        // Encontrar o crear conversación
        $conversation = $this->findOrCreateConversation($request, $request->phone);

        $instance = $this->getUserInstance($request, $request->instance_id);

        if (!$instance) {
            return response()->json([
                'message' => 'No hay instancias disponibles'
            ], 404);
        }

        if ($instance === 'multiple') {
            return response()->json([
                'message' => 'No se ha seleccionado una instancia'
            ], 400);
        }

        $evolutionConfig = $this->getEvolutionConfig();
        $serverUrl = $evolutionConfig['base_url'] . '/message/sendWhatsAppAudio/' . $instance->name;

        try {
            $payload = [
                'number' => $request->phone,
                'audio' => $request->audio,
                'delay' => $request->delay ?? 1200,
            ];

            if ($request->has('encoding')) $payload['encoding'] = $request->encoding;
            if ($request->has('quoted')) $payload['quoted'] = $request->quoted;
            if ($request->has('mentionsEveryOne')) $payload['mentionsEveryOne'] = $request->mentionsEveryOne;
            if ($request->has('mentioned')) $payload['mentioned'] = $request->mentioned;

            $response = Http::withHeaders([
                'Content-Type' => 'application/json',
                'apikey' => $evolutionConfig['api_key'],
            ])->post($serverUrl, $payload);

            if ($response->successful()) {
                Log::info('WhatsApp audio sent successfully', [
                    'instance' => $instance->name,
                    'phone' => $request->phone
                ]);

                // Crear mensaje en la base de datos
                $this->createSystemMessage(
                    $conversation->id,
                    'Mensaje de audio',
                    $request->user()->id,
                    'audio'
                );

                return response()->json([
                    'message' => 'Mensaje de audio enviado con éxito'
                ]);
            } else {
                Log::error('Failed to send WhatsApp audio', [
                    'instance' => $instance->name,
                    'phone' => $request->phone,
                    'response' => $response->body()
                ]);

                return response()->json([
                    'message' => $this->formatErrorMessage($response->body())
                ], $response->status());
            }
        } catch (\Exception $e) {
            Log::error('Exception while sending WhatsApp audio', [
                'instance' => $instance->name,
                'phone' => $request->phone,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'message' => 'Error al enviar el mensaje de audio'
            ], 500);
        }
    }
}