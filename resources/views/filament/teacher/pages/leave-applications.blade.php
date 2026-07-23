<x-filament-panels::page>
    @if ($applications->isEmpty())
        <x-filament::empty-state icon="heroicon-o-document-plus">
            <x-slot name="heading">You haven't applied for any leave yet.</x-slot>
        </x-filament::empty-state>
    @else
        <div class="overflow-x-auto rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-gray-200 bg-gray-50 dark:border-gray-700 dark:bg-gray-800">
                        <th class="px-3 py-2.5 text-left font-semibold text-gray-700 dark:text-gray-300">Leave Type</th>
                        <th class="px-3 py-2.5 text-center font-semibold text-gray-700 dark:text-gray-300">From</th>
                        <th class="px-3 py-2.5 text-center font-semibold text-gray-700 dark:text-gray-300">To</th>
                        <th class="px-3 py-2.5 text-center font-semibold text-gray-700 dark:text-gray-300">Days</th>
                        <th class="px-3 py-2.5 text-center font-semibold text-gray-700 dark:text-gray-300">Status</th>
                        <th class="px-3 py-2.5 text-left font-semibold text-gray-700 dark:text-gray-300">Reason</th>
                        <th class="px-3 py-2.5 text-center font-semibold text-gray-700 dark:text-gray-300">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                    @foreach ($applications as $application)
                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-900/30">
                            <td class="px-3 py-2.5 text-left font-medium text-gray-900 dark:text-white">
                                {{ $application->leaveType?->name ?? '—' }}
                            </td>
                            <td class="px-3 py-2.5 text-center text-gray-700 dark:text-gray-300">
                                {{ $application->from_date->format('d M, Y') }}
                            </td>
                            <td class="px-3 py-2.5 text-center text-gray-700 dark:text-gray-300">
                                {{ $application->to_date->format('d M, Y') }}
                            </td>
                            <td class="px-3 py-2.5 text-center text-gray-700 dark:text-gray-300">
                                {{ $application->total_days }}
                            </td>
                            <td class="px-3 py-2.5 text-center">
                                <x-filament::badge :color="$application->status->getColor()" :icon="$application->status->getIcon()">
                                    {{ $application->status->getLabel() }}
                                </x-filament::badge>
                            </td>
                            <td class="px-3 py-2.5 text-left text-gray-600 dark:text-gray-400">
                                <span class="block max-w-[200px] truncate" title="{{ $application->reason }}">
                                    {{ $application->reason }}
                                </span>
                            </td>
                            <td class="px-3 py-2.5 text-center">
                                @if ($application->isPending())
                                    <x-filament::button
                                        size="sm"
                                        color="danger"
                                        icon="heroicon-o-x-mark"
                                        wire:click="cancel({{ $application->id }})"
                                        wire:confirm="Are you sure you want to cancel this leave application?"
                                    >
                                        Cancel
                                    </x-filament::button>
                                @else
                                    <span class="text-gray-400">—</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</x-filament-panels::page>
