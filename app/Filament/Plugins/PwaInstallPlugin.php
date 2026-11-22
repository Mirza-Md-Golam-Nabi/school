<?php

namespace App\Filament\Plugins;

use Filament\Actions\Action;
use Filament\Contracts\Plugin;
use Filament\Panel;
use Filament\Support\Icons\Heroicon;
use Filament\View\PanelsRenderHook;

/**
 * Lets students and teachers install their panel as an app (PWA) from
 * anywhere: the login page, a topbar button, the user menu and a
 * dismissible banner. Every entry point links to the public install guide.
 */
class PwaInstallPlugin implements Plugin
{
    public static function make(): static
    {
        return app(static::class);
    }

    public function getId(): string
    {
        return 'pwa-install';
    }

    public function register(Panel $panel): void
    {
        $panelId = $panel->getId();
        $guideUrl = fn (): string => route('install-guide', ['panel' => $panelId]);
        $viewData = fn (): array => ['panelId' => $panelId, 'guideUrl' => $guideUrl()];

        $panel
            ->renderHook(
                PanelsRenderHook::HEAD_END,
                fn (): string => view('filament.shared.pwa-install-head', $viewData())->render(),
            )
            ->renderHook(
                PanelsRenderHook::AUTH_LOGIN_FORM_AFTER,
                fn (): string => view('filament.shared.pwa-install-login', $viewData())->render(),
            )
            ->renderHook(
                PanelsRenderHook::USER_MENU_BEFORE,
                fn (): string => view('filament.shared.pwa-install-topbar-button', $viewData())->render(),
            )
            ->renderHook(
                PanelsRenderHook::BODY_END,
                fn (): string => auth()->check() ? view('filament.shared.pwa-install-banner', $viewData())->render() : '',
            )
            ->userMenuItems([
                'install-app' => Action::make('installApp')
                    ->label(fn (): string => __('Install app'))
                    ->icon(Heroicon::OutlinedDevicePhoneMobile)
                    ->url($guideUrl)
                    ->extraAttributes([
                        'data-pwa-install' => true,
                        'data-pwa-hide-when-installed' => true,
                    ]),
            ]);
    }

    public function boot(Panel $panel): void {}
}
