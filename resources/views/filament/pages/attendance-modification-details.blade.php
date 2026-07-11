<x-filament-panels::page>
    <div class="space-y-3">

        @php $changes = $this->getChanges(); @endphp

        @if ($changes->isEmpty())
            <x-filament::empty-state icon="heroicon-o-clipboard-document-check">
                <x-slot name="heading">No attendance modification records found.</x-slot>
            </x-filament::empty-state>
        @else
            <div class="overflow-x-auto rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
                <table class="w-full text-left text-sm">
                    <thead class="border-b border-gray-100 dark:border-gray-800">
                        <tr>
                            <th class="px-4 py-2.5 font-semibold text-gray-600 dark:text-gray-300">Date</th>
                            <th class="px-4 py-2.5 font-semibold text-gray-600 dark:text-gray-300">Student</th>
                            <th class="px-4 py-2.5 font-semibold text-gray-600 dark:text-gray-300">Status Change</th>
                            <th class="px-4 py-2.5 font-semibold text-gray-600 dark:text-gray-300">Modified By</th>
                            <th class="px-4 py-2.5 font-semibold text-gray-600 dark:text-gray-300">Modified At</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                        @foreach ($changes as $change)
                            <tr>
                                <td class="whitespace-nowrap px-4 py-2.5 text-gray-700 dark:text-gray-200">
                                    {{ $change->date->format('d M Y') }}
                                </td>
                                <td class="whitespace-nowrap px-4 py-2.5 text-gray-900 dark:text-white">
                                    {{ $change->studentProfile?->user?->name ?? '—' }}
                                </td>
                                <td class="whitespace-nowrap px-4 py-2.5">
                                    <div class="flex items-center gap-x-1.5">
                                        @if ($change->old_status)
                                            <x-filament::badge :color="$change->old_status->getColor()">
                                                {{ $change->old_status->getLabel() }}
                                            </x-filament::badge>
                                            <x-heroicon-o-arrow-right class="h-3.5 w-3.5 shrink-0 text-gray-400" />
                                        @else
                                            <x-filament::badge color="gray">New</x-filament::badge>
                                        @endif
                                        <x-filament::badge :color="$change->new_status->getColor()">
                                            {{ $change->new_status->getLabel() }}
                                        </x-filament::badge>
                                    </div>
                                </td>
                                <td class="whitespace-nowrap px-4 py-2.5 text-gray-700 dark:text-gray-200">
                                    {{ $change->changedBy?->name ?? '—' }}
                                </td>
                                <td class="whitespace-nowrap px-4 py-2.5 text-gray-500 dark:text-gray-400">
                                    {{ $change->created_at->format('d M Y, h:i A') }}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</x-filament-panels::page>
