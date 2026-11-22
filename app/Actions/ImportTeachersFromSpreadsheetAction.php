<?php

namespace App\Actions;

use App\Enums\BloodGroup;
use App\Enums\EmploymentStatus;
use App\Enums\Gender;
use App\Enums\Religion;
use App\Models\User;
use BackedEnum;
use Carbon\Carbon;
use DateTimeInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use OpenSpout\Reader\XLSX\Reader;
use Throwable;

class ImportTeachersFromSpreadsheetAction
{
    public const MAX_ROWS = 500;

    /**
     * Import করা প্রত্যেক teacher-এর login password — teacher তৈরির ফর্মের ডিফল্টের মতোই।
     */
    public const DEFAULT_PASSWORD = 'password';

    /**
     * অনেকে খালি ঘরে অভ্যাসবশত এগুলো লেখে — এগুলো খালি (null) ধরা হয়, ছোট-বড় হাতের পার্থক্য ছাড়া।
     *
     * @var array<int, string>
     */
    public const EMPTY_PLACEHOLDERS = ['null', 'n/a', 'na', '-', '--'];

    /**
     * ডেমো ফাইলের প্রথম teacher রো-তে এই কলামগুলো আগে থেকে লেখা থাকে। শুধু এগুলো থাকা রো
     * খালি ধরা হয়, যাতে teacher-এর তথ্য না লেখা রো import না হয়।
     *
     * @var array<string, string>
     */
    public const PREFILLED_VALUES = [
        'nationality' => 'Bangladeshi',
    ];

    /**
     * Excel ফাইলের কলাম — ডেমো ফাইল (BuildTeacherImportTemplateAction) আর import দুটোই
     * এই একটা তালিকা থেকে চলে, যাতে কলামের নাম কখনো আলাদা না হয়ে যায়।
     *
     * @var array<string, array{required: bool, note: string, example: string}>
     */
    public const COLUMNS = [
        'name' => ['required' => true, 'note' => 'Teacher-এর পুরো নাম', 'example' => 'Abdul Karim'],
        'email' => ['required' => true, 'note' => 'Login-এর ইমেইল — প্রত্যেকের আলাদা হতে হবে, আগে থেকে সিস্টেমে থাকা ইমেইল চলবে না', 'example' => 'karim@school.com'],
        'gender' => ['required' => true, 'note' => 'male / female / other', 'example' => 'male'],
        'designation' => ['required' => false, 'note' => '', 'example' => 'Assistant Teacher'],
        'department' => ['required' => false, 'note' => '', 'example' => 'Science'],
        'qualification' => ['required' => false, 'note' => '', 'example' => 'M.Sc in Physics'],
        'joining_date' => ['required' => false, 'note' => 'YYYY-MM-DD অথবা DD/MM/YYYY', 'example' => '2020-01-01'],
        'date_of_birth' => ['required' => false, 'note' => 'YYYY-MM-DD অথবা DD/MM/YYYY', 'example' => '1988-05-12'],
        'blood_group' => ['required' => false, 'note' => 'A+ / A- / B+ / B- / AB+ / AB- / O+ / O-', 'example' => 'O+'],
        'religion' => ['required' => false, 'note' => 'muslim / hindu / christian / buddhist / other', 'example' => 'muslim'],
        'nationality' => ['required' => false, 'note' => 'খালি রাখলে Bangladeshi', 'example' => 'Bangladeshi'],
        'present_address' => ['required' => false, 'note' => '', 'example' => 'Mirpur, Dhaka'],
        'permanent_address' => ['required' => false, 'note' => 'খালি রাখলে শুধু present address সেভ হবে', 'example' => 'Cumilla'],
    ];

    public function __construct(private CreateTeacherProfileAction $createTeacherProfile) {}

    /**
     * Excel ফাইলের প্রতিটা রো থেকে একজন করে teacher (login account সহ) তৈরি করে। আগে পুরো
     * ফাইল যাচাই হয়; একটা রো-তেও ভুল থাকলে কিছুই import হয় না — নইলে ফাইল ঠিক করে আবার
     * আপলোড করলে আগের সফল রো-গুলো দ্বিতীয়বার তৈরি হয়ে যেত।
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
            return ['imported' => 0, 'errors' => ['ফাইলে কোনো teacher-এর রো পাওয়া যায়নি।']];
        }

        if (count($rows) > self::MAX_ROWS) {
            return ['imported' => 0, 'errors' => ['একবারে সর্বোচ্চ '.self::MAX_ROWS.' জন teacher import করা যায়।']];
        }

        $existingEmails = User::query()
            ->whereIn('email', array_map(Str::lower(...), array_filter(array_column($rows, 'email'))))
            ->pluck('email')
            ->map(fn (string $email): string => Str::lower($email))
            ->all();

        $errors = [];
        $teachers = [];
        $seenEmails = [];

        foreach ($rows as $rowNumber => $row) {
            $rowErrors = [];
            $teacher = $this->resolveTeacherData($row, $rowErrors);
            $emailKey = Str::lower((string) $teacher['email']);

            if ($emailKey !== '' && $rowErrors === []) {
                if (isset($seenEmails[$emailKey])) {
                    $rowErrors[] = "email {$teacher['email']} এই ফাইলের Row {$seenEmails[$emailKey]}-এও আছে";
                } elseif (in_array($emailKey, $existingEmails, true)) {
                    $rowErrors[] = "email {$teacher['email']} দিয়ে আগে থেকেই সিস্টেমে একটা account আছে";
                }

                $seenEmails[$emailKey] ??= $rowNumber;
            }

            foreach ($rowErrors as $rowError) {
                $errors[] = "Row {$rowNumber}: {$rowError}";
            }

            $teachers[] = $teacher;
        }

        if ($errors !== []) {
            return ['imported' => 0, 'errors' => $errors];
        }

        DB::transaction(function () use ($teachers): void {
            foreach ($teachers as $teacher) {
                $this->createTeacherProfile->handle($teacher);
            }
        });

        return ['imported' => count($teachers), 'errors' => []];
    }

    /**
     * প্রথম sheet-এর প্রথম রো header, বাকিগুলো teacher। Key = Excel-এর রো নম্বর,
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

                $record = [];

                foreach ($headers as $index => $header) {
                    if ($header !== '' && array_key_exists($header, self::COLUMNS)) {
                        $record[$header] = $this->normalizeValue($values[$index] ?? null);
                    }
                }

                if (array_filter(array_diff_key($record, self::PREFILLED_VALUES), fn (?string $value): bool => $value !== null) !== []) {
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
     * Excel সংখ্যার ঘরকে int/float আর তারিখের ঘরকে DateTime হিসেবে দেয় — সবকিছুকে
     * trim করা string-এ (খালি বা NULL/N/A/- হলে null) আনা হয়, যাতে পরের যাচাই একরকম থাকে।
     */
    private function normalizeValue(mixed $value): ?string
    {
        if ($value instanceof DateTimeInterface) {
            return $value->format('Y-m-d');
        }

        if (is_float($value) && floor($value) === $value) {
            $value = number_format($value, 0, '', '');
        }

        if (is_bool($value) || is_array($value) || is_object($value)) {
            return null;
        }

        $value = trim((string) $value);

        return $value === '' || in_array(Str::lower($value), self::EMPTY_PLACEHOLDERS, true) ? null : $value;
    }

    /**
     * একটা রো-কে CreateTeacherProfileAction-এর data shape-এ রূপান্তর করে; ভুলগুলো
     * $errors-এ জমা হয়।
     *
     * @param  array<string, ?string>  $row
     * @param  array<int, string>  $errors
     * @return array<string, mixed>
     */
    private function resolveTeacherData(array $row, array &$errors): array
    {
        $row += array_fill_keys(array_keys(self::COLUMNS), null);

        $validator = Validator::make($row, [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'gender' => ['required'],
            'designation' => ['nullable', 'string', 'max:255'],
            'department' => ['nullable', 'string', 'max:255'],
            'qualification' => ['nullable', 'string', 'max:255'],
            'nationality' => ['nullable', 'string', 'max:255'],
        ], [
            'required' => ':attribute দিতে হবে',
            'email' => ':attribute সঠিক ইমেইল নয়',
            'max' => ':attribute সর্বোচ্চ :max অক্ষরের হবে',
        ], array_combine(array_keys(self::COLUMNS), array_keys(self::COLUMNS)));

        $errors = [...$errors, ...$validator->errors()->all()];

        return [
            'name' => $row['name'],
            'email' => $row['email'] !== null ? Str::lower($row['email']) : null,
            'password' => self::DEFAULT_PASSWORD,
            'gender' => $this->resolveEnum(Gender::class, $row['gender'], 'gender', $errors),
            'designation' => $row['designation'],
            'department' => $row['department'],
            'qualification' => $row['qualification'],
            'joining_date' => $this->resolveDate($row['joining_date'], 'joining_date', $errors),
            'date_of_birth' => $this->resolveDate($row['date_of_birth'], 'date_of_birth', $errors),
            'blood_group' => $this->resolveEnum(BloodGroup::class, $row['blood_group'], 'blood_group', $errors),
            'religion' => $this->resolveEnum(Religion::class, $row['religion'], 'religion', $errors),
            'nationality' => $row['nationality'] ?? 'Bangladeshi',
            'status' => EmploymentStatus::Active->value,
            'present_address' => $row['present_address'],
            'permanent_address' => $row['permanent_address'],
            'same_address' => false,
        ];
    }

    /**
     * Enum-এর value (male) বা label (Male) — যেকোনোটা দিয়ে মেলে, ছোট-বড় হাতের পার্থক্য ছাড়া।
     *
     * @param  class-string<BackedEnum>  $enumClass
     * @param  array<int, string>  $errors
     */
    private function resolveEnum(string $enumClass, ?string $value, string $column, array &$errors): ?string
    {
        if ($value === null) {
            return null;
        }

        foreach ($enumClass::cases() as $case) {
            if ($this->sameName((string) $case->value, $value) || $this->sameName((string) $case->getLabel(), $value)) {
                return $case->value;
            }
        }

        $allowed = implode(' / ', array_map(fn (BackedEnum $case): string => (string) $case->value, $enumClass::cases()));
        $errors[] = "{$column} \"{$value}\" সঠিক নয় — {$allowed}-এর একটা দিন";

        return null;
    }

    /**
     * @param  array<int, string>  $errors
     */
    private function resolveDate(?string $value, string $column, array &$errors): ?string
    {
        if ($value === null) {
            return null;
        }

        foreach (['Y-m-d', 'd/m/Y', 'd-m-Y'] as $format) {
            if (Carbon::canBeCreatedFromFormat($value, $format)) {
                return Carbon::createFromFormat($format, $value)->toDateString();
            }
        }

        $errors[] = "{$column} \"{$value}\" সঠিক তারিখ নয় — YYYY-MM-DD অথবা DD/MM/YYYY লিখুন";

        return null;
    }

    private function sameName(string $first, string $second): bool
    {
        return Str::lower(trim($first)) === Str::lower(trim($second));
    }
}
