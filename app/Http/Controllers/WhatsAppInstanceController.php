<?php

namespace App\Http\Controllers;

use App\Models\EvolutionApiConfig; // Asegúrate de usar el modelo correcto
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class EvolutionApiValueController extends Controller
{


    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'instance_name' => 'required|string',
            'instance_key' => 'required|string'
        ]);
    
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Error de validación',
                'errors' => $validator->errors()
            ], 422);
        }
    
        try {
            // Verificar si ya existe una instancia con ese nombre
            if (Configuration::where('key', $request->instance_name)->exists()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Ya existe una instancia con ese nombre'
                ], 422);
            }
            
            // Guardar la configuración
            Configuration::create([
                'key' => $request->instance_name,
                'value' => $request->instance_key
            ]);
            
            return response()->json([
                'success' => true,
                'message' => 'Configuración creada exitosamente',
                'data' => [
                    'instance_name' => $request->instance_name,
                    'instance_key' => $request->instance_key
                ]
            ], 201);
    
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al crear la configuración',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtener todas las configuraciones de WhatsApp
     */
    public function index()
    {
        try {
            $configs = Configuration::all();
            
            $instances = $configs->map(function ($config) {
                return [
                    'instance_name' => $config->key,
                    'instance_key' => $config->value
                ];
            });
            
            return response()->json([
                'success' => true,
                'data' => $instances
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener las configuraciones',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtener una configuración específica
     */

    public function show($user_id)
    {
        try {
            // Buscar la instancia por su ID
            $instance = Instance::where('id', $user_id)
                ->where('user_id', Auth::id())
                ->first();

            if (!$instance) {
                return response()->json([
                    'success' => false,
                    'message' => 'Instancia no encontrada'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => $instance
            ]);
        } catch (\Exception $e) {
            Log::error('Error al obtener la instancia', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al obtener la instancia',
                'error' => config('app.debug') ? $e->getMessage() : 'Error interno del servidor'
            ], 500);
        }
    }

    /**
     * Actualizar una configuración
      */

/**
 * Actualizar una instancia
 *
 * @param Request $request
 * @param int $instance_id
 * @return \Illuminate\Http\JsonResponse
 */
public function update(Request $request, $instance_id)
{
    $validator = Validator::make($request->all(), [
        'name' => 'sometimes|string|max:255'
    ]);

    if ($validator->fails()) {
        return response()->json([
            'success' => false,
            'message' => 'Error de validación',
            'errors' => $validator->errors()
        ], 422);
    }

    try {
        // Buscar la instancia por ID y asegurar que pertenece al usuario autenticado
        $instance = Instance::where('id', $instance_id)
            ->where('user_id', Auth::id())
            ->first();

        if (!$instance) {
            return response()->json([
                'success' => false,
                'message' => 'Instancia no encontrada'
            ], 404);
        }
        
        // Actualizar solo los campos proporcionados
        if ($request->has('name')) {
            $instance->name = $request->name;
        }
        
        // if ($request->has('phone_number')) {
        //     $instance->phone_number = $request->phone_number;
        // }
        
        // if ($request->has('status')) {
        //     $instance->status = $request->status;
        // }
        
        $instance->save();
        
        return response()->json([
            'success' => true,
            'message' => 'Instancia actualizada exitosamente',
            'data' => $instance
        ]);
    } catch (\Exception $e) {
        Log::error('Error al actualizar la instancia', [
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ]);
        
        return response()->json([
            'success' => false,
            'message' => 'Error al actualizar la instancia',
            'error' => config('app.debug') ? $e->getMessage() : 'Error interno del servidor'
        ], 500);
    }
}


    // public function update(Request $request, $instanceName)
    // {
    //     $validator = Validator::make($request->all(), [
    //         'instance_key' => 'required|string'
    //     ]);

    //     if ($validator->fails()) {
    //         return response()->json([
    //             'success' => false,
    //             'message' => 'Error de validación',
    //             'errors' => $validator->errors()
    //         ], 422);
    //     }

    //     try {
    //         $config = Configuration::where('key', $instanceName)->firstOrFail();
            
    //         // Guardar el historial antes de actualizar
    //         Configuration::create([
    //             'key' => $instanceName . '_history_' . now()->timestamp,
    //             'value' => json_encode([
    //                 'old_key' => $config->value,
    //                 'new_key' => $request->instance_key,
    //                 'changed_by' => auth()->id(),
    //                 'changed_at' => now()
    //             ])
    //         ]);
            
    //         // Actualizar la configuración
    //         $config->value = $request->instance_key;
    //         $config->save();

    //         return response()->json([
    //             'success' => true,
    //             'message' => 'Configuración actualizada exitosamente',
    //             'data' => [
    //                 'instance_name' => $config->key,
    //                 'instance_key' => $config->value
    //             ]
    //         ]);

    //     } catch (\Exception $e) {
    //         return response()->json([
    //             'success' => false,
    //             'message' => 'Error al actualizar la configuración',
    //             'error' => $e->getMessage()
    //         ], 500);
    //     }
    // }

    /**
     * Eliminar una configuración
     */
    public function destroy($id_key)
    {
        try {
            $config = Configuration::where('key', $id_key)->firstOrFail();
            $config->delete();

            // También eliminar el historial si existe
            Configuration::where('key', 'LIKE', $id_key . '_history_%')->delete();

            return response()->json([
                'success' => true,
                'message' => 'Configuración eliminada exitosamente'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al eliminar la configuración',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}