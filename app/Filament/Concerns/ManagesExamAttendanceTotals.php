<?php

namespace App\Filament\Concerns;

use App\Actions\Attendance\ResolveStudentAttendanceTotals;
use App\Actions\Attendance\SaveExamAttendanceSummariesAction;
use App\Enums\StudentStatus;
use App\Models\Classes;
use App\Models\Exam;
use App\Models\StudentAttendanceSummary;
use App\Models\StudentProfile;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Support\Enums\Size;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Collection;
use Livewire\Attributes\Url;

/**
 * The "Attendance Totals" page: the class teacher or an admin writes each
 * student's total attendance up to an exam (e.g. when most days were taken on
 * paper), which the marksheet and attendance rankings then show instead of
 * the count of daily attendance rows.
 */
trait ManagesExamAttendanceTotals
{
    #[Url(as: 'classId')]
    public int $classId = 0;

    #[Url(as: 'examId')]
    public int $examId = 0;

    public int|string|null $workingDays = null;

    /** @var array<int|string, int|string|null> present days keyed by student id */
    public array $presentDays = [];

    /**
     * Whether the logged-in user may write totals for this class.
     */
    abstract protected function canManageClass(int $classId): bool;

    /**
     * @return Collection<int, Classes>
     */
    abstract public function getClasses(): Collection;

    public function mount(): void
    {
        if ($this->examId && ($exam = Exam::find($this->examId))) {
            $this->classId = $exam->class_id;
        }

        if (! $this->classId) {
            $this->classId = (int) ($this->getClasses()->first()?->id ?? 0);
        }

        if ($this->classId) {
            abort_unless($this->canManageClass($this->classId), 403);
        }

        if (! $this->examId || $this->resolveExam()?->class_id !== $this->classId) {
            $this->examId = (int) ($this->getExams()->first()?->id ?? 0);
        }

        $this->loadTotals();
    }

    public function updatedClassId(): void
    {
        abort_unless($this->canManageClass($this->classId), 403);

        $this->examId = (int) ($this->getExams()->first()?->id ?? 0);

        $this->loadTotals();
    }

    public function updatedExamId(): void
    {
        $this->loadTotals();
    }

    /**
     * Exams of the selected class, newest first.
     *
     * @return Collection<int, Exam>
     */
    public function getExams(): Collection
    {
        if (! $this->classId) {
            return collect();
        }

        return Exam::with('examType:id,name')
            ->where('class_id', $this->classId)
            ->orderByDesc('session_year')
            ->orderByDesc('start_date')
            ->orderByDesc('id')
            ->get();
    }

    /**
     * Active students of the class, plus anyone who already has a written
     * total for the exam.
     *
     * @return Collection<int, StudentProfile>
     */
    public function getStudents(): Collection
    {
        $exam = $this->resolveExam();

        if (! $exam) {
            return collect();
        }

        $idsWithTotals = StudentAttendanceSummary::where('exam_id', $exam->id)->pluck('student_id');

        return StudentProfile::with('user:id,name')
            ->withTrashed()
            ->where(function ($query) use ($exam, $idsWithTotals): void {
                $query->where(fn ($active) => $active
                    ->where('current_class_id', $exam->class_id)
                    ->where('status', StudentStatus::Active)
                    ->whereNull('deleted_at'))
                    ->orWhereIn('id', $idsWithTotals);
            })
            ->orderBy('roll_no')
            ->get();
    }

    /**
     * The daily-attendance numbers for the exam's class and session year,
     * shown next to each box for reference.
     *
     * @return array{working_days: int, present_days: Collection<int, int>}
     */
    public function getDeviceTotals(): array
    {
        $exam = $this->resolveExam();

        if (! $exam) {
            return ['working_days' => 0, 'present_days' => collect()];
        }

        $resolver = app(ResolveStudentAttendanceTotals::class);

        return [
            'working_days' => $resolver->deviceWorkingDays($exam->session_year, $exam->class_id),
            'present_days' => $resolver->devicePresentCounts($exam->session_year, $exam->class_id, $this->getStudents()->pluck('id')),
        ];
    }

    /**
     * Student ids that already have a saved written total for the exam.
     *
     * @return Collection<int, int>
     */
    public function getSavedStudentIds(): Collection
    {
        return StudentAttendanceSummary::where('exam_id', $this->examId)->pluck('student_id');
    }

    public function save(): void
    {
        $exam = $this->resolveExam();

        abort_unless($exam && $this->canManageClass($exam->class_id), 403);

        $this->resetErrorBag();

        $saved = app(SaveExamAttendanceSummariesAction::class)->handle(
            $exam,
            $this->workingDays,
            $this->presentDays,
            auth()->id(),
        );

        Notification::make()
            ->success()
            ->title('মোট উপস্থিতি সেভ হয়েছে')
            ->body("{$saved} জন ছাত্রের মোট উপস্থিতি সেভ হয়েছে।")
            ->send();
    }

    /**
     * "Back to device count" button, behind Filament's confirmation modal.
     */
    public function resetToDeviceCountAction(): Action
    {
        return Action::make('resetToDeviceCount')
            ->label('ডিভাইসের হিসাবে ফেরাও')
            ->icon(Heroicon::OutlinedArrowPath)
            ->color('gray')
            ->size(Size::Small)
            ->requiresConfirmation()
            ->modalIcon(Heroicon::OutlinedExclamationTriangle)
            ->modalHeading('ডিভাইসের হিসাবে ফেরাবেন?')
            ->modalDescription('এই পরীক্ষার সব লেখা মোট উপস্থিতি মুছে যাবে। মার্কশিট আর attendance ranking আবার ডিভাইসের হিসাব দেখাবে।')
            ->modalSubmitActionLabel('হ্যাঁ, ফেরাও')
            ->disabled(fn (): bool => $this->getSavedStudentIds()->isEmpty())
            ->action(fn () => $this->resetToDeviceCount());
    }

    /**
     * Removes every written total of the exam, so the marksheet and rankings
     * go back to the daily attendance count.
     */
    protected function resetToDeviceCount(): void
    {
        $exam = $this->resolveExam();

        abort_unless($exam && $this->canManageClass($exam->class_id), 403);

        $removed = app(SaveExamAttendanceSummariesAction::class)->clear($exam);

        $this->resetErrorBag();
        $this->loadTotals();

        Notification::make()
            ->success()
            ->title('ডিভাইসের হিসাবে ফেরানো হয়েছে')
            ->body("{$removed} জন ছাত্রের লেখা মোট উপস্থিতি মুছে ফেলা হয়েছে।")
            ->send();
    }

    public function resolveExam(): ?Exam
    {
        return $this->examId ? Exam::with(['examType:id,name', 'class:id,name'])->find($this->examId) : null;
    }

    /**
     * Fills the boxes with the saved totals, or with the daily-attendance
     * count where nothing is saved yet.
     */
    private function loadTotals(): void
    {
        $this->workingDays = null;
        $this->presentDays = [];

        if (! $this->resolveExam()) {
            return;
        }

        $summaries = StudentAttendanceSummary::where('exam_id', $this->examId)->get()->keyBy('student_id');
        $deviceTotals = $this->getDeviceTotals();

        $this->workingDays = $summaries->first()?->working_days ?? ($deviceTotals['working_days'] ?: null);

        foreach ($this->getStudents() as $student) {
            $this->presentDays[$student->id] = $summaries->get($student->id)?->present_days
                ?? $deviceTotals['present_days']->get($student->id, 0);
        }
    }
}
