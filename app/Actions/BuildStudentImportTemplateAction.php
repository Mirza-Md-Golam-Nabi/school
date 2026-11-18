<?php

namespace App\Actions;

use App\Enums\BloodGroup;
use App\Enums\Gender;
use App\Enums\Religion;
use App\Models\Classes;
use App\Models\ClassGroupSubject;
use App\Models\Section;
use BackedEnum;
use Illuminate\Support\Collection;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Cell\DataValidation;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class BuildStudentImportTemplateAction
{
    /**
     * লুকানো sheet — dropdown-এর তালিকাগুলো এখানে থাকে। Excel-এ সরাসরি লেখা তালিকা
     * ২৫৫ অক্ষরের বেশি হতে পারে না, তাই তালিকা sheet-এর রেঞ্জ থেকে নেওয়া হয়।
     */
    public const LISTS_SHEET = 'Lists';

    /**
     * যে কলামগুলোতে লম্বা সংখ্যা/শুরুতে 0 থাকে — Text ফরম্যাট না দিলে Excel সেগুলো বদলে ফেলে।
     *
     * @var array<int, string>
     */
    private const TEXT_COLUMNS = ['birth_certificate_no', 'guardian_phone'];

    /**
     * Student import-এর ডেমো Excel ফাইল তৈরি করে। প্রথম sheet-এ শুধু কলামের নাম থাকে
     * (ইউজার এখানেই student-দের তথ্য লিখবে), আর gender, class, section ইত্যাদির ঘরে
     * dropdown থাকে। দ্বিতীয় sheet-এ প্রতিটা কলামের ব্যাখ্যা ও একটা উদাহরণ — উদাহরণটা
     * আলাদা sheet-এ, যাতে ভুল করে সেটা import না হয়ে যায়। তৃতীয় sheet-এ সিস্টেমে থাকা
     * ক্লাস, section, group ইত্যাদির আসল নাম। $class দিলে তালিকাগুলো শুধু সেই ক্লাসের।
     *
     * @return string xlsx ফাইলের binary content
     */
    public function handle(?Classes $class = null): string
    {
        $columns = ImportStudentsFromSpreadsheetAction::COLUMNS;
        $allowedValueRows = $this->allowedValueRows($class);

        $spreadsheet = new Spreadsheet;

        $students = $spreadsheet->getActiveSheet()->setTitle('Students');
        $students->fromArray(
            array_map(fn (string $name): string => $columns[$name]['required'] ? $name.'*' : $name, array_keys($columns)),
        );
        $students->getStyle('1:1')->getFont()->setBold(true);
        $students->freezePane('A2');

        $instructions = $spreadsheet->createSheet()->setTitle('Instructions');
        $instructionRows = [['Column', 'Required', 'Example', 'Note']];

        foreach ($columns as $name => $column) {
            $example = $name === 'class' && $class ? $class->name : $column['example'];
            $instructionRows[] = [$name, $column['required'] ? 'Yes' : 'No', $example, $column['note']];
        }

        $instructionRows[] = [];
        $instructionRows[] = ['"Students" sheet-এর প্রথম রো (কলামের নাম) বদলাবেন না। দ্বিতীয় রো থেকে প্রতি রো-তে একজন student-এর তথ্য লিখুন। * চিহ্নিত কলাম অবশ্যই পূরণ করতে হবে।'];
        $instructionRows[] = ['gender, class, section, group, blood_group, religion ও optional subject-এর ঘরে ক্লিক করলে dropdown আসবে — সেখান থেকে বেছে নিন। ID (1, 2, 3) নয়, নাম লিখতে হয়।'];
        $instructionRows[] = ['কোনো তথ্য না থাকলে ঘরটা খালি রাখুন — "NULL" লিখতে হবে না। NULL, N/A বা - লিখলেও খালি ধরা হবে।'];
        $instructionRows[] = ['সব ক্লাসের ফাইলে section/group-এর dropdown-এ সব ক্লাসের নাম থাকে — student-এর ক্লাসে যেটা আছে সেটাই বাছুন, ভুল হলে import-এর সময় জানানো হবে।'];

        $this->writeRows($instructions, $instructionRows);
        $instructions->getStyle('1:1')->getFont()->setBold(true);

        $allowedValues = $spreadsheet->createSheet()->setTitle('Allowed Values');
        $this->writeRows($allowedValues, [['Column', 'Class', 'Group', 'Value (কপি করে বসান)'], ...$allowedValueRows]);
        $allowedValues->getStyle('1:1')->getFont()->setBold(true);

        $this->addDropdowns($spreadsheet, $students, $this->dropdownLists($allowedValueRows));

        foreach ([$students, $instructions, $allowedValues] as $sheet) {
            foreach (range(1, Coordinate::columnIndexFromString($sheet->getHighestColumn())) as $columnIndex) {
                $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($columnIndex))->setAutoSize(true);
            }
        }

        $spreadsheet->setActiveSheetIndex(0);

        $path = tempnam(sys_get_temp_dir(), 'student-import-template');
        (new Xlsx($spreadsheet))->save($path);
        $spreadsheet->disconnectWorksheets();

        $contents = file_get_contents($path);
        unlink($path);

        return $contents;
    }

    /**
     * প্রতি রো-তে একটা করে মান, যাতে ঘরটা সরাসরি কপি করা যায় — নামগুলো সেই উৎস থেকেই আসে
     * যেখান থেকে import মেলায়, তাই এখানে যা আছে তা-ই import-এ গ্রহণযোগ্য।
     *
     * @return array<int, array{0: string, 1: string, 2: string, 3: string}>
     */
    private function allowedValueRows(?Classes $class): array
    {
        $rows = [];

        foreach (['gender' => Gender::class, 'blood_group' => BloodGroup::class, 'religion' => Religion::class] as $column => $enumClass) {
            foreach ($enumClass::cases() as $case) {
                /** @var BackedEnum $case */
                $rows[] = [$column, '', '', (string) $case->value];
            }
        }

        $classes = $class ? collect([$class]) : Classes::orderBy('order')->get();

        foreach ($classes as $schoolClass) {
            $rows[] = ['class', '', '', $schoolClass->name];
        }

        foreach ($classes as $schoolClass) {
            foreach (Section::dropdownOptionsByClass($schoolClass->id) as $sectionName) {
                $rows[] = ['section', $schoolClass->name, '', $sectionName];
            }

            $groups = $schoolClass->groups()->orderBy('groups.name')->pluck('groups.name', 'groups.id');

            foreach ($groups as $groupName) {
                $rows[] = ['group', $schoolClass->name, '', $groupName];
            }

            foreach ($groups as $groupId => $groupName) {
                foreach (ClassGroupSubject::optionalSubjectOptions($schoolClass->id, $groupId, includeAllGroups: false) as $subjectName) {
                    $rows[] = ['main_optional_subject', $schoolClass->name, $groupName, $subjectName];
                }

                foreach (ClassGroupSubject::optionalSubjectOptions($schoolClass->id, $groupId) as $subjectName) {
                    $rows[] = ['extra_optional_subject', $schoolClass->name, $groupName, $subjectName];
                }
            }
        }

        return $rows;
    }

    /**
     * Allowed Values-এর রো থেকে কলাম অনুযায়ী dropdown তালিকা — একই নাম একাধিক ক্লাসে
     * থাকলে (যেমন section "A") একবারই আসে।
     *
     * @param  array<int, array{0: string, 1: string, 2: string, 3: string}>  $allowedValueRows
     * @return Collection<string, Collection<int, string>>
     */
    private function dropdownLists(array $allowedValueRows): Collection
    {
        return collect($allowedValueRows)
            ->groupBy(0)
            ->map(fn (Collection $rows): Collection => $rows->pluck(3)->unique()->values());
    }

    /**
     * Students sheet-এর প্রতিটা dropdown কলামে লুকানো Lists sheet-এর রেঞ্জ থেকে তালিকা বসায়।
     * তালিকার বাইরের মান দিলে Excel থামিয়ে দেয়; ঘর খালি রাখা যায়।
     *
     * @param  Collection<string, Collection<int, string>>  $lists
     */
    private function addDropdowns(Spreadsheet $spreadsheet, Worksheet $students, Collection $lists): void
    {
        $listsSheet = $spreadsheet->createSheet()->setTitle(self::LISTS_SHEET);
        $listsSheet->setSheetState(Worksheet::SHEETSTATE_HIDDEN);

        $columnNames = array_keys(ImportStudentsFromSpreadsheetAction::COLUMNS);
        $lastRow = ImportStudentsFromSpreadsheetAction::MAX_ROWS + 1;
        $listColumnIndex = 0;

        $prefilledValues = ImportStudentsFromSpreadsheetAction::prefilledValues();

        foreach ($columnNames as $index => $name) {
            $studentColumn = Coordinate::stringFromColumnIndex($index + 1);

            if (array_key_exists($name, $prefilledValues)) {
                $students->setCellValue("{$studentColumn}2", $prefilledValues[$name]);
            }

            if (in_array($name, self::TEXT_COLUMNS, true)) {
                $students->getStyle("{$studentColumn}2:{$studentColumn}{$lastRow}")
                    ->getNumberFormat()
                    ->setFormatCode(NumberFormat::FORMAT_TEXT);
            }

            $options = $lists->get($name);

            if ($options === null || $options->isEmpty()) {
                continue;
            }

            $listColumn = Coordinate::stringFromColumnIndex(++$listColumnIndex);
            $listsSheet->setCellValue("{$listColumn}1", $name);

            foreach ($options->values() as $offset => $option) {
                $listsSheet->setCellValueExplicit("{$listColumn}".($offset + 2), $option, DataType::TYPE_STRING);
            }

            $validation = new DataValidation;
            $validation->setType(DataValidation::TYPE_LIST)
                ->setErrorStyle(DataValidation::STYLE_STOP)
                ->setAllowBlank(true)
                ->setShowDropDown(true)
                ->setShowErrorMessage(true)
                ->setErrorTitle('ভুল মান')
                ->setError("{$name}-এর জন্য dropdown থেকে একটা মান বেছে নিন।")
                ->setFormula1(sprintf("'%s'!\$%s\$2:\$%s\$%d", self::LISTS_SHEET, $listColumn, $listColumn, $options->count() + 1));

            $students->setDataValidation("{$studentColumn}2:{$studentColumn}{$lastRow}", $validation);
        }
    }

    /**
     * @param  array<int, array<int, string>>  $rows
     */
    private function writeRows(Worksheet $sheet, array $rows): void
    {
        foreach ($rows as $rowIndex => $row) {
            foreach (array_values($row) as $columnIndex => $value) {
                $sheet->setCellValueExplicit(Coordinate::stringFromColumnIndex($columnIndex + 1).($rowIndex + 1), $value, DataType::TYPE_STRING);
            }
        }
    }
}
