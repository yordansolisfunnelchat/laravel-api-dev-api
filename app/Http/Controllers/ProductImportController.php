<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
use App\Imports\ProductsImport;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

class ProductImportController extends Controller
{
    private function ensureDirectoryExists()
    {
        $tempPath = storage_path('app/temp');
        if (!file_exists($tempPath)) {
            mkdir($tempPath, 0775, true);
        }
        chmod($tempPath, 0775);
        return $tempPath;
    }

    private function isValidFileType($file)
    {
        $extension = strtolower($file->getClientOriginalExtension());
        $mimeType = $file->getMimeType();
        $validExtensions = ['csv', 'xlsx', 'xls'];
        $validMimeTypes = [
            'text/csv',
            'text/plain',                     // Algunos CSV se detectan como text/plain
            'application/csv',
            'application/excel',
            'application/vnd.ms-excel',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ];

        return in_array($extension, $validExtensions) || in_array($mimeType, $validMimeTypes);
    }

    public function preview(Request $request)
    {
        try {
            $file = $request->file('file');

            Log::info('Iniciando preview de archivo', [
                'file_size' => $file->getSize(),
                'file_type' => $file->getMimeType(),
                'original_name' => $file->getClientOriginalName(),
                'extension' => $file->getClientOriginalExtension()
            ]);

            if (!$this->isValidFileType($file)) {
                return response()->json([
                    'error' => 'El archivo debe ser Excel (.xlsx, .xls) o CSV (.csv)'
                ], 422);
            }

            $tempPath = $this->ensureDirectoryExists();
            $fileName = time() . '_' . $file->getClientOriginalName();
            $fullPath = $tempPath . '/' . $fileName;
            
            $file->move($tempPath, $fileName);

            if (!file_exists($fullPath)) {
                Log::error('Archivo no encontrado después de mover', ['path' => $fullPath]);
                return response()->json(['error' => 'Error al guardar el archivo'], 500);
            }

            try {
                $data = Excel::toArray([], $fullPath);
                
                if (!isset($data[0]) || empty($data[0])) {
                    throw new \Exception('No se encontraron datos en el archivo');
                }

                $rows = $data[0];
                $headers = array_shift($rows);

                // Validar que hay datos después de los encabezados
                if (empty($headers)) {
                    throw new \Exception('El archivo no contiene encabezados');
                }

                // Limpiar espacios en blanco de los encabezados
                $headers = array_map('trim', $headers);

                Log::info('Archivo procesado correctamente', [
                    'headers' => $headers,
                    'total_rows' => count($rows)
                ]);

                // Limpiar archivo temporal
                @unlink($fullPath);

                return response()->json([
                    'headers' => $headers,
                    'sample_row' => $rows[0] ?? [],
                    'total_rows' => count($rows)
                ]);

            } catch (\Exception $e) {
                Log::error('Error al procesar Excel/CSV', [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);

                @unlink($fullPath);
                return response()->json(['error' => 'Error al procesar el archivo: ' . $e->getMessage()], 500);
            }

        } catch (\Exception $e) {
            Log::error('Error general en preview', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json(['error' => 'Error al procesar el archivo: ' . $e->getMessage()], 500);
        }
    }

    public function import(Request $request)
    {
        try {
            Log::info('Iniciando importación');

            if (!$this->isValidFileType($request->file('file'))) {
                return response()->json([
                    'error' => 'El archivo debe ser Excel (.xlsx, .xls) o CSV (.csv)'
                ], 422);
            }

            $request->validate([
                'column_mapping' => 'required|string'
            ]);

            $columnMapping = json_decode($request->column_mapping, true);
            
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new \Exception('Error en el mapeo de columnas: ' . json_last_error_msg());
            }

            // Validar que estén todos los campos requeridos
            $requiredFields = ['name', 'description', 'price', 'currency'];
            foreach ($requiredFields as $field) {
                if (empty($columnMapping[$field])) {
                    throw new \Exception("El campo {$field} es requerido para la importación");
                }
            }

            $tempPath = $this->ensureDirectoryExists();
            $fileName = time() . '_import_' . $request->file('file')->getClientOriginalName();
            $fullPath = $tempPath . '/' . $fileName;
            
            $request->file('file')->move($tempPath, $fileName);

            try {
                $import = new ProductsImport($columnMapping);
                Excel::import($import, $fullPath);

                Log::info('Importación completada exitosamente');
                
                return response()->json([
                    'success' => true,
                    'message' => 'Productos importados exitosamente'
                ]);

            } catch (\Maatwebsite\Excel\Validators\ValidationException $e) {
                $errors = collect($e->failures())
                    ->map(fn($failure) => "Fila {$failure->row()}: " . implode(', ', $failure->errors()))
                    ->join("\n");

                throw new \Exception("Errores de validación:\n" . $errors);

            } finally {
                if (file_exists($fullPath)) {
                    @unlink($fullPath);
                }
            }

        } catch (\Exception $e) {
            Log::error('Error en la importación', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Error al importar productos: ' . $e->getMessage()
            ], 500);
        }
    }
}