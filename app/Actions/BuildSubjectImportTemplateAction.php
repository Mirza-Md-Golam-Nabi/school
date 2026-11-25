<?php

namespace App\Actions;

use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Cell\DataValidation;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class BuildSubjectImportTemplateAction
{
    /**
     * যে কলামগুলো Excel নিজে থেকে বদলে ফেলে — code 0101 লিখলে শুরুর 0 হারায়।
     * Text ফরম্যাটে রাখলে যা লেখা হয় তা-ই থাকে।
     *
     * @var array<int, string>
     */
    private const TEXT_COLUMNS = ['code'];

    /**
     * Subject import-এর ডেমো Excel ফাইল তৈরি করে। প্রথম sheet-এ শুধু কলামের নাম থাকে
     * (ইউজার এখানেই subject-গুলো লিখবে), আর হ্যাঁ/না কলামগুলোতে yes/no dropdown থাকে।
     * দ্বিতীয় sheet-এ প্রতিটা কলামের ব্যাখ্যা ও উদাহরণ — উদাহরণটা আলাদা sheet-এ, যাতে
     * ভুল করে সেটা import না হয়ে যায়।
     *
     * @return string xlsx ফাইলের binary content
     */
    public function handle(): string
    {
        $columns = ImportSubjectsFromSpreadsheetAction::COLUMNS;

        $spreadsheet = new Spreadsheet;

        $subjects = $spreadsheet->getActiveSheet()->setTitle('Subjects');
        $subjects->fromArray(
            array_map(fn (string $name): string => $columns[$name]['required'] ? $name.'*' : $name, array_keys($columns)),
        );
        $subjects->getStyle('1:1')->getFont()->setBold(true);
        $subjects->freezePane('A2');

        $instructions = $spreadsheet->createSheet()->setTitle('Instructions');
        $instructionRows = [['Column', 'Required', 'Example', 'Note']];

        foreach ($columns as $name => $column) {
            $instructionRows[] = [$name, $column['required'] ? 'Yes' : 'No', $column['example'], $column['note']];
        }

        $instructionRows[] = [];
        $instructionRows[] = ['"Subjects" sheet-এর প্রথম রো (কলামের নাম) বদলাবেন না। দ্বিতীয় রো থেকে প্রতি রো-তে একটা subject লিখুন। * চিহ্নিত কলাম অবশ্যই পূরণ করতে হবে।'];
        $instructionRows[] = ['has_written, has_mcq, has_practical ও is_active-এর ঘরে ক্লিক করলে yes/no dropdown আসবে।'];
        $instructionRows[] = ['ফাইলে যে ক্রমে subject লিখবেন, সেই ক্রমেই বিদ্যমান subject-গুলোর পরে যোগ হবে (marksheet-এর ক্রম)।'];
        $instructionRows[] = ['কোনো তথ্য না থাকলে ঘরটা খালি রাখুন — "NULL" লিখতে হবে না।'];

        $this->writeRows($instructions, $instructionRows);
        $instructions->getStyle('1:1')->getFont()->setBold(true);

        $this->formatColumns($subjects);

        foreach ([$subjects, $instructions] as $sheet) {
            foreach (range(1, Coordinate::columnIndexFromString($sheet->getHighestColumn())) as $columnIndex) {
                $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($columnIndex))->setAutoSize(true);
            }
        }

        $spreadsheet->setActiveSheetIndex(0);

        $path = tempnam(sys_get_temp_dir(), 'subject-import-template');
        (new Xlsx($spreadsheet))->save($path);
        $spreadsheet->disconnectWorksheets();

        $contents = file_get_contents($path);
        unlink($path);

        return $contents;
    }

    /**
     * code কলাম Text ফরম্যাটে, আর হ্যাঁ/না কলামগুলোতে yes/no dropdown।
     */
    private function formatColumns(Worksheet $subjects): void
    {
        $lastRow = ImportSubjectsFromSpreadsheetAction::MAX_ROWS + 1;

        foreach (array_keys(ImportSubjectsFromSpreadsheetAction::COLUMNS) as $index => $name) {
            $column = Coordinate::stringFromColumnIndex($index + 1);

            if (in_array($name, self::TEXT_COLUMNS, true)) {
                foreach (["{$column}:{$column}", "{$column}2:{$column}{$lastRow}"] as $range) {
                    $subjects->getStyle($range)
                        ->getNumberFormat()
                        ->setFormatCode(NumberFormat::FORMAT_TEXT);
                }
            }

            if (! (ImportSubjectsFromSpreadsheetAction::COLUMNS[$name]['boolean'] ?? false)) {
                continue;
            }

            $validation = new DataValidation;
            $validation->setType(DataValidation::TYPE_LIST)
                ->setErrorStyle(DataValidation::STYLE_STOP)
                ->setAllowBlank(true)
                ->setShowDropDown(true)
                ->setShowErrorMessage(true)
                ->setErrorTitle('ভুল মান')
                ->setError("{$name}-এর জন্য yes অথবা no বেছে নিন।")
                ->setFormula1('"yes,no"');

            $subjects->setDataValidation("{$column}2:{$column}{$lastRow}", $validation);
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
