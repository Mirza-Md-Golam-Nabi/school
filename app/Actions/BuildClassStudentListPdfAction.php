<?php

namespace App\Actions;

use App\Enums\StudentListColumn;
use App\Models\Classes;
use App\Models\StudentProfile;
use App\Support\Concerns\BuildsMpdfDocuments;
use Illuminate\Support\Collection;

class BuildClassStudentListPdfAction
{
    use BuildsMpdfDocuments;

    /**
     * Build a PDF listing every student in this class for the given session —
     * roll and name always, plus whichever optional columns were selected.
     *
     * @param  array<int, string>  $columns
     */
    public function handle(Classes $class, int $sessionYear, array $columns = [], string $orientation = 'P'): string
    {
        $students = StudentProfile::with(['user', 'section', 'group'])
            ->where('current_class_id', $class->id)
            ->where('session_year', $sessionYear)
            ->orderBy('roll_no')
            ->get();

        abort_if($students->isEmpty(), 404, 'এই ক্লাসে এই সেশনে এখনো কোনো student নেই।');

        $selectedColumns = $this->resolveColumns($columns);

        $rows = $students->map(fn (StudentProfile $student): array => [
            'roll' => sprintf('%02d', $student->roll_no),
            'name' => $student->user?->name,
            'values' => $selectedColumns
                ->map(fn (StudentListColumn $column) => $this->resolveColumnValue($student, $column))
                ->all(),
        ]);

        $mpdf = $this->makeMpdf('A4', $orientation === 'L' ? 'L' : 'P');

        $html = view('documents.student-list', [
            'class' => $class,
            'sessionYear' => $sessionYear,
            'columns' => $selectedColumns,
            'rows' => $rows,
        ])->render();

        $mpdf->WriteHTML($html);

        return $this->outputMpdfString($mpdf);
    }

    /**
     * @param  array<int, string>  $columns
     * @return Collection<int, StudentListColumn>
     */
    private function resolveColumns(array $columns): Collection
    {
        $selected = collect(StudentListColumn::cases())
            ->filter(fn (StudentListColumn $column) => in_array($column->value, $columns, true))
            ->values();

        if ($selected->isEmpty()) {
            $selected = collect(StudentListColumn::cases())
                ->filter(fn (StudentListColumn $column) => in_array($column->value, StudentListColumn::defaults(), true))
                ->values();
        }

        return $selected;
    }

    private function resolveColumnValue(StudentProfile $student, StudentListColumn $column): string
    {
        return match ($column) {
            StudentListColumn::Email => $student->user?->email ?? '-',
            StudentListColumn::Section => $student->section?->name ?? '-',
            StudentListColumn::Group => $student->group?->name ?? '-',
            StudentListColumn::RegistrationNo => $student->registration_no ?? '-',
            StudentListColumn::Gender => $student->gender?->getLabel() ?? '-',
            StudentListColumn::DateOfBirth => $student->date_of_birth?->format('d/m/Y') ?? '-',
            StudentListColumn::BloodGroup => $student->blood_group?->getLabel() ?? '-',
            StudentListColumn::Religion => $student->religion?->getLabel() ?? '-',
            StudentListColumn::FatherName => $student->father_name ?? '-',
            StudentListColumn::FatherOccupation => $student->father_occupation ?? '-',
            StudentListColumn::MotherName => $student->mother_name ?? '-',
            StudentListColumn::MotherOccupation => $student->mother_occupation ?? '-',
            StudentListColumn::GuardianName => $student->guardian_name ?? '-',
            StudentListColumn::GuardianRelation => $student->guardian_relation ?? '-',
            StudentListColumn::GuardianOccupation => $student->guardian_occupation ?? '-',
        };
    }
}
