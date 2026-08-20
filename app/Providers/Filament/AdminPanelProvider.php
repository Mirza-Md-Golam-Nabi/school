<?php

namespace App\Providers\Filament;

use App\Filament\Admin\Pages\Auth\EditProfile;
use App\Filament\Admin\Pages\Auth\Login;
use App\Filament\Admin\Pages\Auth\Register;
use App\Filament\Admin\Pages\Dashboard;
use Filament\Actions\Action;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\View\PanelsRenderHook;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->viteTheme('resources/css/filament/theme.css')
            ->databaseTransactions()
            ->databaseNotifications()
            ->login(Login::class)
            ->registration(Register::class)
            ->passwordReset()
            ->emailVerification()
            ->emailChangeVerification()
            ->profile(EditProfile::class, isSimple: false)
            ->userMenuItems([
                'profile' => fn (Action $action) => $action
                    ->label(auth()->user()->name),
            ])
            ->colors([
                'primary' => Color::Amber,
            ])
            ->renderHook(
                PanelsRenderHook::HEAD_END,
                fn (): string => '<link rel="manifest" href="/manifest/admin.json">',
            )
            ->renderHook(
                PanelsRenderHook::BODY_END,
                fn (): string => auth()->check() ? view('filament.shared.webpush-subscribe')->render() : '',
            )
            ->navigationGroups([
                'Academic Structure',
                'Attendance',
                'User Profile',
                'Fee & Finance',
                'Communication',
                'Document Management',
                'HR & Staff Management',
                'Salary Management',
                'Fund Management',
                'User Management',
                'Activity Log',
                'School Settings',
            ])
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->pages([
                Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\Filament\Widgets')
            ->widgets([
                //
            ])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                PreventRequestForgery::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ]);
    }
}
