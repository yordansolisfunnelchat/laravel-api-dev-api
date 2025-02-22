<?php

namespace App\Imports;

use App\Models\Product;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;
use Illuminate\Support\Facades\Log;

class ProductsImport implements ToModel, WithHeadingRow, WithValidation
{
    private $columnMapping;

    public function __construct(array $columnMapping)
    {
        // Normalizar las claves del mapeo (eliminar espacios y convertir a minúsculas)
        $this->columnMapping = array_map(function($column) {
            return $this->normalizeColumnName($column);
        }, $columnMapping);

        Log::info('Mapeo de columnas configurado:', [
            'original_mapping' => $columnMapping,
            'normalized_mapping' => $this->columnMapping
        ]);
    }

    private function normalizeColumnName($name)
    {
        // Eliminar espacios y convertir a minúsculas
        return str_replace(' ', '_', strtolower(trim($name)));
    }

    private function getValueFromRow($row, $fieldName)
    {
        // Obtener el nombre de la columna mapeada
        $mappedColumn = $this->columnMapping[$fieldName] ?? null;
        
        if (!$mappedColumn) {
            Log::warning("No se encontró mapeo para el campo: {$fieldName}");
            return null;
        }

        // Normalizar el nombre de la columna
        $normalizedColumn = $this->normalizeColumnName($mappedColumn);

        // Buscar el valor en el row usando diferentes variaciones del nombre
        $variations = [
            $mappedColumn,                    // Original
            $normalizedColumn,                // Normalizado
            strtolower($mappedColumn),        // Minúsculas
            str_replace(' ', '_', $mappedColumn), // Con guiones bajos
        ];

        foreach ($variations as $columnName) {
            if (isset($row[$columnName])) {
                return $row[$columnName];
            }
        }

        Log::warning("No se encontró valor para el campo {$fieldName}", [
            'mapped_column' => $mappedColumn,
            'variations_tried' => $variations,
            'available_columns' => array_keys($row)
        ]);

        return null;
    }

    public function model(array $row)
    {
        Log::info('Procesando fila', [
            'row_data' => $row,
            'available_columns' => array_keys($row)
        ]);

        // Obtener valores usando el método mejorado
        $name = $this->getValueFromRow($row, 'name');
        $description = $this->getValueFromRow($row, 'description');
        $priceRaw = $this->getValueFromRow($row, 'price');
        $currency = $this->getValueFromRow($row, 'currency');
        $imagesRaw = $this->getValueFromRow($row, 'images');
        $externalLink = $this->getValueFromRow($row, 'external_link');

        // Procesar precio
        $price = str_replace(['$', ','], '', $priceRaw);
        $price = (float) preg_replace('/[^0-9.]/', '', $price);

        // Procesar imágenes
        $images = [];
        if (!empty($imagesRaw)) {
            $images = array_map('trim', explode(',', $imagesRaw));
        }

        // Validar y establecer moneda
        $currency = strtoupper(trim($currency ?? 'USD'));
        if (!in_array($currency, ['USD', 'COP'])) {
            $currency = 'USD';
        }

        Log::info('Creando producto', [
            'name' => $name,
            'price' => $price,
            'currency' => $currency
        ]);

        return new Product([
            'user_id' => auth()->id(),
            'name' => $name,
            'description' => $description,
            'price' => $price,
            'currency' => $currency,
            'images' => $images,
            'external_link' => $externalLink,
            'is_default' => false,
        ]);
    }

    public function rules(): array
    {
        Log::info('Validando con mapeo:', $this->columnMapping);

        return [
            $this->normalizeColumnName($this->columnMapping['name']) => 'required',
            $this->normalizeColumnName($this->columnMapping['price']) => 'required',
            $this->normalizeColumnName($this->columnMapping['currency']) => 'required',
        ];
    }

    public function customValidationMessages()
    {
        $nameColumn = $this->columnMapping['name'];
        $priceColumn = $this->columnMapping['price'];
        $currencyColumn = $this->columnMapping['currency'];

        return [
            "{$nameColumn}.required" => 'El nombre del producto es requerido',
            "{$priceColumn}.required" => 'El precio es requerido',
            "{$currencyColumn}.required" => 'La moneda es requerida (USD o COP)',
        ];
    }
}