<?php

namespace App\Filament\Resources\StudentProfiles\Pages;

use App\Actions\PromoteStudentsAction;
use App\Enums\ExamConfigType;
use App\Enums\PromotionStatus;
use App\Filament\Resources\StudentProfiles\StudentProfileResource;
use App\Models\Classes;
use App\Models\Exam;
use App\Models\Section;
use App\Models\StudentMeritRanking;
use App\Models\StudentProfile;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Page;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Livewire\Attributes\Url;

class PromoteStudents extends Page
{
    protected static string $resource = StudentProfileResource::class;

    protected string $view = 'filament.resources.student-profiles.pages.promote-students';

    #[Url(as: 'classId')]
    public int $classId = 0;

    /** @var array<int, array<string, mixed>> */
    public array $promotions = [];

    public bool $hasMainExamResults = false;

    public function mount(): void
    {
        abort_unless($this->classId, 404);

        $defaultClassId = $this->getNextClass()?->id ?? $this->classId;
        $students = $this->getStudents();
        $meritRanks = $students->isNotEmpty()
            ? $this->getMainExamMeritRanks($students->first()->session_year)
            : collect();

        $this->hasMainExamResults = $meritRanks->isNotEmpty();

        foreach ($students as $student) {
            $this->promotions[$student->id] = [
                'status' => PromotionStatus::Promoted->value,
                'class_id' => $defaultClassId,
                'section_id' => null,
                'group_id' => null,
                'roll_no' => $meritRanks->get($student->id),
                'remarks' => null,
            ];
        }
    }

    /**
     * New roll = the student's class rank in this class's Main exam for the
     * given session year. Empty when no Main exam (or no ranking) exists —
     * the old roll number is never used as a fallback.
     */
    private function getMainExamMeritRanks(int $sessionYear): Collection
    {
        $mainExamId = Exam::where('class_id', $this->classId)
            ->where('session_year', $sessionYear)
            ->whereHas('examType.examTypeConfig', fn ($query) => $query->where('type', ExamConfigType::Main))
            ->value('id');

        if (! $mainExamId) {
            return collect();
        }

        return StudentMeritRanking::where('exam_id', $mainExamId)->pluck('class_rank', 'student_id');
    }

    public function updated(string $name): void
    {
        if (! Str::is('promotions.*.class_id', $name)) {
            return;
        }

        $studentId = (int) explode('.', $name)[1];

        $this->promotions[$studentId]['section_id'] = null;
        $this->promotions[$studentId]['group_id'] = null;
    }

    public function getTitle(): string|Htmlable
    {
        return 'Promote Students — '.($this->resolveClass()?->name ?? 'Class');
    }

    public function getBreadcrumbs(): array
    {
        return [
            StudentProfileResource::getUrl() => 'Students',
            StudentProfileResource::getUrl('students-by-class', ['classId' => $this->classId]) => $this->resolveClass()?->name ?? 'Class',
            '' => 'Promote',
        ];
    }

    public function getBackUrl(): string
    {
        return StudentProfileResource::getUrl('students-by-class', ['classId' => $this->classId]);
    }

    public function getStudents(): EloquentCollection
    {
        return StudentProfile::with('user')
            ->where('current_class_id', $this->classId)
            ->active()
            ->orderBy('roll_no')
            ->get();
    }

    public function resolveClass(): ?Classes
    {
        return Classes::find($this->classId);
    }

    public function getNextClass(): ?Classes
    {
        $current = $this->resolveClass();

        if (! $current) {
            return null;
        }

        return Classes::active()
            ->where('order', '>', $current->order)
            ->orderBy('order')
            ->first();
    }

    public function getTargetClassOptions(): Collection
    {
        return Classes::active()->orderBy('order')->pluck('name', 'id');
    }

    public function getSectionOptions(?int $classId): Collection
    {
        return $classId ? Section::dropdownOptionsByClass($classId) : collect();
    }

    public function getGroupOptions(?int $classId): Collection
    {
        if (! $classId) {
            return collect();
        }

        $class = Classes::find($classId);

        if (! $class?->has_group) {
            return collect();
        }

        return $class->groups()->where('groups.is_active', true)->pluck('groups.name', 'groups.id');
    }

    public function promote(): void
    {
        foreach ($this->promotions as $row) {
            $status = PromotionStatus::from($row['status']);

            if (! in_array($status, [PromotionStatus::Promoted, PromotionStatus::Repeated], true)) {
                continue;
            }

            if (blank($row['class_id']) || blank($row['roll_no'])) {
                Notification::make()
                    ->danger()
                    ->title('Promoted/Repeated সব student-এর জন্য Target Class ও New Roll পূরণ করা আবশ্যক')
                    ->send();

                return;
            }
        }

        app(PromoteStudentsAction::class)->handle($this->promotions, Auth::id());

        Notification::make()
            ->success()
            ->title('Students promoted successfully')
            ->send();

        $this->redirect(StudentProfileResource::getUrl('students-by-class', ['classId' => $this->classId]));
    }

    public function promoteAction(): Action
    {
        return Action::make('promote')
            ->label('Promote Students')
            ->icon('heroicon-o-check')
            ->color('success')
            ->requiresConfirmation()
            ->modalHeading('Students Promote করবেন?')
            ->modalDescription(fn (): string => 'আপনি কি নিশ্চিত এই '.$this->getStudents()->count().' জন student-কে promote করতে চান?')
            ->modalSubmitActionLabel('হ্যাঁ, Promote করো')
            ->action(fn () => $this->promote());
    }
}
