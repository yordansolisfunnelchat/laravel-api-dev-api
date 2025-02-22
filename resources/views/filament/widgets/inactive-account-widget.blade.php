<x-filament::widget class="col-span-full">
    <x-filament::card>
        <div class="flex items-center justify-between">
            <div>
                <h2 class="text-2xl font-bold text-danger-500">Tu cuenta está inactiva</h2>
                <p class="mt-2">Revisa tu suscripción para volver a tener acceso a todos nuestros servicios.</p>
            </div>
            <div>
                <x-filament::button
                    color="danger"
                    wire:click="contactSupport"
                >
                    Contactar a Soporte
                </x-filament::button>
            </div>
        </div>
    </x-filament::card>
</x-filament::widget>