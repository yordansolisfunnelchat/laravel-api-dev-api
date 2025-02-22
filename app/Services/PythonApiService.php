<?php
# PythonApiService.php
namespace App\Services;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PythonApiService
{
    protected $baseUrl;


    /// ---------------------
    protected $maxRetries = 3;  // Número máximo de reintentos
    protected $retryDelay = 2;  // Segundos entre reintentos
    protected $timeout = 90;    // Timeout en segundos
   /// ---------------------

    public function __construct()
    {
        // La URL base de tu API Python. Asegúrate de agregar esto en tu archivo .env
        $this->baseUrl = env('PYTHON_SERVICE_URL', 'http://127.0.0.1:7070');
    }

    /**
     * Envía un mensaje a la API de Python
     * 
     * @param array $data Los datos a enviar
     * @return array|null La respuesta de la API o null si hay error
     */
    public function sendMessage($data)
    {
        try {
            Log::info('🚀 Enviando mensaje a API Python', [
                'data' => $data,
                'url' => "{$this->baseUrl}/api/v1/whatsapp/webhook"
                // 'url' => "{$this->baseUrl}/api/v1/webhook/whatsapp"
            ]);
            
            $response = Http::timeout(60)
                ->post("{$this->baseUrl}api/v1/whatsapp/webhook", $data);

            if (!$response->successful()) {
                Log::error('❌ Error en respuesta de API Python', [
                    'status' => $response->status(),
                    'body' => $response->json()
                ]);
                return null;
            }

            Log::info('✅ Respuesta recibida de API Python', [
                'response' => $response->json()
            ]);

            return $response->json();

        } catch (\Exception $e) {
            Log::error('❌ Error comunicándose con API Python', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return null;
        }
    }
}