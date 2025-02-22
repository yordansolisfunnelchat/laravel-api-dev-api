<?php

namespace App\Providers\Filament;

use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Pages;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Widgets;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\AuthenticateSession;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;
use App\Filament\Admin\Widgets\InstancesOverviewWidget;
use BezhanSalleh\PanelSwitch\PanelSwitch;
use App\Filament\Widgets\InactiveAccountWidget;
use App\Filament\User\Pages\EditProfile;
use App\Filament\Widgets\DelayedPaymentWidget;

class UserPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->id('user')
            ->path('')
            ->login()
            ->profile(EditProfile::class)
            ->colors([
                'primary' => '#25D366',
            ])
            ->plugins([
            ])
            ->brandLogo(asset('storage/assets/logo-negro.png'))
            ->brandLogoHeight('2rem')
            ->darkModeBrandLogo(asset('storage/assets/logo-blanco.png'))
            ->favicon(asset('storage/assets/favicon.png'))
            ->font('Poppins')
            ->discoverResources(in: app_path('Filament/User/Resources'), for: 'App\\Filament\\User\\Resources')
            ->discoverPages(in: app_path('Filament/User/Pages'), for: 'App\\Filament\\User\\Pages')
            ->pages([
                Pages\Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\\Filament\\Widgets')
            ->widgets([
                DelayedPaymentWidget::class,
                InactiveAccountWidget::class,
                Widgets\AccountWidget::class,
                InstancesOverviewWidget::class,
            ])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                VerifyCsrfToken::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ]);
    }
}
