<?php

namespace App\Actions;

use App\Models\Marksheet;
use App\Models\StudentMeritRanking;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\App;

class BuildMarksheetVerificationAction
{
    public function __construct(private readonly BuildStudentMarksDetail $buildStudentMarksDetail) {}

    /**
     * The result a marksheet's verification page shows: always the current
     * one in the database (not what was printed), built by the same code as
     * the marksheet PDF so the numbers can be compared line by line. Subject
     * names come out in Bengali, the page's language.
     *
     * Null when the exam's result isn't published or the student has no
     * ranking (results not calculated) — nothing to compare against then.
     *
     * @return array{rows: Collection<int, array<string, mixed>>, summary: array<string, mixed>, updated_at: ?Carbon}|null
     */
    public function handle(Marksheet $marksheet): ?array
    {
        if (! $marksheet->exam?->is_published) {
            return null;
        }

        $ranking = StudentMeritRanking::query()
            ->where('exam_id', $marksheet->exam_id)
            ->where('student_id', $marksheet->student_id)
            ->first();

        if (! $ranking) {
            return null;
        }

        $ranking->setRelation('student', $marksheet->student);
        $ranking->setRelation('exam', $marksheet->exam);

        $currentLocale = App::getLocale();
        App::setLocale('bn');

        try {
            ['rows' => $rows, 'summary' => $summary] = $this->buildStudentMarksDetail->handle($ranking);
        } finally {
            App::setLocale($currentLocale);
        }

        return [
            'rows' => $rows,
            'summary' => $summary,
            'updated_at' => $ranking->updated_at,
        ];
    }
}
