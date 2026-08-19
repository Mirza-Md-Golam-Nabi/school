<?php

namespace App\Actions\Attendance;

use App\Enums\AttendanceSource;
use App\Enums\AttendanceStatus;
use App\Filament\Pages\AttendanceModificationDetails;
use App\Models\Attendance;
use App\Models\AttendanceStatusChange;
use App\Models\Classes;
use App\Models\StudentProfile;
use App\Models\TeacherProfile;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class SaveClassAttendanceAction
{
    /**
     * @param  Collection<int, StudentProfile>  $students
     * @param  array<string>  $presentIds
     */
    public function handle(
        int $classId,
        ?Classes $class,
        string $date,
        Collection $students,
        array $presentIds,
        int $markedBy,
        string $markedByName,
    ): int {
        $isBackdated = $date !== now()->toDateString();
        $batchId = (string) Str::uuid();
        $changes = collect();

        foreach ($students as $student) {
            $isPresent = in_array((string) $student->id, $presentIds);
            $newStatus = $isPresent ? AttendanceStatus::Present : AttendanceStatus::Absent;

            $keys = [
                'attendable_type' => StudentProfile::class,
                'attendable_id' => $student->id,
                'date' => $date,
                'class_id' => $classId,
                'subject_id' => null,
            ];

            $existing = Attendance::where($keys)->first();
            $oldStatus = $existing?->status;
            $statusChanged = $existing && $oldStatus !== $newStatus;

            $attendance = $existing ?? new Attendance($keys);

            $attendance->fill([
                'status' => $newStatus,
                'source' => AttendanceSource::Manual,
                'marked_by' => $markedBy,
                // Only stamp a fresh entry time when presence is newly recorded or
                // just flipped to present — resaving an unchanged "present" student
                // must not touch their row, or every student would show as
                // "updated" in the activity log whenever one student is corrected.
                'entry_time' => $isPresent
                    ? ((! $existing || $statusChanged) ? now()->format('H:i:s') : $attendance->entry_time)
                    : null,
            ]);

            if (! $existing || $attendance->isDirty()) {
                $attendance->save();
            }

            // Any new entry or real status change on a non-today date must be
            // flagged for admin review — resaving an unchanged status is a no-op.
            if ($isBackdated && (! $existing || $statusChanged)) {
                $changes->push(AttendanceStatusChange::create([
                    'attendance_id' => $attendance->id,
                    'class_id' => $classId,
                    'student_profile_id' => $student->id,
                    'date' => $date,
                    'old_status' => $oldStatus,
                    'new_status' => $newStatus,
                    'changed_by' => $markedBy,
                    'batch_id' => $batchId,
                ]));
            }
        }

        if ($changes->isNotEmpty()) {
            $this->notifyAdminsOfChanges($class, $date, $markedByName, $changes, $batchId);
        }

        $this->notifyIfWrongTeacherMarkedAttendance($class, $date, $markedBy, $markedByName);

        return $students->count();
    }

    /**
     * A class can have one authorized "class teacher" for attendance. If a
     * different teacher marks it, admins and the assigned class teacher are
     * notified — this doesn't block the save, only flags it for visibility.
     */
    private function notifyIfWrongTeacherMarkedAttendance(?Classes $class, string $date, int $markedBy, string $markedByName): void
    {
        if (! $class || ! $class->class_teacher_id) {
            return;
        }

        $markingTeacher = TeacherProfile::where('user_id', $markedBy)->first();

        if (! $markingTeacher || $markingTeacher->id === $class->class_teacher_id) {
            return;
        }

        $assignedTeacherUser = $class->classTeacher?->user;

        $recipients = User::role(['admin', 'super-admin'])->get();

        if ($assignedTeacherUser) {
            $recipients->push($assignedTeacherUser);
        }

        Notification::make()
            ->warning()
            ->title('Attendance Marked by Another Teacher')
            ->body("{$markedByName} marked attendance for {$class->name} on {$date}, but this class is assigned to {$assignedTeacherUser?->name}.")
            ->sendToDatabase($recipients->unique('id'));
    }

    private function notifyAdminsOfChanges(?Classes $class, string $date, string $markedByName, Collection $changes, string $batchId): void
    {
        $count = $changes->count();

        $body = "{$markedByName} recorded/changed {$count} ".Str::plural('student', $count)."' attendance in {$class?->name} on {$date}.";

        Notification::make()
            ->warning()
            ->title('Attendance Flagged for Review')
            ->body($body)
            ->actions([
                // Explicit panel: this notification can be created from either the
                // admin or teacher panel, but the review page only exists in admin.
                Action::make('view')
                    ->label('View Details')
                    ->button()
                    ->url(AttendanceModificationDetails::getUrl(['batch' => $batchId], panel: 'admin'))
                    ->markAsRead(),
            ])
            ->sendToDatabase(
                User::role(['admin', 'super-admin'])->get()
            );
    }
}
