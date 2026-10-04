<?php

namespace App\Actions;

use App\Enums\BloodGroup;
use App\Enums\Gender;
use App\Enums\Religion;
use App\Models\Classes;
use App\Models\ClassGroupSubject;
use App\Models\Section;
use BackedEnum;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\XLSX\Writer;

class BuildStudentImportTemplateAction
{
    /**
     * Student import-এর ডেমো Excel ফাইল তৈরি করে। প্রথম sheet-এ শুধু কলামের নাম থাকে
     * (ইউজার এখানেই student-দের তথ্য লিখবে); দ্বিতীয় sheet-এ প্রতিটা কলামের ব্যাখ্যা ও
     * একটা উদাহরণ — উদাহরণটা আলাদা sheet-এ, যাতে ভুল করে সেটা import না হয়ে যায়। তৃতীয়
     * sheet-এ সিস্টেমে থাকা ক্লাস, section, group ইত্যাদির আসল নাম, যেখান থেকে ইউজার কপি
     * করে বসাবে। $class দিলে তালিকাটা শুধু সেই ক্লাসের।
     *
     * @return string xlsx ফাইলের binary content
     */
    public function handle(?Classes $class = null): string
    {
        $path = tempnam(sys_get_temp_dir(), 'student-import-template');
        $columns = ImportStudentsFromSpreadsheetAction::COLUMNS;
        $headerStyle = (new Style)->setFontBold();

        $writer = new Writer;
        $writer->openToFile($path);

        $writer->getCurrentSheet()->setName('Students');
        $writer->addRow(Row::fromValues(
            array_map(fn (string $name): string => $columns[$name]['required'] ? $name.'*' : $name, array_keys($columns)),
            $headerStyle,
        ));

        $writer->addNewSheetAndMakeItCurrent()->setName('Instructions');
        $writer->addRow(Row::fromValues(['Column', 'Required', 'Example', 'Note'], $headerStyle));

        foreach ($columns as $name => $column) {
            $example = $name === 'class' && $class ? $class->name : $column['example'];

            $writer->addRow(Row::fromValues([$name, $column['required'] ? 'Yes' : 'No', $example, $column['note']]));
        }

        $writer->addRow(Row::fromValues([]));
        $writer->addRow(Row::fromValues(['"Students" sheet-এর প্রথম রো (কলামের নাম) বদলাবেন না। দ্বিতীয় রো থেকে প্রতি রো-তে একজন student-এর তথ্য লিখুন। * চিহ্নিত কলাম অবশ্যই পূরণ করতে হবে।']));
        $writer->addRow(Row::fromValues(['class, section, group, gender ইত্যাদিতে ID নয়, নাম লিখুন — সিস্টেমে থাকা সঠিক নামগুলো "Allowed Values" sheet-এ আছে, সেখান থেকে কপি করুন।']));

        $writer->addNewSheetAndMakeItCurrent()->setName('Allowed Values');
        $writer->addRow(Row::fromValues(['Column', 'Class', 'Group', 'Value (কপি করে বসান)'], $headerStyle));

        foreach ($this->allowedValueRows($class) as $row) {
            $writer->addRow(Row::fromValues($row));
        }

        $writer->close();

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
}
