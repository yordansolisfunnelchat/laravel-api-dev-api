<?php
# MessageSenderService.php
namespace App\Services;

use App\Models\Instance;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;

class MessageSenderService
{
    // private $evolutionApiBaseUrl;
    // private $evolutionApiKey;

    private $evolutionApiBaseUrl;
    private const CACHE_TTL = 3600; // 1 hora de caché

    public function __construct()
    {
        $this->evolutionApiBaseUrl = 'https://ezcala-ai-evolution-api.3bx9yv.easypanel.host';
        $this->evolutionApiKey = '0A4A5D1011E0-4713-AE53-CB564044BE78';
        
        $this->evolutionApiBaseUrl = config('services.evolution_api.base_url', 
        'https://ezcala-ai-evolution-api.3bx9yv.easypanel.host');

    }

    // public function sendMessage(Instance $instance, $recipientPhone, $content, $hasWaitingTime = true)
    public function sendMessage($instance, $recipientPhone, $content, $hasWaitingTime = true)

    {
        Log::info('📤 Iniciando envío de mensaje', [
            'instance' => $instance->name,
            'recipient' => $recipientPhone,
            'content_length' => strlen($content)
        ]);

        if (empty($this->evolutionApiBaseUrl) || empty($this->evolutionApiKey)) {
            Log::error('❌ Configuración de Evolution API incompleta');
            return false;
        }

        $url = "{$this->evolutionApiBaseUrl}/message/sendText/{$instance->name}";
        
        $delay = $hasWaitingTime ? 1 : 0;
        // Asegurarse de que el número de teléfono tenga el formato correcto
        $formattedPhone = $this->formatPhoneNumber($recipientPhone);
        // Formato simplificado que espera la API
        $payload = [
            'number' => $recipientPhone,
            'text' => $content,
            'delay' => $delay
        ];

        return $this->sendRequest($url, $payload);
    }

    private function sendRequest($url, $payload)
    {
        Log::info(' 🚛 Enviando petición a Evolution API', [
            'url' => $url,
            'payload' => $payload
        ]);

        try {
            $response = Http::timeout(60)
                ->withHeaders([
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json',
                    'apikey' => trim($this->evolutionApiKey) // Aseguramos que no haya espacios
                ])
                ->post($url, $payload);

            Log::info('📩 Respuesta recibida de Evolution API', [
                'status' => $response->status(),
                'body' => $response->json()
            ]);

            if (!$response->successful()) {
                Log::error('❌ Error en la respuesta de Evolution API', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                    'url' => $url
                ]);
                return false;
            }

            Log::info('✅ Mensaje enviado exitosamente');
            return true;

        } catch (\Exception $e) {
            Log::error('❌ Excepción al enviar mensaje', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return false;
        }
    }

    private function formatPhoneNumber($phone)
{
    // Eliminar cualquier carácter que no sea número
    $cleanPhone = preg_replace('/[^0-9]/', '', $phone);
    
    // Si el número no empieza con el código del país, agregar 57 (Colombia)
    if (strlen($cleanPhone) === 10) {
        $cleanPhone = "57" . $cleanPhone;
    }
    
    Log::info('📱 Formato de número de teléfono', [
        'original' => $phone,
        'formatted' => $cleanPhone
    ]);
    
    return $cleanPhone;
}
}

// namespace App\Services; 
// use App\Models\Instance;
// use App\Models\Configuration;
// use Illuminate\Support\Facades\Log;
// use Illuminate\Support\Facades\Http;
// use App\Models\Agent;

// class MessageSenderService
// {
//     private $evolutionApiBaseUrl;
//     private $evolutionApiKey;

//     public function __construct()
//     {
//         // $this->evolutionApiBaseUrl = Configuration::where('key', 'evolution_api_base_url')->value('value');
//         // $this->evolutionApiKey = Configuration::where('key', 'evolution_api_key')->value('value');
//         // https://9c6c-191-97-6-129.ngrok-free.app/api/webhook/evolution-api
//         $this->evolutionApiBaseUrl = 'https://ezcala-ai-evolution-api.3bx9yv.easypanel.host';
//         $this->evolutionApiKey = '104E77A69EB3-48E5-B4C1-F32449335401';

//     }

//     public function sendMessage(Instance $instance, $recipientPhone, $content, $hasWaitingTime = true)
//     {
//         Log::info('📤 Iniciando envío de mensaje', [
//             'instance' => $instance->name,
//             'recipient' => $recipientPhone,
//             'content_length' => strlen($content)
//         ]);

//         if (empty($this->evolutionApiBaseUrl) || empty($this->evolutionApiKey)) {
//             Log::error('❌ Configuración de Evolution API incompleta', [
//                 'base_url_exists' => !empty($this->evolutionApiBaseUrl),
//                 'api_key_exists' => !empty($this->evolutionApiKey)
//             ]);
//             return false;
//         }


//         $url = "{$this->evolutionApiBaseUrl}/message/sendText/{$instance->name}";
//         Log::info('🔗 URL generada', ['url' => $url]);

//         $delay = 0;
//         if ($hasWaitingTime === true) {
//             // $delay = 5000;
//             $delay = 1;
//         }

//         Log::info('Message configuration', [
//             'has_waiting_time' => $hasWaitingTime,
//             'delay_set' => $delay,
//             'waiting_time_type' => gettype($hasWaitingTime)
//         ]);

//         $formattedPhone = $this->formatPhoneNumber($recipientPhone);
//         if (!$formattedPhone) {
//             Log::error('❌ Número de teléfono no válido', ['phone' => $recipientPhone]);
//             return false;
//         }
        
//         $payload = [
//             'number' => $recipientPhone,
//             'text' => $content,
//             'delay' => $delay,
//             // 'linkPreview' => false,
//             // 'mentionsEveryOne' => false,
//         ];

//         return $this->sendRequest($url, $payload);
//     }


//     private function formatPhoneNumber($phone)
//     {
//         // Eliminar cualquier carácter que no sea número
//         $cleanPhone = preg_replace('/[^0-9]/', '', $phone);
        
//         Log::info('📱 Formato de número de teléfono', [
//             'original' => $phone,
//             'formatted' => $cleanPhone
//         ]);
        
//         return $cleanPhone;
//     }





//     public function sendImage(Instance $instance, $recipientPhone, $imageUrl, $caption = '')
//     {
//         $url = "{$this->evolutionApiBaseUrl}/message/sendMedia/{$instance->name}";
        
//         $payload = [
//             'number' => $recipientPhone,
//             'mediatype' => 'image',
//             'media' => $imageUrl,
//             'caption' => $caption,
//             'delay' => 1200,
//             'mentionsEveryOne' => false,
//         ];

//         return $this->sendRequest($url, $payload);
//     }

//     public function sendDocument(Instance $instance, $recipientPhone, $documentUrl, $fileName)
//     {
//         $url = "{$this->evolutionApiBaseUrl}/message/sendMedia/{$instance->name}";
        
//         $payload = [
//             'number' => $recipientPhone,
//             'mediatype' => 'document',
//             'media' => $documentUrl,
//             'fileName' => $fileName,
//             'delay' => 1200,
//             'mentionsEveryOne' => false,
//         ];

//         return $this->sendRequest($url, $payload);
//     }

//     public function sendAudio(Instance $instance, $recipientPhone, $audioUrl)
//     {
//         $url = "{$this->evolutionApiBaseUrl}/message/sendWhatsAppAudio/{$instance->name}";
        
//         $payload = [
//             'number' => $recipientPhone,
//             'audio' => $audioUrl,
//             'delay' => 1200,
//             'encoding' => true,
//             'mentionsEveryOne' => false,
//         ];

//         return $this->sendRequest($url, $payload);
//     }


//     private function sendRequest($url, $payload)
//     {
//         Log::info('🚀 Enviando petición a Evolution API', [
//             'url' => $url,
//             'payload' => $payload
//         ]);

//         try {
//             $response = Http::timeout(30)
//                 ->withHeaders([
//                     'Content-Type' => 'application/json',
//                     'apikey' => $this->evolutionApiKey
//                 ])
//                 ->post($url, $payload);

//             Log::info('📩 Respuesta recibida de Evolution API', [
//                 'status' => $response->status(),
//                 'body' => $response->json(),
//                 'headers' => $response->headers()
//             ]);

//             if (!$response->successful()) {
//                 Log::error('❌ Error en la respuesta de Evolution API', [
//                     'status' => $response->status(),
//                     'body' => $response->body(),
//                     'url' => $url
//                 ]);
//                 return false;
//             }

//             Log::info('✅ Mensaje enviado exitosamente');
//             return true;

//         } catch (\Exception $e) {
//             Log::error('❌ Excepción al enviar mensaje', [
//                 'error' => $e->getMessage(),
//                 'trace' => $e->getTraceAsString(),
//                 'url' => $url
//             ]);
//             return false;
//         }
//     }
// }