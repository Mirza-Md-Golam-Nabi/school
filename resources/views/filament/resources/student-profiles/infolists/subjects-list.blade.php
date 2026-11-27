<div class="space-y-5">
    @foreach ($sections as $section)
        @continue($section['hideWhenEmpty'] && $section['subjects']->isEmpty())

        <div>
            <div @class([
                'mb-2 flex items-center gap-2 text-xs font-bold uppercase tracking-wide sm:text-sm',
                'text-info-600 dark:text-info-400' => $section['color'] === 'info',
                'text-warning-600 dark:text-warning-400' => $section['color'] === 'warning',
                'text-success-600 dark:text-success-400' => $section['color'] === 'success',
            ])>
                <span @class([
                    'h-2 w-2 shrink-0 rounded-full',
                    'bg-info-500' => $section['color'] === 'info',
                    'bg-warning-500' => $section['color'] === 'warning',
                    'bg-success-500' => $section['color'] === 'success',
                ])></span>
                {{ $section['heading'] }}
            </div>

            @if ($section['subjects']->isEmpty())
                <p class="text-xs text-gray-400 italic dark:text-gray-500 sm:text-sm">{{ $section['empty'] }}</p>
            @else
                <div class="flex flex-wrap gap-2">
                    @foreach ($section['subjects'] as $name)
                        <span @class([
                            'inline-flex items-center rounded-full px-2.5 py-1 text-[11px] font-medium sm:text-xs',
                            'bg-info-100 text-info-700 dark:bg-info-500/10 dark:text-info-400' => $section['color'] === 'info',
                            'bg-warning-100 text-warning-700 dark:bg-warning-500/10 dark:text-warning-400' => $section['color'] === 'warning',
                            'bg-success-100 text-success-700 dark:bg-success-500/10 dark:text-success-400' => $section['color'] === 'success',
                        ])>
                            {{ $name }}
                        </span>
                    @endforeach
                </div>
            @endif
        </div>
    @endforeach
</div>
