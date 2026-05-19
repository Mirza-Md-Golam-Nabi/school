<?php

if (! function_exists('convertBengaliToEnglish')) {
    function convertBengaliToEnglish(string $text): string
    {
        $bengali = ['০', '১', '২', '৩', '৪', '৫', '৬', '৭', '৮', '৯'];
        $english = ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'];

        return str_replace($bengali, $english, $text);
    }
}

if (! function_exists('convertEnglishToBengali')) {
    function convertEnglishToBengali(string $text): string
    {
        $bengali = ['০', '১', '২', '৩', '৪', '৫', '৬', '৭', '৮', '৯'];
        $english = ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'];

        return str_replace($english, $bengali, $text);
    }
}

if (! function_exists('sessionYear')) {
    function sessionYear(): array
    {
        $start = 2026;
        $end = now()->year + 1; // For next year

        return collect(range($start, $end))
            ->reverse()
            ->mapWithKeys(fn (int $year): array => [$year => (string) $year])
            ->toArray();
    }
}
