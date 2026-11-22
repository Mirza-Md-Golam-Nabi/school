<?php

namespace App\Actions;

use App\Enums\BloodGroup;
use App\Enums\Gender;
use App\Enums\Religion;
use BackedEnum;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Cell\DataValidation;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class BuildTeacherImportTemplateAction
{
    /**
     * লুকানো sheet — dropdown-এর তালিকাগুলো এখানে থাকে।
     */
    public const LISTS_SHEET = 'Lists';

    /**
     * যে কলামগুলোতে dropdown থাকে, আর তাদের enum।
     *
     * @var array<string, class-string<BackedEnum>>
     */
    private const DROPDOWN_COLUMNS = [
        'gender' => Gender::class,
        'blood_group' => BloodGroup::class,
        'religion' => Religion::class,
    ];

    /**
     * যে কলামগুলোতে শুরুতে 0 থাকে — Text ফরম্যাট না দিলে Excel সেগুলো বদলে ফেলে।
     *
     * @var array<int, string>
     */
    private const TEXT_COLUMNS = ['phone'];

    /**
     * Teacher import-এর ডেমো Excel ফাইল তৈরি করে। প্রথম sheet-এ শুধু কলামের নাম থাকে
     * (ইউজার এখানেই teacher-দের তথ্য লিখবে), আর gender, blood_group, religion-এর ঘরে
     * dropdown থাকে। দ্বিতীয় sheet-এ প্রতিটা কলামের ব্যাখ্যা ও একটা উদাহরণ — উদাহরণটা
     * আলাদা sheet-এ, যাতে ভুল করে সেটা import না হয়ে যায়।
     *
     * @return string xlsx ফাইলের binary content
     */
    public function handle(): string
    {
        $columns = ImportTeachersFromSpreadsheetAction::COLUMNS;

        $spreadsheet = new Spreadsheet;

        $teachers = $spreadsheet->getActiveSheet()->setTitle('Teachers');
        $teachers->fromArray(
            array_map(fn (string $name): string => $columns[$name]['required'] ? $name.'*' : $name, array_keys($columns)),
        );
        $teachers->getStyle('1:1')->getFont()->setBold(true);
        $teachers->freezePane('A2');

        $instructions = $spreadsheet->createSheet()->setTitle('Instructions');
        $instructionRows = [['Column', 'Required', 'Example', 'Note']];

        foreach ($columns as $name => $column) {
            $instructionRows[] = [$name, $column['required'] ? 'Yes' : 'No', $column['example'], $column['note']];
        }

        $instructionRows[] = [];
        $instructionRows[] = ['"Teachers" sheet-এর প্রথম রো (কলামের নাম) বদলাবেন না। দ্বিতীয় রো থেকে প্রতি রো-তে একজন teacher-এর তথ্য লিখুন। * চিহ্নিত কলাম অবশ্যই পূরণ করতে হবে।'];
        $instructionRows[] = ['gender, blood_group ও religion-এর ঘরে ক্লিক করলে dropdown আসবে — সেখান থেকে বেছে নিন।'];
        $instructionRows[] = ['কোনো তথ্য না থাকলে ঘরটা খালি রাখুন — "NULL" লিখতে হবে না। NULL, N/A বা - লিখলেও খালি ধরা হবে।'];
        $instructionRows[] = ['প্রত্যেক teacher-এর login password হবে "'.ImportTeachersFromSpreadsheetAction::DEFAULT_PASSWORD.'" — পরে প্রোফাইল থেকে বদলানো যাবে।'];

        $this->writeRows($instructions, $instructionRows);
        $instructions->getStyle('1:1')->getFont()->setBold(true);

        $this->addDropdowns($spreadsheet, $teachers);

        foreach ([$teachers, $instructions] as $sheet) {
            foreach (range(1, Coordinate::columnIndexFromString($sheet->getHighestColumn())) as $columnIndex) {
                $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($columnIndex))->setAutoSize(true);
            }
        }

        $spreadsheet->setActiveSheetIndex(0);

        $path = tempnam(sys_get_temp_dir(), 'teacher-import-template');
        (new Xlsx($spreadsheet))->save($path);
        $spreadsheet->disconnectWorksheets();

        $contents = file_get_contents($path);
        unlink($path);

        return $contents;
    }

    /**
     * Teachers sheet-এর dropdown কলামগুলোতে লুকানো Lists sheet-এর রেঞ্জ থেকে তালিকা বসায়,
     * আর আগে থেকে লেখা মানগুলো (যেমন nationality) প্রথম রো-তে বসায়।
     */
    private function addDropdowns(Spreadsheet $spreadsheet, Worksheet $teachers): void
    {
        $listsSheet = $spreadsheet->createSheet()->setTitle(self::LISTS_SHEET);
        $listsSheet->setSheetState(Worksheet::SHEETSTATE_HIDDEN);

        $lastRow = ImportTeachersFromSpreadsheetAction::MAX_ROWS + 1;
        $listColumnIndex = 0;

        foreach (array_keys(ImportTeachersFromSpreadsheetAction::COLUMNS) as $index => $name) {
            $teacherColumn = Coordinate::stringFromColumnIndex($index + 1);

            if (array_key_exists($name, ImportTeachersFromSpreadsheetAction::PREFILLED_VALUES)) {
                $teachers->setCellValue("{$teacherColumn}2", ImportTeachersFromSpreadsheetAction::PREFILLED_VALUES[$name]);
            }

            if (in_array($name, self::TEXT_COLUMNS, true)) {
                $teachers->getStyle("{$teacherColumn}2:{$teacherColumn}{$lastRow}")
                    ->getNumberFormat()
                    ->setFormatCode(NumberFormat::FORMAT_TEXT);
            }

            $enumClass = self::DROPDOWN_COLUMNS[$name] ?? null;

            if ($enumClass === null) {
                continue;
            }

            $options = array_map(fn (BackedEnum $case): string => (string) $case->value, $enumClass::cases());

            $listColumn = Coordinate::stringFromColumnIndex(++$listColumnIndex);
            $listsSheet->setCellValue("{$listColumn}1", $name);

            foreach ($options as $offset => $option) {
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
                ->setFormula1(sprintf("'%s'!\$%s\$2:\$%s\$%d", self::LISTS_SHEET, $listColumn, $listColumn, count($options) + 1));

            $teachers->setDataValidation("{$teacherColumn}2:{$teacherColumn}{$lastRow}", $validation);
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
