<?php

namespace App\Services;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use App\Models\Configuration;

class TranscriptionService
{
    private $groqApiKey;

    public function __construct()
    {
        $this->groqApiKey = Configuration::where('key', 'groq-api-token')->value('value');
    }

    public function transcribe(string $audioUrl)
    {
        try {
            Log::info('Iniciando transcripción de audio con Groq', ['url' => $audioUrl]);

            $response = Http::withHeaders([
                'Authorization' => "Bearer {$this->groqApiKey}",
            ])->attach(
                'file', file_get_contents($audioUrl), 'audio.ogg'
            )->post('https://api.groq.com/openai/v1/audio/transcriptions', [
                'model' => 'whisper-large-v3-turbo',
                'temperature' => 0,
                'response_format' => 'json',
                'language' => 'es',
            ]);

            if ($response->successful()) {
                $responseData = $response->json();
                $transcription = $this->extractTranscription($responseData);
                
                if ($transcription) {
                    Log::info('Transcripción completada con Groq', ['transcription' => $transcription]);
                    return $transcription;
                } else {
                    Log::error('No se pudo extraer la transcripción de la respuesta de Groq', ['response' => $responseData]);
                    return null;
                }
            } else {
                Log::error('Error en la respuesta de Groq', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
                return null;
            }
        } catch (\Exception $e) {
            Log::error('Error en la transcripción con Groq', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return null;
        }
    }

    private function extractTranscription($responseData)
    {
        if (is_array($responseData) && !empty($responseData) && isset($responseData[0]['text'])) {
            return $responseData[0]['text'];
        } elseif (is_array($responseData) && isset($responseData['text'])) {
            return $responseData['text'];
        } elseif (is_object($responseData) && isset($responseData->text)) {
            return $responseData->text;
        } else {
            return null;
        }
    }
}