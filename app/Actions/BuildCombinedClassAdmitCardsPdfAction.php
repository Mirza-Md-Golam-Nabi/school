<?php

namespace App\Actions;

use App\Enums\AdmitCardDesign;
use App\Models\AdmitCard;
use App\Models\Classes;
use App\Models\Exam;
use App\Support\AdmitCardBranding;
use App\Support\Concerns\BuildsMpdfDocuments;
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
        $admitCards = AdmitCard::where('exam_id', $exam->id)
            ->where('is_generated', true)
            ->whereHas('student', fn ($query) => $query->where('current_class_id', $class->id))
            ->with(['student.user', 'student.class', 'student.section', 'student.group', 'exam.examType', 'exam.schedules.subject'])
            ->get()
            ->sortBy(fn (AdmitCard $admitCard) => $admitCard->student?->roll_no)
            ->values();

        abort_if($admitCards->isEmpty(), 404, 'এই ক্লাস ও exam-এর জন্য এখনো কোনো admit card generate করা হয়নি।');

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
        // Each card embeds base64 photo/logo/seal/signature images, so a single
        // WriteHTML() call covering a whole page (several cards at once) can push
        // past PHP's pcre.backtrack_limit even after raising it — feed mpdf one
        // row (2 cards) per call instead, keeping every call's HTML small.
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
