<?php

namespace App\Actions;

use App\Enums\AddressType;
use App\Enums\TeacherListColumn;
use App\Models\Address;
use App\Models\TeacherProfile;
use App\Support\Concerns\BuildsMpdfDocuments;
use Illuminate\Support\Collection;

class BuildTeacherListPdfAction
{
    use BuildsMpdfDocuments;

    /**
     * Build a PDF listing every (non-deleted) teacher, sorted by name —
     * serial and name always, plus whichever optional columns were selected.
     *
     * @param  array<int, string>  $columns
     */
    public function handle(array $columns = [], string $orientation = 'P'): string
    {
        $teachers = TeacherProfile::with(['user', 'addresses'])
            ->orderedByUserName()
            ->get();

        abort_if($teachers->isEmpty(), 404, 'এখনো কোনো teacher যোগ করা হয়নি।');

        $selectedColumns = $this->resolveColumns($columns);

        $rows = $teachers->map(fn (TeacherProfile $teacher, int $index): array => [
            'serial' => sprintf('%02d', $index + 1),
            'name' => $teacher->user?->name ?? '-',
            'values' => $selectedColumns
                ->map(fn (TeacherListColumn $column) => $this->resolveColumnValue($teacher, $column))
                ->all(),
        ]);

        $mpdf = $this->makeMpdf('A4', $orientation === 'L' ? 'L' : 'P');

        $html = view('documents.teacher-list', [
            'columns' => $selectedColumns,
            'rows' => $rows,
        ])->render();

        $mpdf->WriteHTML($html);

        return $this->outputMpdfString($mpdf);
    }

    /**
     * @param  array<int, string>  $columns
     * @return Collection<int, TeacherListColumn>
     */
    private function resolveColumns(array $columns): Collection
    {
        $selected = collect(TeacherListColumn::cases())
            ->filter(fn (TeacherListColumn $column) => in_array($column->value, $columns, true))
            ->values();

        if ($selected->isEmpty()) {
            $selected = collect(TeacherListColumn::cases())
                ->filter(fn (TeacherListColumn $column) => in_array($column->value, TeacherListColumn::defaults(), true))
                ->values();
        }

        return $selected;
    }

    private function resolveColumnValue(TeacherProfile $teacher, TeacherListColumn $column): string
    {
        return match ($column) {
            TeacherListColumn::Designation => $teacher->designation ?: '-',
            TeacherListColumn::Department => $teacher->department ?: '-',
            TeacherListColumn::Qualification => $teacher->qualification ?: '-',
            TeacherListColumn::Phone => $teacher->user?->phone ?: '-',
            TeacherListColumn::Email => $teacher->user?->email ?: '-',
            TeacherListColumn::JoiningDate => $teacher->joining_date?->format('d/m/Y') ?? '-',
            TeacherListColumn::Gender => $teacher->gender?->getLabel() ?? '-',
            TeacherListColumn::DateOfBirth => $teacher->date_of_birth?->format('d/m/Y') ?? '-',
            TeacherListColumn::BloodGroup => $teacher->blood_group?->getLabel() ?? '-',
            TeacherListColumn::Religion => $teacher->religion?->getLabel() ?? '-',
            TeacherListColumn::Nationality => $teacher->nationality ?: '-',
            TeacherListColumn::Status => $teacher->status?->getLabel() ?? '-',
            TeacherListColumn::PresentAddress => $this->addressOfType($teacher, AddressType::Present),
            TeacherListColumn::PermanentAddress => $this->addressOfType($teacher, AddressType::Permanent),
        };
    }

    private function addressOfType(TeacherProfile $teacher, AddressType $type): string
    {
        return $teacher->addresses
            ->first(fn (Address $address): bool => $address->type === $type)
            ?->address ?: '-';
    }
}
