<x-filament::widget class="col-span-full">
    <x-filament::card>
        <div class="sm:flex sm:items-center sm:justify-between space-y-4 sm:space-y-0">
            <div>
                <h2 class="text-lg sm:text-xl font-bold text-warning-500">Tu pago está atrasado</h2>
                <p class="mt-1 text-sm sm:text-base">Tienes 24 horas para ponerte al día y que todo siga funcionando.</p>
            </div>
            <div class="mt-4 sm:mt-0">
                <x-filament::button
                    color="warning"
                    tag="a"
                    href="https://wa.me/573002717873"
                    target="_blank"
                    rel="noopener noreferrer"
                    class="w-full sm:w-auto"
                >
                    Contactar a Soporte
                </x-filament::button>
            </div>
        </div>
    </x-filament::card>
</x-filament::widget>