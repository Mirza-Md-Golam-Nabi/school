<?php

namespace App\Actions;

use App\Models\AdmitCard;
use App\Models\Classes;
use App\Models\Exam;
use App\Support\Concerns\BuildsMpdfDocuments;

class BuildCombinedClassAdmitCardsPdfAction
{
    use BuildsMpdfDocuments;

    /**
     * Two admit cards per row, three rows per A4 page.
     */
    private const CARDS_PER_PAGE = 8;

    /**
     * Combine every already-generated admit card for this class + exam into one
     * PDF sheet — two admit cards side by side per row, several rows per A4 page.
     */
    public function handle(Classes $class, Exam $exam): string
    {
        $admitCards = AdmitCard::where('exam_id', $exam->id)
            ->where('is_generated', true)
            ->whereHas('student', fn ($query) => $query->where('current_class_id', $class->id))
            ->with(['student.user', 'student.class', 'student.section', 'student.group', 'exam.examType'])
            ->get()
            ->sortBy(fn (AdmitCard $admitCard) => $admitCard->student?->roll_no)
            ->values();

        abort_if($admitCards->isEmpty(), 404, 'এই ক্লাস ও exam-এর জন্য এখনো কোনো admit card generate করা হয়নি।');

        $mpdf = $this->makeMpdf();

        // Each card embeds base64 photo/logo/seal/signature images, so a single
        // WriteHTML() call covering a whole page (several cards at once) can push
        // past PHP's pcre.backtrack_limit even after raising it — feed mpdf one
        // row (2 cards) per call instead, keeping every call's HTML small.
        $rowsPerPage = (int) ceil(self::CARDS_PER_PAGE / 2);

        foreach ($admitCards->chunk(2)->values() as $rowIndex => $row) {
            if ($rowIndex > 0 && $rowIndex % $rowsPerPage === 0) {
                $mpdf->AddPage();
            }

            $html = view('documents.admit-card-sheet', ['admitCards' => $row])->render();

            $mpdf->WriteHTML($html);
        }

        return $this->outputMpdfString($mpdf);
    }
}
