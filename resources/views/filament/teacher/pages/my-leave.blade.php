<x-filament-panels::page>
    @if ($rows->isEmpty())
        <x-filament::empty-state icon="heroicon-o-calendar-days">
            <x-slot name="heading">No leave types available for you yet.</x-slot>
        </x-filament::empty-state>
    @else
        <div class="overflow-x-auto rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-gray-200 bg-gray-50 dark:border-gray-700 dark:bg-gray-800">
                        <th class="px-3 py-2.5 text-left font-semibold text-gray-700 dark:text-gray-300">Leave Type</th>
                        <th class="px-3 py-2.5 text-center font-semibold text-gray-700 dark:text-gray-300">Allowed</th>
                        <th class="px-3 py-2.5 text-center font-semibold text-gray-700 dark:text-gray-300">Taken</th>
                        <th class="px-3 py-2.5 text-center font-semibold text-gray-700 dark:text-gray-300">Remaining</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                    @foreach ($rows as $row)
                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-900/30">
                            <td class="px-3 py-2.5 text-left">
                                <p class="font-medium text-gray-900 dark:text-white">{{ $row['name'] }}</p>
                                <x-filament::badge :color="$row['applicable_gender']->getColor()" size="sm">
                                    {{ $row['applicable_gender']->getLabel() }}
                                </x-filament::badge>
                            </td>
                            <td class="px-3 py-2.5 text-center text-gray-700 dark:text-gray-300">{{ $row['allowed'] }}</td>
                            <td class="px-3 py-2.5 text-center font-semibold text-danger-600 dark:text-danger-400">{{ $row['taken'] }}</td>
                            <td class="px-3 py-2.5 text-center font-semibold text-success-600 dark:text-success-400">{{ $row['remaining'] }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</x-filament-panels::page>
