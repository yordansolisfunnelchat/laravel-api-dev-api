<?php

namespace App\Filament\Widgets;

use Filament\Widgets\Widget;
use App\Models\User;

class DelayedPaymentWidget extends Widget
{
    protected static string $view = 'filament.widgets.delayed-payment-widget';

    protected static ?int $sort = 2;

    public static function canView(): bool
    {
        $user = auth()->user();
        return $user && $user->payment_status === 'delayed';
    }

    protected function getViewData(): array
    {
        return [];
    }
}