@php
    use App\Support\QuestionBankConnection;
@endphp

<x-filament-panels::page>
    <x-filament::section
        :icon="QuestionBankConnection::isConnected() ? 'heroicon-o-check-circle' : 'heroicon-o-exclamation-triangle'"
        :icon-color="QuestionBankConnection::isConnected() ? 'success' : 'warning'"
        :heading="QuestionBankConnection::isConnected() ? __('Connected') : __('Not connected')"
    >
        <p class="text-sm text-gray-600 dark:text-gray-400">
            @if (QuestionBankConnection::isConnected())
                {{ __('Connected to the Question Bank as :school on :date. Teachers and students see a "Question Bank" link in their panels.', [
                    'school' => QuestionBankConnection::schoolName(),
                    'date' => QuestionBankConnection::connectedAt()->translatedFormat('j M Y, g:i A'),
                ]) }}
            @else
                {{ __('Sign in to the Question Bank with the school\'s Google account, open "School software connection", and copy the three values here.') }}
            @endif
        </p>
    </x-filament::section>

    {{ $this->form }}
</x-filament-panels::page>
