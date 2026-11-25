<?php

namespace App\Actions;

use App\Enums\SubjectListColumn;
use App\Models\Subject;
use App\Support\Concerns\BuildsMpdfDocuments;
use Illuminate\Support\Collection;

class BuildSubjectListPdfAction
{
    use BuildsMpdfDocuments;

    /**
     * Build a PDF listing every (non-deleted) subject in the school-wide subject
     * order — serial and name always, plus whichever optional columns were selected.
     *
     * @param  array<int, string>  $columns
     */
    public function handle(array $columns = [], string $orientation = 'P'): string
    {
        $subjects = Subject::query()->ordered()->get();

        abort_if($subjects->isEmpty(), 404, 'এখনো কোনো subject যোগ করা হয়নি।');

        $selectedColumns = $this->resolveColumns($columns);

        $rows = $subjects->values()->map(fn (Subject $subject, int $index): array => [
            'serial' => sprintf('%02d', $index + 1),
            'name' => $subject->name,
            'values' => $selectedColumns
                ->map(fn (SubjectListColumn $column) => $this->resolveColumnValue($subject, $column))
                ->all(),
        ]);

        $mpdf = $this->makeMpdf('A4', $orientation === 'L' ? 'L' : 'P');

        $html = view('documents.subject-list', [
            'columns' => $selectedColumns,
            'rows' => $rows,
        ])->render();

        $mpdf->WriteHTML($html);

        return $this->outputMpdfString($mpdf);
    }

    /**
     * কোনো কলাম না পাঠালে ডিফল্ট (সব কলাম)।
     *
     * @param  array<int, string>  $columns
     * @return Collection<int, SubjectListColumn>
     */
    private function resolveColumns(array $columns): Collection
    {
        $wanted = $columns !== [] ? $columns : SubjectListColumn::defaults();

        return collect(SubjectListColumn::cases())
            ->filter(fn (SubjectListColumn $column) => in_array($column->value, $wanted, true))
            ->values();
    }

    private function resolveColumnValue(Subject $subject, SubjectListColumn $column): string
    {
        return match ($column) {
            SubjectListColumn::NameBn => $subject->name_bn ?: '-',
            SubjectListColumn::Code => $subject->code ?: '-',
            SubjectListColumn::Written => $subject->has_written ? 'Yes' : 'No',
            SubjectListColumn::Mcq => $subject->has_mcq ? 'Yes' : 'No',
            SubjectListColumn::Practical => $subject->has_practical ? 'Yes' : 'No',
            SubjectListColumn::Status => $subject->is_active ? 'Active' : 'Inactive',
        };
    }
}
