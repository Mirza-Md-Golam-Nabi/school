<?php

namespace App\Actions;

use App\Enums\MarksheetBundleType;
use App\Models\Classes;
use App\Models\Exam;
use App\Models\Marksheet;
use App\Models\StudentMeritRanking;
use App\Support\Concerns\BuildsMpdfDocuments;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\App;

class BuildCombinedClassMarksheetPdfAction
{
    use BuildsMpdfDocuments;

    public const RESULT_PASSED = 'passed';

    public const RESULT_FAILED = 'failed';

    public function __construct(private readonly CalculateExamRankings $calculateExamRankings) {}

    /**
     * Combine every already-generated marksheet for this class + exam into one
     * PDF, one student per page, reusing the same per-student layout and data
     * as the individual marksheet PDFs.
     *
     * $subjectLanguage controls only this combined PDF's document language —
     * independent of whatever language each marksheet was originally generated in.
     *
     * $resultFilter ('passed' | 'failed' | null) keeps only students who passed
     * in every subject, or only students who failed — null keeps everyone.
     * $minFailedSubjects / $maxFailedSubjects further keep only failed students
     * whose failed-subject count falls in that (inclusive) range.
     */
    public function handle(
        Classes $class,
        Exam $exam,
        string $subjectLanguage = 'en',
        ?string $resultFilter = null,
        ?int $minFailedSubjects = null,
        ?int $maxFailedSubjects = null,
    ): string {
        $marksheets = $this->loadMarksheets(
            $this->generatedMarksheetsQuery($class, $exam, $resultFilter, $minFailedSubjects, $maxFailedSubjects)
        );

        abort_if($marksheets->isEmpty(), 404, 'এই ক্লাস ও exam-এর জন্য কোনো marksheet পাওয়া যায়নি।');

        ['pdf' => $pdf] = $this->render($marksheets, $exam, $subjectLanguage);

        abort_if($pdf === null, 404, 'এই ক্লাস ও exam-এর জন্য কোনো marksheet পাওয়া যায়নি।');

        return $pdf;
    }

    /**
     * Build a ready-made bundle PDF for this exam. Unlike handle(), it includes
     * every marksheet record of the exam — not only those whose own PDF job has
     * already finished — because the bundle jobs run alongside those jobs.
     *
     * @return array{pdf: ?string, student_count: int}
     */
    public function buildBundle(Exam $exam, MarksheetBundleType $type, string $subjectLanguage): array
    {
        $marksheets = $this->loadMarksheets(
            $this->generatedMarksheetsQuery($exam->class, $exam, $type->resultFilter(), requireGeneratedFile: false)
        );

        return $this->render($marksheets, $exam, $subjectLanguage);
    }

    /**
     * Generated marksheets of this class + exam, optionally narrowed to students
     * who passed overall or failed overall (same rule as the exam result report).
     * Students absent in every subject did not sit the exam, so they belong to
     * neither the passed nor the failed list. Giving a failed-subject range
     * implies the failed list.
     *
     * @return Builder<Marksheet>
     */
    public function generatedMarksheetsQuery(
        Classes $class,
        Exam $exam,
        ?string $resultFilter = null,
        ?int $minFailedSubjects = null,
        ?int $maxFailedSubjects = null,
        bool $requireGeneratedFile = true,
    ): Builder {
        $query = Marksheet::where('exam_id', $exam->id)
            ->when($requireGeneratedFile, fn (Builder $query) => $query->where('is_generated', true))
            ->whereHas('student', fn ($query) => $query->where('current_class_id', $class->id));

        if ($minFailedSubjects !== null || $maxFailedSubjects !== null) {
            $resultFilter = self::RESULT_FAILED;
        }

        if (! in_array($resultFilter, [self::RESULT_PASSED, self::RESULT_FAILED], true)) {
            return $query;
        }

        $studentIds = collect($this->calculateExamRankings->summarizeStudents($exam))
            ->where('is_fully_absent', false)
            ->where('is_overall_fail', $resultFilter === self::RESULT_FAILED)
            ->when($minFailedSubjects !== null, fn (Collection $students) => $students->where('failed_subject_count', '>=', $minFailedSubjects))
            ->when($maxFailedSubjects !== null, fn (Collection $students) => $students->where('failed_subject_count', '<=', $maxFailedSubjects))
            ->pluck('student_id')
            ->all();

        return $query->whereIn('student_id', $studentIds);
    }

    /**
     * @param  Builder<Marksheet>  $query
     * @return Collection<int, Marksheet>
     */
    private function loadMarksheets(Builder $query): Collection
    {
        return $query
            ->with(['student.user', 'student.class', 'student.section', 'exam.examType', 'exam.class.classTeacher'])
            ->orderedByStudentRoll()
            ->get();
    }

    /**
     * Render the marksheets one student per page. Students without a ranking
     * are skipped; pdf is null when no page was written.
     *
     * @param  Collection<int, Marksheet>  $marksheets
     * @return array{pdf: ?string, student_count: int}
     */
    private function render(Collection $marksheets, Exam $exam, string $subjectLanguage): array
    {
        if ($marksheets->isEmpty()) {
            return ['pdf' => null, 'student_count' => 0];
        }

        $mpdf = $this->makeMpdf();

        $currentLocale = App::getLocale();
        App::setLocale(array_key_exists($subjectLanguage, config('app.available_locales')) ? $subjectLanguage : 'en');

        // Loaded once for the whole class — the loop below used to query the
        // ranking, the exam-wide results and the section check per student.
        $rankings = StudentMeritRanking::where('exam_id', $exam->id)
            ->whereIn('student_id', $marksheets->pluck('student_id'))
            ->get()
            ->keyBy('student_id');

        $hasSections = $marksheets->first()->exam?->class?->sections()->exists() ?? false;
        $buildStudentMarksDetail = app(BuildStudentMarksDetail::class);
        $pageCount = 0;

        try {
            foreach ($marksheets as $marksheet) {
                $ranking = $rankings->get($marksheet->student_id);

                if (! $ranking) {
                    continue;
                }

                $ranking->setRelation('student', $marksheet->student);
                $ranking->setRelation('exam', $marksheet->exam);

                ['rows' => $rows, 'summary' => $summary] = $buildStudentMarksDetail->handle($ranking);

                $html = view('documents.marksheet', [
                    'marksheet' => $marksheet,
                    'rows' => $rows,
                    'summary' => $summary,
                    'hasSections' => $hasSections,
                ])->render();

                if ($pageCount > 0) {
                    $mpdf->AddPage();
                }

                $mpdf->WriteHTML($html);
                $pageCount++;
            }
        } finally {
            App::setLocale($currentLocale);
        }

        if ($pageCount === 0) {
            return ['pdf' => null, 'student_count' => 0];
        }

        return ['pdf' => $this->outputMpdfString($mpdf), 'student_count' => $pageCount];
    }
}
