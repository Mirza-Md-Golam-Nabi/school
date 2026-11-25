<?php

namespace App\Actions;

use App\Models\Subject;
use DateTimeInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use OpenSpout\Reader\XLSX\Reader;
use Throwable;

class ImportSubjectsFromSpreadsheetAction
{
    public const MAX_ROWS = 300;

    /**
     * অনেকে খালি ঘরে অভ্যাসবশত এগুলো লেখে — এগুলো খালি (null) ধরা হয়, ছোট-বড় হাতের পার্থক্য ছাড়া।
     *
     * @var array<int, string>
     */
    public const EMPTY_PLACEHOLDERS = ['null', 'n/a', 'na', '-', '--'];

    /**
     * হ্যাঁ/না কলামে যে মানগুলো চলে।
     *
     * @var array<int, string>
     */
    public const TRUE_VALUES = ['yes', 'y', 'true', '1', 'হ্যাঁ'];

    /**
     * @var array<int, string>
     */
    public const FALSE_VALUES = ['no', 'n', 'false', '0', 'না'];

    /**
     * Excel ফাইলের কলাম — ডেমো ফাইল (BuildSubjectImportTemplateAction) আর import দুটোই
     * এই একটা তালিকা থেকে চলে। boolean কলামের 'default' = ঘর খালি থাকলে যে মান বসে
     * (subject ফর্মের ডিফল্টের মতোই)।
     *
     * @var array<string, array{required: bool, note: string, example: string, boolean?: bool, default?: bool}>
     */
    public const COLUMNS = [
        'name' => ['required' => true, 'note' => 'Subject-এর নাম (English)', 'example' => 'Mathematics'],
        'name_bn' => ['required' => false, 'note' => 'Subject-এর নাম (বাংলা)', 'example' => 'গণিত'],
        'code' => ['required' => false, 'note' => 'Subject code, সর্বোচ্চ ২০ অক্ষর — প্রত্যেক subject-এর আলাদা হতে হবে, আগে থেকে সিস্টেমে থাকা code চলবে না', 'example' => '109'],
        'has_written' => ['required' => false, 'note' => 'yes / no — খালি রাখলে yes', 'example' => 'yes', 'boolean' => true, 'default' => true],
        'has_mcq' => ['required' => false, 'note' => 'yes / no — খালি রাখলে no', 'example' => 'yes', 'boolean' => true, 'default' => false],
        'has_practical' => ['required' => false, 'note' => 'yes / no — খালি রাখলে no', 'example' => 'no', 'boolean' => true, 'default' => false],
        'is_active' => ['required' => false, 'note' => 'yes / no — খালি রাখলে yes', 'example' => 'yes', 'boolean' => true, 'default' => true],
    ];

    /**
     * Excel ফাইলের প্রতিটা রো থেকে একটা করে subject তৈরি করে, ফাইলের ক্রম অনুযায়ী
     * বিদ্যমান subject-গুলোর পরে। আগে পুরো ফাইল যাচাই হয়; একটা রো-তেও ভুল থাকলে কিছুই
     * import হয় না — নইলে ফাইল ঠিক করে আবার আপলোড করলে আগের সফল রো-গুলো দ্বিতীয়বার তৈরি হতো।
     *
     * @return array{imported: int, errors: array<int, string>}
     */
    public function handle(string $path): array
    {
        try {
            $rows = $this->readRows($path);
        } catch (Throwable) {
            return ['imported' => 0, 'errors' => ['ফাইলটা পড়া যায়নি — ডেমো ফাইলের মতো একটা .xlsx ফাইল আপলোড করুন।']];
        }

        if (isset($rows['error'])) {
            return ['imported' => 0, 'errors' => [$rows['error']]];
        }

        if ($rows === []) {
            return ['imported' => 0, 'errors' => ['ফাইলে কোনো subject-এর রো পাওয়া যায়নি।']];
        }

        if (count($rows) > self::MAX_ROWS) {
            return ['imported' => 0, 'errors' => ['একবারে সর্বোচ্চ '.self::MAX_ROWS.'টা subject import করা যায়।']];
        }

        $codes = array_values(array_filter(array_map(
            fn (array $row): ?string => $row['code'] !== null ? Str::upper($row['code']) : null,
            $rows,
        )));

        $existingCodes = Subject::withTrashed()
            ->whereIn('code', $codes)
            ->pluck('code')
            ->map(fn (string $code): string => Str::upper($code))
            ->all();

        $errors = [];
        $subjects = [];
        $seenCodes = [];

        foreach ($rows as $rowNumber => $row) {
            $rowErrors = [];
            $subject = $this->resolveSubjectData($row, $rowErrors);
            $code = $subject['code'];

            if ($code !== null) {
                if (isset($seenCodes[$code])) {
                    $rowErrors[] = "code {$code} এই ফাইলের Row {$seenCodes[$code]}-এও আছে";
                } elseif (in_array($code, $existingCodes, true)) {
                    $rowErrors[] = "code {$code} দিয়ে আগে থেকেই সিস্টেমে একটা subject আছে";
                }

                $seenCodes[$code] ??= $rowNumber;
            }

            foreach ($rowErrors as $rowError) {
                $errors[] = "Row {$rowNumber}: {$rowError}";
            }

            $subjects[] = $subject;
        }

        if ($errors !== []) {
            return ['imported' => 0, 'errors' => $errors];
        }

        DB::transaction(function () use ($subjects): void {
            foreach ($subjects as $subject) {
                Subject::create($subject);
            }
        });

        return ['imported' => count($subjects), 'errors' => []];
    }

    /**
     * প্রথম sheet-এর প্রথম রো header, বাকিগুলো subject। Key = Excel-এর রো নম্বর,
     * যাতে error বার্তায় ইউজার সরাসরি সেই রো খুঁজে পায়।
     *
     * @return array<int, array<string, ?string>>|array{error: string}
     */
    private function readRows(string $path): array
    {
        $reader = new Reader;
        $reader->open($path);

        $headers = null;
        $rows = [];

        foreach ($reader->getSheetIterator() as $sheet) {
            foreach ($sheet->getRowIterator() as $rowNumber => $row) {
                $values = $row->toArray();

                if ($headers === null) {
                    $headers = array_map(fn (mixed $header): string => $this->normalizeHeader($header), $values);

                    continue;
                }

                $record = array_fill_keys(array_keys(self::COLUMNS), null);

                foreach ($headers as $index => $header) {
                    if ($header !== '' && array_key_exists($header, self::COLUMNS)) {
                        $record[$header] = $this->normalizeValue($values[$index] ?? null);
                    }
                }

                if (array_filter($record, fn (?string $value): bool => $value !== null) !== []) {
                    $rows[$rowNumber] = $record;
                }
            }

            break;
        }

        $reader->close();

        $requiredHeaders = array_keys(array_filter(self::COLUMNS, fn (array $column): bool => $column['required']));
        $missingHeaders = array_diff($requiredHeaders, $headers ?? []);

        if ($missingHeaders !== []) {
            return ['error' => 'ফাইলে এই কলামগুলো নেই: '.implode(', ', $missingHeaders).'। ডেমো ফাইল ডাউনলোড করে সেটার কলামের নাম ব্যবহার করুন।'];
        }

        return $rows;
    }

    private function normalizeHeader(mixed $header): string
    {
        return Str::of((string) $header)->lower()->replace('*', '')->trim()->replace(' ', '_')->toString();
    }

    /**
     * Excel সংখ্যার ঘরকে int/float দেয় (code 101 → 101.0) — সবকিছুকে trim করা string-এ
     * (খালি বা NULL/N/A/- হলে null) আনা হয়, যাতে পরের যাচাই একরকম থাকে।
     */
    private function normalizeValue(mixed $value): ?string
    {
        if ($value instanceof DateTimeInterface) {
            return $value->format('Y-m-d');
        }

        if (is_bool($value)) {
            return $value ? 'yes' : 'no';
        }

        if (is_float($value) && floor($value) === $value) {
            $value = number_format($value, 0, '', '');
        }

        if (is_array($value) || is_object($value)) {
            return null;
        }

        $value = trim((string) $value);

        return $value === '' || in_array(Str::lower($value), self::EMPTY_PLACEHOLDERS, true) ? null : $value;
    }

    /**
     * একটা রো-কে Subject-এর attribute-এ রূপান্তর করে; ভুলগুলো $errors-এ জমা হয়।
     *
     * @param  array<string, ?string>  $row
     * @param  array<int, string>  $errors
     * @return array<string, mixed>
     */
    private function resolveSubjectData(array $row, array &$errors): array
    {
        $validator = Validator::make($row, [
            'name' => ['required', 'string', 'max:100'],
            'name_bn' => ['nullable', 'string', 'max:100'],
            'code' => ['nullable', 'string', 'max:20'],
        ], [
            'required' => ':attribute দিতে হবে',
            'max' => ':attribute সর্বোচ্চ :max অক্ষরের হবে',
        ], array_combine(array_keys(self::COLUMNS), array_keys(self::COLUMNS)));

        $errors = [...$errors, ...$validator->errors()->all()];

        $subject = [
            'name' => $row['name'],
            'name_bn' => $row['name_bn'],
            'code' => $row['code'] !== null ? Str::upper($row['code']) : null,
        ];

        foreach (self::COLUMNS as $name => $column) {
            if ($column['boolean'] ?? false) {
                $subject[$name] = $this->resolveBoolean($row[$name], $column['default'] ?? false, $name, $errors);
            }
        }

        return $subject;
    }

    /**
     * @param  array<int, string>  $errors
     */
    private function resolveBoolean(?string $value, bool $default, string $column, array &$errors): bool
    {
        if ($value === null) {
            return $default;
        }

        $value = Str::lower($value);

        if (in_array($value, self::TRUE_VALUES, true)) {
            return true;
        }

        if (in_array($value, self::FALSE_VALUES, true)) {
            return false;
        }

        $errors[] = "{$column} \"{$value}\" সঠিক নয় — yes অথবা no দিন";

        return $default;
    }
}
