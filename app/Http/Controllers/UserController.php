<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Instance;
use App\Services\EvolutionApiService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class UserController extends Controller
{
    public function __construct(
        private EvolutionApiService $evolutionApi
    ) {}

    public function deactivate(User $user): JsonResponse
    {
        try {
            DB::beginTransaction();

            // Obtener instancia del usuario
            $instance = Instance::where('user_id', $user->id)->first();

            if ($instance) {
                // 1. Intentar desconectar instancia, pero no fallar si ya está desconectada
                $disconnected = $this->evolutionApi->disconnectInstance($instance);
                if (!$disconnected) {
                    Log::info('La instancia posiblemente ya está desconectada', ['instance' => $instance->name]);
                }

                // 2. Eliminar instancia en Evolution API
                $deleted = $this->evolutionApi->deleteInstance($instance);
                if (!$deleted) {
                    throw new \Exception('Error al eliminar la instancia');
                }

                // 3. Cambiar estado de la instancia a inactive
                $instance->update(['status' => 'inactive']);
            }

            // 4. Cambiar estado del usuario a inactive
            $user->update(['status' => 'inactive']);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Usuario e instancia desactivados correctamente'
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Error al desactivar usuario: {$e->getMessage()}", [
                'user_id' => $user->id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al desactivar usuario',
                'error' => $e->getMessage()
            ], 500);
        }
    }
} 