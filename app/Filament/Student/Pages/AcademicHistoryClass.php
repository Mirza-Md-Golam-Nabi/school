<?php

namespace App\Filament\Student\Pages;

use App\Enums\AttendanceStatus;
use App\Enums\ExamConfigType;
use App\Filament\Student\Resources\ExamResults\ExamResultResource;
use App\Filament\Student\Resources\FeeInvoices\Pages\AcademicHistoryFees;
use App\Models\Attendance;
use App\Models\Exam;
use App\Models\GradeScale;
use App\Models\StudentClassHistory;
use App\Models\StudentFeeInvoice;
use App\Models\StudentMeritRanking;
use App\Models\StudentProfile;
use App\Models\User;
use Filament\Pages\Page;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Url;

class AcademicHistoryClass extends Page
{
    protected string $view = 'filament.student.pages.academic-history-class';

    protected static bool $shouldRegisterNavigation = false;

    #[Url(as: 'history')]
    public int $history = 0;

    public StudentClassHistory $record;

    public function mount(): void
    {
        abort_unless($this->history, 404);

        $profile = $this->getStudentProfile();

        $this->record = StudentClassHistory::where('id', $this->history)
            ->where('student_id', $profile?->id)
            ->with('class')
            ->firstOrFail();
    }

    public function getTitle(): string|Htmlable
    {
        return ($this->record->class?->name ?? 'Class').' — '.$this->record->session_year;
    }

    public function getBreadcrumbs(): array
    {
        return [
            AcademicHistory::getUrl() => 'Academic History',
            '' => $this->getTitle(),
        ];
    }

    /**
     * @return array{workingDays: int, presentDays: int, rank: ?int, totalStudents: int}
     */
    public function getAttendanceSummary(): array
    {
        $profile = $this->getStudentProfile();

        $workingDays = Attendance::where('attendable_type', StudentProfile::class)
            ->where('class_id', $this->record->class_id)
            ->whereYear('date', $this->record->session_year)
            ->distinct('date')
            ->count('date');

        // The classmate roster for that class-year comes from history, not current
        // enrollment, so a student who has since moved on still counts toward ranking.
        $classmateIds = StudentClassHistory::where('class_id', $this->record->class_id)
            ->where('session_year', $this->record->session_year)
            ->pluck('student_id');

        $presentCounts = Attendance::where('attendable_type', StudentProfile::class)
            ->whereIn('attendable_id', $classmateIds)
            ->where('status', AttendanceStatus::Present)
            ->where('class_id', $this->record->class_id)
            ->whereYear('date', $this->record->session_year)
            ->selectRaw('attendable_id, count(*) as present_count')
            ->groupBy('attendable_id')
            ->pluck('present_count', 'attendable_id');

        $ranking = $classmateIds
            ->mapWithKeys(fn (int $id): array => [$id => $presentCounts->get($id, 0)])
            ->sortDesc()
            ->keys()
            ->values();

        $rank = $ranking->search($profile?->id);

        return [
            'workingDays' => $workingDays,
            'presentDays' => $presentCounts->get($profile?->id, 0),
            'rank' => $rank === false ? null : $rank + 1,
            'totalStudents' => $classmateIds->count(),
        ];
    }

    /**
     * @return array{totalPaid: float, url: string}
     */
    public function getFeeSummary(): array
    {
        $profile = $this->getStudentProfile();

        $totalPaid = StudentFeeInvoice::where('student_id', $profile?->id)
            ->where('year', $this->record->session_year)
            ->withSum('payments as paid_amount', 'amount_paid')
            ->get()
            ->sum(fn (StudentFeeInvoice $invoice) => $invoice->paid_amount ?? 0);

        return [
            'totalPaid' => (float) $totalPaid,
            'url' => AcademicHistoryFees::getUrl(['year' => $this->record->session_year], panel: 'student'),
        ];
    }

    /**
     * @return array{classRank: ?int, gpa: string, gradeLabel: string, url: string}|null
     */
    public function getExamResultSummary(): ?array
    {
        $profile = $this->getStudentProfile();

        $mainExamId = Exam::where('class_id', $this->record->class_id)
            ->where('session_year', $this->record->session_year)
            ->whereHas('examType.examTypeConfig', fn ($query) => $query->where('type', ExamConfigType::Main))
            ->value('id');

        if (! $mainExamId) {
            return null;
        }

        $ranking = StudentMeritRanking::where('exam_id', $mainExamId)
            ->where('student_id', $profile?->id)
            ->first();

        if (! $ranking) {
            return null;
        }

        return [
            'classRank' => $ranking->class_rank,
            'gpa' => number_format((float) $ranking->gpa, 2),
            'gradeLabel' => GradeScale::fromGpa((float) $ranking->gpa)?->letter_grade ?? '—',
            'url' => ExamResultResource::getUrl('view', ['record' => $mainExamId], panel: 'student'),
        ];
    }

    private function getStudentProfile(): ?StudentProfile
    {
        /** @var User $user */
        $user = Auth::user();

        return $user->studentProfile;
    }
}
