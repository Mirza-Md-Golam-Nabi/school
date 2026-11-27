<?php

namespace App\Actions;

use App\Enums\AdmitCardDesign;
use App\Models\AdmitCard;
use App\Models\Classes;
use App\Models\Exam;
use App\Support\AdmitCardBranding;
use App\Support\Concerns\BuildsMpdfDocuments;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Mpdf\Mpdf;

class BuildCombinedClassAdmitCardsPdfAction
{
    use BuildsMpdfDocuments;

    /**
     * Two admit cards per row, three rows per A4 page.
     */
    private const CARDS_PER_PAGE = 8;

    /**
     * Routine cards are wide and tall: two per A4 page, one above the other.
     */
    private const ROUTINE_CARDS_PER_PAGE = 2;

    /**
     * Combine every already-generated admit card for this class + exam into one
     * PDF sheet — simple cards two side by side per row, several rows per A4
     * page; cards with the routine two per page, after the simple ones.
     */
    public function handle(Classes $class, Exam $exam): string
    {
        $admitCards = $this->loadAdmitCards($this->admitCardsQuery($class->id, $exam)->where('is_generated', true));

        abort_if($admitCards->isEmpty(), 404, 'এই ক্লাস ও exam-এর জন্য এখনো কোনো admit card generate করা হয়নি।');

        return $this->render($admitCards);
    }

    /**
     * Build the ready-made bundle PDF of this exam's class. Unlike handle(), it
     * includes every admit card record of the exam — not only those whose own
     * PDF job has already finished — because the bundle job runs alongside them.
     *
     * @return array{pdf: ?string, card_count: int}
     */
    public function buildBundle(Exam $exam): array
    {
        $admitCards = $this->loadAdmitCards($this->admitCardsQuery($exam->class_id, $exam));

        if ($admitCards->isEmpty()) {
            return ['pdf' => null, 'card_count' => 0];
        }

        return ['pdf' => $this->render($admitCards), 'card_count' => $admitCards->count()];
    }

    /**
     * Every admit card record of this exam whose student is in the class —
     * the same set a stored bundle covers, so its count tells whether the
     * bundle is still up to date.
     *
     * @return Builder<AdmitCard>
     */
    public function admitCardsQuery(int $classId, Exam $exam): Builder
    {
        return AdmitCard::where('exam_id', $exam->id)
            ->whereHas('student', fn (Builder $query) => $query->where('current_class_id', $classId));
    }

    /**
     * @param  Builder<AdmitCard>  $query
     * @return Collection<int, AdmitCard>
     */
    private function loadAdmitCards(Builder $query): Collection
    {
        return $query
            ->with(['student.user', 'student.class', 'student.section', 'student.group', 'student.optionalSubjects', 'exam.examType', 'exam.schedules' => fn ($query) => $query->inExamDateOrder(), 'exam.schedules.subject'])
            ->orderedByStudentRoll()
            ->get();
    }

    /**
     * @param  Collection<int, AdmitCard>  $admitCards
     */
    private function render(Collection $admitCards): string
    {
        $mpdf = $this->makeMpdf();

        [$routineCards, $simpleCards] = $admitCards->partition(fn (AdmitCard $admitCard): bool => $admitCard->design === AdmitCardDesign::WithRoutine);

        $this->writeSimpleCards($mpdf, $simpleCards->values());
        $this->writeRoutineCards($mpdf, $routineCards->values(), startOnNewPage: $simpleCards->isNotEmpty());

        return $this->outputMpdfString($mpdf);
    }

    /**
     * @param  Collection<int, AdmitCard>  $admitCards
     */
    private function writeSimpleCards(Mpdf $mpdf, Collection $admitCards): void
    {
        // Feed mpdf one row (2 cards) per WriteHTML() call so each call's HTML
        // stays small; images are local file paths (PdfImage), not base64.
        $rowsPerPage = (int) ceil(self::CARDS_PER_PAGE / 2);

        foreach ($admitCards->chunk(2)->values() as $rowIndex => $row) {
            if ($rowIndex > 0 && $rowIndex % $rowsPerPage === 0) {
                $mpdf->AddPage();
            }

            $mpdf->WriteHTML(view('documents.admit-card-sheet', ['admitCards' => $row])->render());
        }
    }

    /**
     * @param  Collection<int, AdmitCard>  $admitCards
     */
    private function writeRoutineCards(Mpdf $mpdf, Collection $admitCards, bool $startOnNewPage): void
    {
        $branding = AdmitCardBranding::fromSettings();

        foreach ($admitCards->chunk(self::ROUTINE_CARDS_PER_PAGE)->values() as $pageIndex => $page) {
            if ($pageIndex > 0 || $startOnNewPage) {
                $mpdf->AddPage();
            }

            $mpdf->WriteHTML(view('documents.admit-card-routine-sheet', ['admitCards' => $page, 'branding' => $branding])->render());
        }
    }
}
