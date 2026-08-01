<?php

namespace App\Filament\Student\Resources\ExamResults\Pages;

use App\Actions\BuildStudentMarksDetail;
use App\Filament\Student\Resources\ExamResults\ExamResultResource;
use App\Models\Exam;
use App\Models\StudentMeritRanking;
use Filament\Actions\Action;
use Filament\Resources\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;

class ViewExamResult extends Page
{
    protected static string $resource = ExamResultResource::class;

    protected string $view = 'filament.student.pages.view-exam-result';

    public Exam $record;

    public function mount(Exam $record): void
    {
        $this->record = $record->load(['examType', 'class']);
    }

    public function getBreadcrumbs(): array
    {
        return [
            ExamResultResource::getUrl() => 'Exam Results',
            '#' => $this->record->examType?->name.' — '.$this->record->session_year,
        ];
    }

    public function getTitle(): string
    {
        return ($this->record->examType?->name ?? 'Exam').' — '.$this->record->session_year;
    }

    public function getRankings(): Collection
    {
        return StudentMeritRanking::where('exam_id', $this->record->id)
            ->with(['student.user', 'section'])
            ->orderBy('class_rank')
            ->get();
    }

    public function getMyStudentId(): ?int
    {
        $student = ExamResultResource::getStudentProfile();

        return $student?->id;
    }

    public function getMyMarksDetail(int $rankingId): View
    {
        $ranking = StudentMeritRanking::findOrFail($rankingId);

        abort_unless((int) $ranking->student_id === (int) $this->getMyStudentId(), 403);

        ['rows' => $rows, 'summary' => $summary] = app(BuildStudentMarksDetail::class)->handle($ranking);

        return view('filament.shared.student-marks-detail', compact('rows', 'summary'));
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('back')
                ->label('Back to Results')
                ->icon(Heroicon::OutlinedArrowLeft)
                ->color('gray')
                ->url(ExamResultResource::getUrl()),
        ];
    }
}
