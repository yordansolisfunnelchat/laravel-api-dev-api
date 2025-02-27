<?php

namespace App\Http\Controllers;

use App\Models\Configuration;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Validation\ValidatesRequests;

class EvolutionApiValueController extends BaseController
{
    // use AuthorizesRequests, ValidatesRequests;

    // public function __construct()
    // {
    //     $this->middleware('auth:sanctum');
    // }

    /**
     * Crear una nueva configuración de WhatsApp
     */
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
    public function show($instanceName)
    {
        try {
            $config = Configuration::where('key', $instanceName)->firstOrFail();
            
            return response()->json([
                'success' => true,
                'data' => [
                    'instance_name' => $config->key,
                    'instance_key' => $config->value
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Configuración no encontrada',
                'error' => $e->getMessage()
            ], 404);
        }
    }

    /**
     * Actualizar una configuración
     */
    public function update(Request $request, $instanceName)
    {
        $validator = Validator::make($request->all(), [
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
            $config = Configuration::where('key', $instanceName)->firstOrFail();
            
            // Guardar el historial antes de actualizar
            Configuration::create([
                'key' => $instanceName . '_history_' . now()->timestamp,
                'value' => json_encode([
                    'old_key' => $config->value,
                    'new_key' => $request->instance_key,
                    'changed_by' => auth()->id(),
                    'changed_at' => now()
                ])
            ]);
            
            // Actualizar la configuración
            $config->value = $request->instance_key;
            $config->save();

            return response()->json([
                'success' => true,
                'message' => 'Configuración actualizada exitosamente',
                'data' => [
                    'instance_name' => $config->key,
                    'instance_key' => $config->value
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al actualizar la configuración',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Eliminar una configuración
     */
    public function destroy($instanceName)
    {
        try {
            $config = Configuration::where('key', $instanceName)->firstOrFail();
            $config->delete();

            // También eliminar el historial si existe
            Configuration::where('key', 'LIKE', $instanceName . '_history_%')->delete();

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