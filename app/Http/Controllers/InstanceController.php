<?php
namespace App\Http\Controllers;

use App\Models\WhatsappInstance;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;

class WhatsAppInstanceController extends Controller
{
    /**
     * Crear una nueva instancia de WhatsApp
     */
    public function store(Request $request)
    {
        Log::info('Iniciando proceso de registro de instancia WhatsApp', [
            'timestamp' => now(),
            'request_data' => $request->all()
        ]);

        try {
            // 1. Validar datos
            $validatedData = $this->validateInstanceData($request);

            // 2. Verificar duplicados
            $this->checkForDuplicates($validatedData);

            // 3. Registrar la instancia en nuestra BD
            $instance = $this->createInstance($validatedData);

            // 4. Verificar la conexión con Evolution API
            $evolutionApiStatus = $this->verifyEvolutionApiInstance($instance);

            return response()->json([
                'status' => 'success',
                'message' => 'Instancia registrada exitosamente',
                'data' => [
                    'instance' => $instance,
                    'evolution_api_status' => $evolutionApiStatus
                ]
            ], 201);

        } catch (\Exception $e) {
            Log::error('Error al registrar instancia WhatsApp', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Validar datos de la instancia
     */
    private function validateInstanceData(Request $request)
    {
        return $request->validate([
            'instance_name' => 'required|string|max:255',
            'instance_key' => 'required|string|max:255',
            'status' => 'sometimes|in:active,inactive,connecting,disconnected'
        ]);
    }

    /**
     * Verificar duplicados
     */
    private function checkForDuplicates($data)
    {
        $exists = WhatsappInstance::where('instance_name', $data['instance_name'])
            ->orWhere('instance_key', $data['instance_key'])
            ->exists();

        if ($exists) {
            throw new \Exception('Ya existe una instancia con este nombre o clave');
        }
    }

    /**
     * Crear instancia en la base de datos
     */
    private function createInstance($data)
    {
        $userId = auth()->id(); // Asumiendo que usas autenticación de Laravel

        return WhatsappInstance::create([
            'user_id' => $userId,
            'instance_name' => $data['instance_name'],
            'instance_key' => $data['instance_key'],
            'status' => $data['status'] ?? 'inactive'
        ]);
    }

    /**
     * Verificar la instancia en Evolution API
     */
    private function verifyEvolutionApiInstance($instance)
    {
        $evolutionApiBaseUrl = config('services.evolution_api.base_url');
        $url = "{$evolutionApiBaseUrl}/instance/info/{$instance->instance_name}";

        try {
            $response = Http::timeout(30)
                ->withHeaders([
                    'apikey' => $instance->instance_key
                ])
                ->get($url);

            if ($response->successful()) {
                $instance->update(['status' => 'active']);
                return $response->json();
            }

            $instance->update(['status' => 'disconnected']);
            return ['error' => 'No se pudo conectar con la instancia'];

        } catch (\Exception $e) {
            Log::error('Error al verificar instancia en Evolution API', [
                'instance' => $instance->instance_name,
                'error' => $e->getMessage()
            ]);

            $instance->update(['status' => 'disconnected']);
            return ['error' => $e->getMessage()];
        }
    }

    /**
     * Listar todas las instancias del usuario
     */
    public function index()
    {
        $userId = auth()->id();
        $instances = WhatsappInstance::where('user_id', $userId)->get();

        return response()->json([
            'status' => 'success',
            'data' => $instances
        ]);
    }

    /**
     * Obtener una instancia específica
     */
    public function show($id)
    {
        $instance = WhatsappInstance::findOrFail($id);
        
        // Verificar que el usuario tiene acceso a esta instancia
        if ($instance->user_id !== auth()->id()) {
            return response()->json([
                'status' => 'error',
                'message' => 'No autorizado'
            ], 403);
        }

        return response()->json([
            'status' => 'success',
            'data' => $instance
        ]);
    }

    /**
     * Actualizar una instancia
     */
    public function update(Request $request, $id)
    {
        $instance = WhatsappInstance::findOrFail($id);

        // Verificar autorización
        if ($instance->user_id !== auth()->id()) {
            return response()->json([
                'status' => 'error',
                'message' => 'No autorizado'
            ], 403);
        }

        try {
            $validatedData = $request->validate([
                'instance_key' => 'sometimes|string|max:255',
                'status' => 'sometimes|in:active,inactive,connecting,disconnected'
            ]);

            // Si se está actualizando la clave, guardar el historial
            if (isset($validatedData['instance_key']) && $validatedData['instance_key'] !== $instance->instance_key) {
                \DB::table('whatsapp_instance_keys_history')->insert([
                    'instance_id' => $instance->id,
                    'old_key' => $instance->instance_key,
                    'new_key' => $validatedData['instance_key'],
                    'changed_by' => auth()->id(),
                    'changed_at' => now()
                ]);
            }

            $instance->update($validatedData);

            return response()->json([
                'status' => 'success',
                'message' => 'Instancia actualizada exitosamente',
                'data' => $instance
            ]);

        } catch (\Exception $e) {
            Log::error('Error al actualizar instancia', [
                'instance_id' => $id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ], 500);
        }
    }
}