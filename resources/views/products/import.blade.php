<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Importar Productos</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <meta name="csrf-token" content="{{ csrf_token() }}">
</head>
<body class="bg-gray-100">
    <div class="container mx-auto px-4 py-8">
        <div class="bg-white rounded-lg shadow-md p-6">
            <div class="flex justify-between items-center mb-6">
                <h1 class="text-2xl font-bold">Importar Productos</h1>
                <a href="/" class="text-blue-500 hover:text-blue-700">Volver al Panel</a>
            </div>

            <div class="mb-6">
                <div class="bg-yellow-50 border-l-4 border-yellow-400 p-4">
                    <div class="flex">
                        <div class="flex-shrink-0">
                            <svg class="h-5 w-5 text-yellow-400" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
                            </svg>
                        </div>
                        <div class="ml-3">
                            <h3 class="text-sm leading-5 font-medium text-yellow-800">
                                Formato del archivo
                            </h3>
                            <div class="mt-2 text-sm leading-5 text-yellow-700">
                                <p>El archivo debe contener las siguientes columnas:</p>
                                <ul class="list-disc list-inside mt-1">
                                    <li>Nombre del producto</li>
                                    <li>Descripción</li>
                                    <li>Precio (solo números)</li>
                                    <li>Moneda (USD o COP)</li>
                                    <li>Enlaces de imágenes (separados por comas)</li>
                                    <li>Enlace externo (opcional)</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div id="alertSuccess" class="hidden bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4"></div>
            <div id="alertError" class="hidden bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4"></div>

            <div class="mb-4">
                <label class="block text-gray-700 text-sm font-bold mb-2">
                    Archivo Excel/CSV
                </label>
                <input type="file" id="fileInput" accept=".xlsx,.xls,.csv" 
                       class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
            </div>

            <div id="mappingSection" class="hidden mb-4">
                <h3 class="font-bold mb-2">Mapear Campos</h3>
                <div class="grid grid-cols-2 gap-4" id="mappingFields"></div>
            </div>

            <div class="flex items-center justify-between">
                <button onclick="importProducts()" class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline">
                    Importar
                </button>
            </div>
        </div>
    </div>

    <script>
        const requiredFields = [
            { id: 'name', label: 'Nombre del Producto', required: true },
            { id: 'description', label: 'Descripción', required: true },
            { id: 'price', label: 'Precio', required: true },
            { id: 'currency', label: 'Moneda', required: true },
            { id: 'images', label: 'Enlaces de Imágenes', required: false },
            { id: 'external_link', label: 'Enlace Externo', required: false }
        ];

        document.getElementById('fileInput').addEventListener('change', async function(e) {
            const file = e.target.files[0];
            if (!file) return;

            const formData = new FormData();
            formData.append('file', file);

            try {
                showLoading(true);
                const response = await fetch('/api/products/import/preview', {
                    method: 'POST',
                    body: formData,
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                    }
                });

                if (!response.ok) {
                    throw new Error('Error al procesar el archivo');
                }
                
                const data = await response.json();
                updateMappingFields(data.headers);
            } catch (error) {
                showError('Error al procesar el archivo');
                console.error('Error:', error);
            } finally {
                showLoading(false);
            }
        });

        function updateMappingFields(headers) {
            const mappingSection = document.getElementById('mappingSection');
            const mappingFields = document.getElementById('mappingFields');
            
            mappingSection.classList.remove('hidden');
            mappingFields.innerHTML = '';
            
            requiredFields.forEach(field => {
                const div = document.createElement('div');
                div.innerHTML = `
                    <label class="block text-gray-700 text-sm font-bold mb-2">
                        ${field.label}
                        ${field.required ? '<span class="text-red-500">*</span>' : ''}
                    </label>
                    <select id="mapping_${field.id}" class="shadow border rounded w-full py-2 px-3 text-gray-700">
                        <option value="">Seleccionar columna</option>
                        ${headers.map(header => `
                            <option value="${header}">${header}</option>
                        `).join('')}
                    </select>
                `;
                mappingFields.appendChild(div);
            });
        }

        async function importProducts() {
            const file = document.getElementById('fileInput').files[0];
            if (!file) {
                showError('Por favor seleccione un archivo');
                return;
            }

            const formData = new FormData();
            formData.append('file', file);

            // Recopilar mapeo de columnas
            const columnMapping = {};
            let hasError = false;

            requiredFields.forEach(field => {
                const value = document.getElementById(`mapping_${field.id}`).value;
                if (field.required && !value) {
                    showError(`Por favor seleccione la columna para ${field.label}`);
                    hasError = true;
                    return;
                }
                columnMapping[field.id] = value;
            });

            if (hasError) return;

            formData.append('column_mapping', JSON.stringify(columnMapping));

            try {
                showLoading(true);
                const response = await fetch('/api/products/import', {
                    method: 'POST',
                    body: formData,
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                    }
                });

                const data = await response.json();

                if (response.ok) {
                    showSuccess(data.message);
                    setTimeout(() => window.location.href = '/', 2000);
                } else {
                    showError(data.message || 'Error al importar productos');
                }
            } catch (error) {
                showError('Error al importar productos');
                console.error('Error:', error);
            } finally {
                showLoading(false);
            }
        }

        function showLoading(show) {
            const loadingDiv = document.getElementById('loading') || createLoadingElement();
            loadingDiv.style.display = show ? 'flex' : 'none';
        }

        function createLoadingElement() {
            const div = document.createElement('div');
            div.id = 'loading';
            div.className = 'fixed top-0 left-0 w-full h-full bg-black bg-opacity-50 flex items-center justify-center z-50';
            div.innerHTML = '<div class="bg-white p-4 rounded">Procesando archivo...</div>';
            document.body.appendChild(div);
            return div;
        }

        function showSuccess(message) {
            const alert = document.getElementById('alertSuccess');
            alert.textContent = message;
            alert.classList.remove('hidden');
            setTimeout(() => alert.classList.add('hidden'), 5000);
        }

        function showError(message) {
            const alert = document.getElementById('alertError');
            alert.textContent = message;
            alert.classList.remove('hidden');
            setTimeout(() => alert.classList.add('hidden'), 5000);
        }
    </script>
</body>
</html>