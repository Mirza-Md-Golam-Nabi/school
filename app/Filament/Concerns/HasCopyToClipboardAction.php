<?php

namespace App\Filament\Concerns;

use Filament\Actions\Action;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Js;

trait HasCopyToClipboardAction
{
    /**
     * An icon-only, label-less button that copies $value to the clipboard —
     * for pasting a one-time secret (like a device API token) out of a notification.
     */
    protected static function copyToClipboardAction(string $name, string $value): Action
    {
        return Action::make($name)
            ->label('Copy')
            ->icon(Heroicon::OutlinedClipboard)
            ->iconButton()
            ->color('gray')
            ->alpineClickHandler(
                'window.navigator.clipboard.writeText('.Js::from($value).');'
                .'$tooltip('.Js::from('Copied!').', { theme: $store.theme, timeout: 1500 })'
            );
    }
}
