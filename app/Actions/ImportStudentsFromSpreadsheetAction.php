<?php

namespace App\Actions;

use App\Enums\BloodGroup;
use App\Enums\Gender;
use App\Enums\Religion;
use App\Enums\StudentStatus;
use App\Models\Classes;
use App\Models\ClassGroupSubject;
use App\Models\Section;
use App\Models\StudentProfile;
use BackedEnum;
use Carbon\Carbon;
use DateTimeInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use OpenSpout\Reader\XLSX\Reader;
use Throwable;

class ImportStudentsFromSpreadsheetAction
{
    public const MAX_ROWS = 1000;

    /**
     * Excel ফাইলের কলাম — ডেমো ফাইল (BuildStudentImportTemplateAction) আর import দুটোই
     * এই একটা তালিকা থেকে চলে, যাতে কলামের নাম কখনো আলাদা না হয়ে যায়।
     *
     * @var array<string, array{required: bool, note: string, example: string}>
     */
    public const COLUMNS = [
        'name' => ['required' => true, 'note' => 'Student-এর পূর্ণ নাম', 'example' => 'Abdur Rahman'],
        'roll_no' => ['required' => true, 'note' => 'শুধু সংখ্যা', 'example' => '1'],
        'class' => ['required' => true, 'note' => 'সিস্টেমে থাকা ক্লাসের নাম হুবহু। ক্লাসের পেজ থেকে আপলোড করলে খালি রাখা যায়', 'example' => 'Class 6'],
        'section' => ['required' => false, 'note' => 'ওই ক্লাসের section-এর নাম', 'example' => 'A'],
        'group' => ['required' => false, 'note' => 'ওই ক্লাসের group-এর নাম (যেমন Science)', 'example' => ''],
        'session_year' => ['required' => false, 'note' => 'খালি রাখলে চলতি বছর ধরা হবে', 'example' => '2026'],
        'gender' => ['required' => true, 'note' => 'male / female / other', 'example' => 'male'],
        'registration_no' => ['required' => false, 'note' => 'শুধু সংখ্যা', 'example' => ''],
        'admission_date' => ['required' => false, 'note' => 'YYYY-MM-DD অথবা DD/MM/YYYY', 'example' => '2026-01-05'],
        'date_of_birth' => ['required' => false, 'note' => 'YYYY-MM-DD অথবা DD/MM/YYYY', 'example' => '2014-03-21'],
        'birth_certificate_no' => ['required' => false, 'note' => 'কলামটা Text ফরম্যাটে রাখুন, নইলে Excel লম্বা সংখ্যা বদলে ফেলে। আগে থেকে নিবন্ধিত নম্বর হলে সেই student-এর তথ্য আপডেট হবে', 'example' => '20141234567890123'],
        'blood_group' => ['required' => false, 'note' => 'A+ / A- / B+ / B- / AB+ / AB- / O+ / O-', 'example' => 'B+'],
        'religion' => ['required' => false, 'note' => 'muslim / hindu / christian / buddhist / other', 'example' => 'muslim'],
        'nationality' => ['required' => false, 'note' => 'খালি রাখলে Bangladeshi', 'example' => 'Bangladeshi'],
        'father_name' => ['required' => false, 'note' => '', 'example' => 'Abdul Karim'],
        'father_occupation' => ['required' => false, 'note' => '', 'example' => 'Business'],
        'mother_name' => ['required' => false, 'note' => '', 'example' => 'Fatema Begum'],
        'mother_occupation' => ['required' => false, 'note' => '', 'example' => 'Housewife'],
        'guardian_name' => ['required' => false, 'note' => '', 'example' => 'Abdul Karim'],
        'guardian_relation' => ['required' => false, 'note' => '', 'example' => 'Father'],
        'guardian_occupation' => ['required' => false, 'note' => '', 'example' => 'Business'],
        'guardian_phone' => ['required' => false, 'note' => 'সর্বোচ্চ ১৫ সংখ্যা। কলামটা Text ফরম্যাটে রাখুন, নইলে শুরুর 0 হারিয়ে যায়', 'example' => '01712345678'],
        'present_address' => ['required' => false, 'note' => '', 'example' => 'Mirpur, Dhaka'],
        'permanent_address' => ['required' => false, 'note' => 'খালি রাখলে শুধু present address সেভ হবে', 'example' => 'Cumilla'],
        'main_optional_subject' => ['required' => false, 'note' => 'শুধু group থাকলে — ওই group-এর optional subject-এর নাম', 'example' => ''],
        'extra_optional_subject' => ['required' => false, 'note' => 'শুধু group থাকলে — main optional থেকে আলাদা হতে হবে', 'example' => ''],
    ];

    /**
     * @var Collection<int, Classes>|null
     */
    private ?Collection $classes = null;

    /**
     * @var array<int, Collection<int, string>>
     */
    private array $sectionsByClass = [];

    /**
     * @var array<int, Collection<int, string>>
     */
    private array $groupsByClass = [];

    /**
     * Optional subject options, cached per class/group so a 1000-row file
     * doesn't re-query them for every row.
     *
     * @var array<string, Collection>
     */
    private array $optionalSubjectsByClassGroup = [];

    public function __construct(private CreateStudentProfileAction $createStudentProfile) {}

    /**
     * Excel ফাইলের প্রতিটা রো থেকে একজন করে student তৈরি করে। আগে পুরো ফাইল যাচাই হয়;
     * একটা রো-তেও ভুল থাকলে কিছুই import হয় না — নইলে ফাইল ঠিক করে আবার আপলোড করলে
     * আগের সফল রো-গুলো দ্বিতীয়বার তৈরি হয়ে যেত।
     *
     * @return array{imported: int, errors: array<int, string>}
     */
    public function handle(string $path, ?int $defaultClassId = null): array
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
            return ['imported' => 0, 'errors' => ['ফাইলে কোনো student-এর রো পাওয়া যায়নি।']];
        }

        if (count($rows) > self::MAX_ROWS) {
            return ['imported' => 0, 'errors' => ['একবারে সর্বোচ্চ '.self::MAX_ROWS.' জন student import করা যায়।']];
        }

        $errors = [];
        $students = [];
        $seenRolls = [];

        foreach ($rows as $rowNumber => $row) {
            $rowErrors = [];
            $student = $this->resolveStudentData($row, $defaultClassId, $rowErrors);

            if ($rowErrors === []) {
                $rollKey = implode('-', [$student['current_class_id'], $student['current_section_id'] ?? 0, $student['session_year'], $student['roll_no']]);

                if (isset($seenRolls[$rollKey])) {
                    $rowErrors[] = "roll_no {$student['roll_no']} এই ফাইলের Row {$seenRolls[$rollKey]}-এও একই ক্লাস/section-এ আছে";
                } elseif ($this->rollIsTaken($student)) {
                    $rowErrors[] = "roll_no {$student['roll_no']} এই ক্লাস/section ও সেশনে আগে থেকেই একজন student-এর আছে";
                }

                $seenRolls[$rollKey] ??= $rowNumber;
            }

            foreach ($rowErrors as $rowError) {
                $errors[] = "Row {$rowNumber}: {$rowError}";
            }

            $students[] = $student;
        }

        if ($errors !== []) {
            return ['imported' => 0, 'errors' => $errors];
        }

        DB::transaction(function () use ($students): void {
            foreach ($students as $student) {
                $this->createStudentProfile->handle($student);
            }
        });

        return ['imported' => count($students), 'errors' => []];
    }

    /**
     * প্রথম sheet-এর প্রথম রো header, বাকিগুলো student। Key = Excel-এর রো নম্বর,
     * যাতে error বার্তায় ইউজার সরাসরি সেই রো খুঁজে পায়।
     *
     * @return array<int, array<string, mixed>>|array{error: string}
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

                if (array_filter($record, fn (mixed $value): bool => $value !== null) !== []) {
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
     * trim করা string-এ (খালি হলে null) আনা হয়, যাতে পরের যাচাই একরকম থাকে।
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

        return $value === '' ? null : $value;
    }

    /**
     * একটা রো-কে CreateStudentProfileAction-এর data shape-এ রূপান্তর করে; ভুলগুলো
     * $errors-এ জমা হয়।
     *
     * @param  array<string, ?string>  $row
     * @param  array<int, string>  $errors
     * @return array<string, mixed>
     */
    private function resolveStudentData(array $row, ?int $defaultClassId, array &$errors): array
    {
        $row += array_fill_keys(array_keys(self::COLUMNS), null);

        $validator = Validator::make($row, [
            'name' => ['required', 'string', 'max:255'],
            'roll_no' => ['required', 'integer', 'min:1'],
            'session_year' => ['nullable', 'integer', 'between:2000,2100'],
            'gender' => ['required'],
            'registration_no' => ['nullable', 'integer', 'min:1'],
            'birth_certificate_no' => ['nullable', 'digits_between:1,20'],
            'guardian_phone' => ['nullable', 'max:15'],
        ], [
            'required' => ':attribute দিতে হবে',
            'integer' => ':attribute শুধু সংখ্যা হবে',
            'min' => ':attribute কমপক্ষে :min হতে হবে',
            'max' => ':attribute সর্বোচ্চ :max অক্ষরের হবে',
            'between' => ':attribute :min থেকে :max-এর মধ্যে হতে হবে',
            'digits_between' => ':attribute শুধু সংখ্যা হবে (সর্বোচ্চ :max সংখ্যা)',
        ], array_combine(array_keys(self::COLUMNS), array_keys(self::COLUMNS)));

        $errors = [...$errors, ...$validator->errors()->all()];

        $class = $this->resolveClass($row['class'], $defaultClassId, $errors);
        $sectionId = $class ? $this->resolveByName($row['section'], $this->sectionsOf($class), 'section', $class->name, $errors) : null;
        $groupId = $class ? $this->resolveByName($row['group'], $this->groupsOf($class), 'group', $class->name, $errors) : null;

        $mainOptionalSubjectId = null;
        $extraOptionalSubjectId = null;

        if ($class && $groupId) {
            $mainOptionalSubjectId = $this->resolveByName(
                $row['main_optional_subject'],
                $this->optionalSubjectsOf($class->id, $groupId, includeAllGroups: false),
                'main_optional_subject',
                $class->name,
                $errors,
            );
            $extraOptionalSubjectId = $this->resolveByName(
                $row['extra_optional_subject'],
                $this->optionalSubjectsOf($class->id, $groupId, includeAllGroups: true),
                'extra_optional_subject',
                $class->name,
                $errors,
            );

            if ($mainOptionalSubjectId && $mainOptionalSubjectId === $extraOptionalSubjectId) {
                $errors[] = 'extra_optional_subject অবশ্যই main_optional_subject থেকে আলাদা হতে হবে';
            }
        } elseif (filled($row['main_optional_subject']) || filled($row['extra_optional_subject'])) {
            $errors[] = 'optional subject দিতে হলে group দিতে হবে';
        }

        $phone = $row['guardian_phone'];

        // Excel সংখ্যা হিসেবে রাখলে মোবাইল নম্বরের শুরুর 0 ফেলে দেয় (1712345678)।
        if ($phone !== null && preg_match('/^1\d{9}$/', $phone)) {
            $phone = '0'.$phone;
        }

        return [
            'name' => $row['name'],
            'roll_no' => (int) $row['roll_no'],
            'registration_no' => $row['registration_no'] !== null ? (int) $row['registration_no'] : null,
            'birth_certificate_no' => $row['birth_certificate_no'],
            'current_class_id' => $class?->id,
            'current_section_id' => $sectionId,
            'current_group_id' => $groupId,
            'session_year' => (int) ($row['session_year'] ?? now()->year),
            'gender' => $this->resolveEnum(Gender::class, $row['gender'], 'gender', $errors),
            'date_of_birth' => $this->resolveDate($row['date_of_birth'], 'date_of_birth', $errors),
            'blood_group' => $this->resolveEnum(BloodGroup::class, $row['blood_group'], 'blood_group', $errors),
            'religion' => $this->resolveEnum(Religion::class, $row['religion'], 'religion', $errors),
            'nationality' => $row['nationality'] ?? 'Bangladeshi',
            'father_name' => $row['father_name'],
            'father_occupation' => $row['father_occupation'],
            'mother_name' => $row['mother_name'],
            'mother_occupation' => $row['mother_occupation'],
            'guardian_name' => $row['guardian_name'],
            'guardian_relation' => $row['guardian_relation'],
            'guardian_occupation' => $row['guardian_occupation'],
            'guardian_phone' => $phone,
            'admission_date' => $this->resolveDate($row['admission_date'], 'admission_date', $errors),
            'status' => StudentStatus::Active->value,
            'present_address' => $row['present_address'],
            'permanent_address' => $row['permanent_address'],
            'same_address' => false,
            'main_optional_subject_id' => $mainOptionalSubjectId,
            'extra_optional_subject_id' => $extraOptionalSubjectId,
        ];
    }

    /**
     * @param  array<int, string>  $errors
     */
    private function resolveClass(?string $className, ?int $defaultClassId, array &$errors): ?Classes
    {
        $classes = $this->classes ??= Classes::all();

        if ($className === null) {
            $class = $defaultClassId ? $classes->firstWhere('id', $defaultClassId) : null;

            if (! $class) {
                $errors[] = 'class দিতে হবে';
            }

            return $class;
        }

        $class = $classes->first(fn (Classes $class): bool => $this->sameName($class->name, $className));

        if (! $class) {
            $errors[] = "class \"{$className}\" সিস্টেমে নেই";
        }

        return $class;
    }

    /**
     * @return Collection<int, string>
     */
    private function sectionsOf(Classes $class): Collection
    {
        return $this->sectionsByClass[$class->id] ??= Section::dropdownOptionsByClass($class->id);
    }

    /**
     * @return Collection<int, string>
     */
    private function optionalSubjectsOf(int $classId, int $groupId, bool $includeAllGroups): Collection
    {
        return $this->optionalSubjectsByClassGroup["{$classId}-{$groupId}-".(int) $includeAllGroups]
            ??= ClassGroupSubject::optionalSubjectOptions($classId, $groupId, $includeAllGroups);
    }

    /**
     * Groups of a class, keyed by ID — loaded once per class.
     */
    private function groupsOf(Classes $class): Collection
    {
        return $this->groupsByClass[$class->id] ??= $class->groups()->pluck('groups.name', 'groups.id');
    }

    /**
     * @param  Collection<int, string>  $options  id => name
     * @param  array<int, string>  $errors
     */
    private function resolveByName(?string $name, Collection $options, string $column, string $className, array &$errors): ?int
    {
        if ($name === null) {
            return null;
        }

        $id = $options->search(fn (?string $optionName): bool => $this->sameName((string) $optionName, $name));

        if ($id === false) {
            $errors[] = "{$column} \"{$name}\" {$className}-এ নেই";

            return null;
        }

        return (int) $id;
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

    /**
     * একই ক্লাস/section ও সেশনে ওই roll আগে থেকেই থাকলে ধরা হয় — একই ফাইল দুবার আপলোড
     * হলে ডুপ্লিকেট student তৈরি হওয়া ঠেকায়। Birth certificate মিলে গেলে সেটা বিদ্যমান
     * student-এর আপডেট, তাই তখন বাধা দেওয়া হয় না।
     *
     * @param  array<string, mixed>  $student
     */
    private function rollIsTaken(array $student): bool
    {
        return StudentProfile::query()
            ->active()
            ->where('current_class_id', $student['current_class_id'])
            ->where('current_section_id', $student['current_section_id'])
            ->where('session_year', $student['session_year'])
            ->where('roll_no', $student['roll_no'])
            ->when(
                filled($student['birth_certificate_no']),
                fn ($query) => $query->where(fn ($query) => $query
                    ->whereNull('birth_certificate_no')
                    ->orWhere('birth_certificate_no', '!=', $student['birth_certificate_no'])),
            )
            ->exists();
    }

    private function sameName(string $first, string $second): bool
    {
        return Str::lower(trim($first)) === Str::lower(trim($second));
    }
}
