<?php
namespace App\Services;

use App\Models\WhatsappInstance; // Importamos el modelo para consultar la DB
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;

class MessageSenderService
{
    private $evolutionApiBaseUrl;
    private $evolutionApiKey; // Se asignará dinámicamente desde la base de datos
    private const CACHE_TTL = 3600; // 1 hora de caché

    public function __construct()
    {
        // Se obtiene la URL base de la API desde la configuración o se usa el valor por defecto.
        $this->evolutionApiBaseUrl = config('services.evolution_api.base_url', 'https://ezcala-ai-evolution-api.3bx9yv.easypanel.host');
    }

    /**
     * Envía un mensaje utilizando la Evolution API.
     *
     * @param object $instance Objeto que contiene al menos la propiedad "name"
     * @param string $recipientPhone Número del destinatario
     * @param string $content Contenido del mensaje
     * @param bool $hasWaitingTime Indica si se debe aplicar tiempo de espera
     * @return bool Resultado del envío
     */
    public function sendMessage($instance, $recipientPhone, $content, $hasWaitingTime = true)
    {
        Log::info('📤 Iniciando envío de mensaje', [
            'instance' => $instance->name,
            'recipient' => $recipientPhone,
            'content_length' => strlen($content)
        ]);

        // Se consulta la base de datos para obtener la instancia según su nombre.
        $whatsappInstance = WhatsappInstance::where('instance_name', $instance->name)->first();

        // Si no se encuentra o no tiene clave definida, se detiene el envío.
        if (!$whatsappInstance || empty($whatsappInstance->instance_key)) {
            Log::error('La instancia no tiene clave API definida. No se puede enviar el mensaje.');
            return false;
        }

        // Se asigna la clave API obtenida dinámicamente.
        $this->evolutionApiKey = $whatsappInstance->instance_key;

        // Verificar que tanto la URL base como la clave API estén definidas.
        if (empty($this->evolutionApiBaseUrl) || empty($this->evolutionApiKey)) {
            Log::error('❌ Configuración de Evolution API incompleta');
            return false;
        }

        $url = "{$this->evolutionApiBaseUrl}/message/sendText/{$instance->name}";
        $delay = $hasWaitingTime ? 1 : 0;
        $formattedPhone = $this->formatPhoneNumber($recipientPhone);

        $payload = [
            'number' => $formattedPhone,
            'text'   => $content,
            'delay'  => $delay
        ];

        return $this->sendRequest($url, $payload);
    }

    private function sendRequest($url, $payload)
    {
        Log::info('🚛 Enviando petición a Evolution API', [
            'url' => $url,
            'payload' => $payload
        ]);

        try {
            $response = Http::timeout(60)
                ->withHeaders([
                    'Content-Type' => 'application/json',
                    'Accept'       => 'application/json',
                    'apikey'       => trim($this->evolutionApiKey)
                ])
                ->post($url, $payload);

            Log::info('📩 Respuesta recibida de Evolution API', [
                'status' => $response->status(),
                'body'   => $response->json()
            ]);

            if (!$response->successful()) {
                Log::error('❌ Error en la respuesta de Evolution API', [
                    'status' => $response->status(),
                    'body'   => $response->body(),
                    'url'    => $url
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
        // Se eliminan todos los caracteres que no sean dígitos.
        $cleanPhone = preg_replace('/[^0-9]/', '', $phone);
        
        // Si el número tiene 10 dígitos, se le agrega el código de país (57 para Colombia).
        if (strlen($cleanPhone) === 10) {
            $cleanPhone = "57" . $cleanPhone;
        }
        
        Log::info('📱 Formato de número de teléfono', [
            'original'  => $phone,
            'formatted' => $cleanPhone
        ]);
        
        return $cleanPhone;
    }
}
