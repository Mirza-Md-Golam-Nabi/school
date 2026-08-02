<?php

namespace App\Actions\Attendance;

use App\Enums\AttendanceSource;
use App\Enums\AttendanceStatus;
use App\Filament\Pages\AttendanceModificationDetails;
use App\Models\Attendance;
use App\Models\AttendanceStatusChange;
use App\Models\Classes;
use App\Models\StudentProfile;
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

            $attendance = Attendance::updateOrCreate($keys, [
                'status' => $newStatus,
                'source' => AttendanceSource::Manual,
                'marked_by' => $markedBy,
                'entry_time' => $isPresent ? now()->format('H:i:s') : null,
            ]);

            $statusChanged = $existing && $existing->status !== $newStatus;

            // Any new entry or real status change on a non-today date must be
            // flagged for admin review — resaving an unchanged status is a no-op.
            if ($isBackdated && (! $existing || $statusChanged)) {
                $changes->push(AttendanceStatusChange::create([
                    'attendance_id' => $attendance->id,
                    'class_id' => $classId,
                    'student_profile_id' => $student->id,
                    'date' => $date,
                    'old_status' => $existing?->status,
                    'new_status' => $newStatus,
                    'changed_by' => $markedBy,
                    'batch_id' => $batchId,
                ]));
            }
        }

        if ($changes->isNotEmpty()) {
            $this->notifyAdminsOfChanges($class, $date, $markedByName, $changes, $batchId);
        }

        return $students->count();
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
