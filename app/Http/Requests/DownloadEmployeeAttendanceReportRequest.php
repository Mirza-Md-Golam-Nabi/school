<?php

namespace App\Http\Requests;

use App\Models\StaffProfile;
use App\Models\TeacherProfile;
use Filament\Facades\Filament;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Collection;

class DownloadEmployeeAttendanceReportRequest extends FormRequest
{
    /**
     * How a selected person is written in the URL: "teacher-12" or "staff-4".
     */
    public const PERSON_KEY_PATTERN = '/^(teacher|staff)-\d+$/';

    /**
     * Teacher and staff attendance is only for people who can use the admin panel,
     * where the report page lives — not for any logged-in student or teacher.
     */
    public function authorize(): bool
    {
        return (bool) $this->user()?->canAccessPanel(Filament::getPanel('admin'));
    }

    /**
     * The year and month are part of the URL; validate them with the rest.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'year' => $this->route('year'),
            'month' => $this->route('month'),
        ]);
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'year' => ['required', 'integer', 'between:2000,2100'],
            'month' => ['required', 'integer', 'between:1,12'],
            'people' => ['required', 'array', 'min:1', 'max:200'],
            'people.*' => ['string', 'regex:'.self::PERSON_KEY_PATTERN],
        ];
    }

    /**
     * The selected teachers first, then staff, each sorted by name. Unknown IDs are skipped.
     *
     * @return Collection<int, TeacherProfile|StaffProfile>
     */
    public function people(): Collection
    {
        $idsOf = fn (string $kind): array => collect($this->validated('people'))
            ->filter(fn (string $key): bool => str_starts_with($key, "{$kind}-"))
            ->map(fn (string $key): int => (int) substr($key, strlen($kind) + 1))
            ->all();

        $teachers = TeacherProfile::with('user')->whereKey($idsOf('teacher'))->get()->sortBy('user.name');
        $staff = StaffProfile::with('user')->whereKey($idsOf('staff'))->get()->sortBy('user.name');

        return collect([...$teachers, ...$staff]);
    }
}
