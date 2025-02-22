<?php

namespace App\Filament\Widgets;

use Filament\Widgets\Widget;
use App\Models\Instance;
use Filament\Notifications\Notification;

class InactiveAccountWidget extends Widget
{
    protected static string $view = 'filament.widgets.inactive-account-widget';

    protected static ?int $sort = 1;


    public static function canView(): bool
    {
        $instance = Instance::where('user_id', auth()->id())->first();
        return $instance?->status === 'inactive';
    }

    public function mount(): void
    {
        if ($this->canView()) {
            Notification::make()
                ->title('Tu cuenta está inactiva')
                ->body('Por favor, revisa tu suscripción para volver a tener acceso a todos nuestros servicios.')
                ->danger()
                ->persistent()
                ->send();
        }
    }

    public function contactSupport(): void
    {
        $this->redirect('https://wa.me/573002717873');
    }
}