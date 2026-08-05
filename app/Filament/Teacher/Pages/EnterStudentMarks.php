<?php

namespace App\Filament\Teacher\Pages;

use App\Filament\Teacher\Resources\Exams\ExamResource;
use App\Models\Exam;
use App\Models\ExamSubjectConfig;
use App\Models\StudentProfile;
use App\Models\StudentResult;
use App\Models\Subject;
use BackedEnum;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Collection;
use Livewire\Attributes\Url;
use UnitEnum;

class EnterStudentMarks extends Page
{
    protected string $view = 'filament.shared.enter-student-marks';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPencilSquare;

    protected static string|UnitEnum|null $navigationGroup = null;

    protected static bool $shouldRegisterNavigation = false;

    #[Url(as: 'examId')]
    public int $examId = 0;

    #[Url(as: 'subjectId')]
    public int $subjectId = 0;

    #[Url(as: 'classId')]
    public int $classId = 0;

    /** @var array<int|string, array<string, mixed>> */
    public array $marks = [];

    public function mount(): void
    {
        abort_unless($this->examId && $this->subjectId && $this->classId, 404);

        abort_unless($this->teacherCanEnterMarks(), 403);

        $this->loadExistingResults();
    }

    /**
     * Only the teacher assigned (via TeacherSubject) to this class+subject for
     * the exam's session year may enter marks — re-checked in save() too, since
     * the URL-bound properties could otherwise be tampered with after mount.
     */
    private function teacherCanEnterMarks(): bool
    {
        $exam = Exam::find($this->examId);
        $teacherProfile = auth()->user()?->teacherProfile;

        return $exam && $teacherProfile?->isAssignedToTeach($this->classId, $this->subjectId, $exam->session_year);
    }

    private function loadExistingResults(): void
    {
        $results = StudentResult::where('exam_id', $this->examId)
            ->where('subject_id', $this->subjectId)
            ->get()
            ->keyBy('student_id');

        foreach ($this->getStudents() as $student) {
            $result = $results->get($student->id);
            $this->marks[$student->id] = [
                'mcq_marks' => $result?->mcq_marks,
                'written_marks' => $result?->written_marks,
                'practical_marks' => $result?->practical_marks,
                'is_absent' => (bool) ($result?->is_absent ?? false),
            ];
        }
    }

    public function getTitle(): string|Htmlable
    {
        $subject = Subject::find($this->subjectId);

        return 'Enter Marks — '.($subject?->name ?? 'Subject');
    }

    public function getBreadcrumbs(): array
    {
        return [
            ExamResource::getUrl('view', ['record' => $this->examId]) => 'Exam',
            '' => 'Enter Marks',
        ];
    }

    public function getBackUrl(): string
    {
        return ExamResource::getUrl('view', ['record' => $this->examId]);
    }

    public function getStudents(): Collection
    {
        return StudentProfile::with('user')
            ->where('current_class_id', $this->classId)
            ->active()
            ->orderBy('roll_no')
            ->get();
    }

    public function getSubjectConfig(): ?ExamSubjectConfig
    {
        return ExamSubjectConfig::where('exam_id', $this->examId)
            ->where('subject_id', $this->subjectId)
            ->first();
    }

    public function save(): void
    {
        abort_unless($this->teacherCanEnterMarks(), 403);

        foreach ($this->marks as $studentId => $mark) {
            $isAbsent = (bool) ($mark['is_absent'] ?? false);

            StudentResult::updateOrCreate(
                [
                    'exam_id' => $this->examId,
                    'subject_id' => $this->subjectId,
                    'student_id' => (int) $studentId,
                ],
                [
                    'class_id' => $this->classId,
                    'mcq_marks' => $isAbsent ? null : (isset($mark['mcq_marks']) && $mark['mcq_marks'] !== '' ? $mark['mcq_marks'] : null),
                    'written_marks' => $isAbsent ? null : (isset($mark['written_marks']) && $mark['written_marks'] !== '' ? $mark['written_marks'] : null),
                    'practical_marks' => $isAbsent ? null : (isset($mark['practical_marks']) && $mark['practical_marks'] !== '' ? $mark['practical_marks'] : null),
                    'is_absent' => $isAbsent,
                ]
            );
        }

        Notification::make()
            ->success()
            ->title('মার্ক্স সফলভাবে সেভ হয়েছে')
            ->send();
    }
}
