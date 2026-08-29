<?php

namespace App\Actions;

use App\Models\Classes;
use App\Models\StudentProfile;
use App\Support\Concerns\BuildsMpdfDocuments;
use Illuminate\Support\Collection;

class BuildClassStudentListPdfAction
{
    use BuildsMpdfDocuments;

    /**
     * Build a PDF listing every student in this class for the given session —
     * name, roll, email, and section/group where the class actually uses them.
     */
    public function handle(Classes $class, int $sessionYear): string
    {
        $students = StudentProfile::with(['user', 'section', 'group'])
            ->where('current_class_id', $class->id)
            ->where('session_year', $sessionYear)
            ->orderBy('roll_no')
            ->get();

        abort_if($students->isEmpty(), 404, 'এই ক্লাসে এই সেশনে এখনো কোনো student নেই।');

        $mpdf = $this->makeMpdf();

        $html = view('documents.student-list', [
            'class' => $class,
            'sessionYear' => $sessionYear,
            'students' => $students,
            'hasSection' => $this->anyHave($students, 'section'),
            'hasGroup' => $this->anyHave($students, 'group'),
        ])->render();

        $mpdf->WriteHTML($html);

        return $this->outputMpdfString($mpdf);
    }

    /**
     * @param  Collection<int, StudentProfile>  $students
     */
    private function anyHave(Collection $students, string $relation): bool
    {
        return $students->contains(fn (StudentProfile $student) => filled($student->{$relation}));
    }
}
