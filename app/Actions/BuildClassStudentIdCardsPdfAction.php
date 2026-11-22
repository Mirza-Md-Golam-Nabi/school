<?php

namespace App\Actions;

use App\Enums\StudentIdCardTemplate;
use App\Models\Classes;
use App\Models\StudentProfile;
use App\Support\Concerns\BuildsMpdfDocuments;
use App\Support\Concerns\LaysOutIdCardSheets;
use App\Support\IdCardPhotoRenderer;
use App\Support\StudentIdCardLayout;

class BuildClassStudentIdCardsPdfAction
{
    use BuildsMpdfDocuments;
    use LaysOutIdCardSheets;

    public function __construct(private IdCardPhotoRenderer $photoRenderer) {}

    /**
     * Build an ID card PDF for every active student in this class. Each
     * student's front and back sit side by side — so the pair can be cut out
     * as one piece and folded down the middle — four students per landscape A4 page.
     * The cards use the design the school picked unless one is passed in.
     */
    public function handle(Classes $class, ?StudentIdCardTemplate $template = null): string
    {
        $template ??= StudentIdCardLayout::template();

        $students = StudentProfile::with(['user', 'class', 'group', 'section', 'addresses'])
            ->active()
            ->where('current_class_id', $class->id)
            ->orderBy('roll_no')
            ->get();

        abort_if($students->isEmpty(), 404, 'এই ক্লাসে এখনো কোনো active student নেই।');

        $mpdf = $this->makeMpdf('A4', 'L');

        $sharedViewData = [
            'school' => $this->schoolBranding(StudentIdCardLayout::logoColour()),
            'theme' => $template->theme(),
            'frontBackground' => $template->backgroundDataUri('front', self::CARD_WIDTH, self::CARD_HEIGHT),
            'backBackground' => $template->backgroundDataUri('back', self::CARD_WIDTH, self::CARD_HEIGHT),
            'cardWidth' => self::CARD_WIDTH,
            'cardHeight' => self::CARD_HEIGHT,
            'frontFields' => StudentIdCardLayout::frontFields(),
            'backFields' => StudentIdCardLayout::backFields(),
            'validity' => StudentIdCardLayout::validity(),
            'issueDate' => StudentIdCardLayout::issueDate(),
        ];

        // Every card embeds a base64 portrait, so mpdf is fed one page of cards
        // per WriteHTML() call to keep each call's HTML small.
        foreach ($students->chunk(self::CARDS_PER_PAGE) as $pageIndex => $pageStudents) {
            if ($pageIndex > 0) {
                $mpdf->AddPage();
            }

            $cards = $pageStudents->values()->map(fn (StudentProfile $student, int $slot): array => [
                'student' => $student,
                'photo' => $this->photoRenderer->render($student->photo, ringColour: $template->photoRing()),
                ...$this->slotOrigin($slot),
            ]);

            $mpdf->WriteHTML(view('documents.student-id-card-sheet', [...$sharedViewData, 'cards' => $cards])->render());
        }

        return $this->outputMpdfString($mpdf);
    }
}
