<?php

namespace App\Actions;

use App\Models\Classes;
use App\Models\SchoolSetting;
use App\Models\StudentProfile;
use App\Support\Concerns\BuildsMpdfDocuments;
use App\Support\IdCardPhotoRenderer;
use App\Support\StudentIdCardLayout;
use Illuminate\Support\Facades\Storage;

class BuildClassStudentIdCardsPdfAction
{
    use BuildsMpdfDocuments;

    /**
     * Standard CR80 ID card, portrait, in millimetres.
     */
    private const CARD_WIDTH = 54.0;

    private const CARD_HEIGHT = 85.6;

    /**
     * Gap between neighbouring students' cards, both across and down the sheet.
     */
    private const CARD_GAP = 12.0;

    private const COLUMNS_PER_PAGE = 2;

    private const ROWS_PER_PAGE = 2;

    private const PAGE_WIDTH = 297.0;

    private const PAGE_HEIGHT = 210.0;

    public function __construct(private IdCardPhotoRenderer $photoRenderer) {}

    /**
     * Build an ID card PDF for every active student in this class. Each
     * student's front and back sit side by side — so the pair can be cut out
     * as one piece and folded down the middle — four students per landscape A4 page.
     */
    public function handle(Classes $class): string
    {
        $students = StudentProfile::with(['user', 'class', 'group', 'section', 'addresses'])
            ->active()
            ->where('current_class_id', $class->id)
            ->orderBy('roll_no')
            ->get();

        abort_if($students->isEmpty(), 404, 'এই ক্লাসে এখনো কোনো active student নেই।');

        $mpdf = $this->makeMpdf('A4', 'L');

        $sharedViewData = [
            'school' => $this->schoolBranding(),
            'frontBackground' => $this->svgDataUri('documents.student-id-card.front-background'),
            'backBackground' => $this->svgDataUri('documents.student-id-card.back-background'),
            'cardWidth' => self::CARD_WIDTH,
            'cardHeight' => self::CARD_HEIGHT,
            'frontFields' => StudentIdCardLayout::frontFields(),
            'backFields' => StudentIdCardLayout::backFields(),
            'validity' => StudentIdCardLayout::validity(),
            'issueDate' => StudentIdCardLayout::issueDate(),
        ];

        // Every card embeds a base64 portrait, so mpdf is fed one page of cards
        // per WriteHTML() call to keep each call's HTML small.
        foreach ($students->chunk(self::COLUMNS_PER_PAGE * self::ROWS_PER_PAGE) as $pageIndex => $pageStudents) {
            if ($pageIndex > 0) {
                $mpdf->AddPage();
            }

            $cards = $pageStudents->values()->map(fn (StudentProfile $student, int $slot): array => [
                'student' => $student,
                'photo' => $this->photoRenderer->render($student->user?->avatar),
                ...$this->slotOrigin($slot),
            ]);

            $mpdf->WriteHTML(view('documents.student-id-card-sheet', [...$sharedViewData, 'cards' => $cards])->render());
        }

        return $this->outputMpdfString($mpdf);
    }

    /**
     * Top-left corner, in millimetres from the page edge, of the front card in
     * the given slot. The block of cards is centred on the page.
     *
     * @return array{x: float, y: float}
     */
    private function slotOrigin(int $slot): array
    {
        $pairWidth = self::CARD_WIDTH * 2;

        $blockWidth = $pairWidth * self::COLUMNS_PER_PAGE + self::CARD_GAP * (self::COLUMNS_PER_PAGE - 1);
        $blockHeight = self::CARD_HEIGHT * self::ROWS_PER_PAGE + self::CARD_GAP * (self::ROWS_PER_PAGE - 1);

        $column = $slot % self::COLUMNS_PER_PAGE;
        $row = intdiv($slot, self::COLUMNS_PER_PAGE);

        return [
            'x' => (self::PAGE_WIDTH - $blockWidth) / 2 + $column * ($pairWidth + self::CARD_GAP),
            'y' => (self::PAGE_HEIGHT - $blockHeight) / 2 + $row * (self::CARD_HEIGHT + self::CARD_GAP),
        ];
    }

    /**
     * School details shared by every card, resolved once per PDF.
     *
     * @return array{name: string, address: string, logo: ?string, signature: ?string}
     */
    private function schoolBranding(): array
    {
        return [
            'name' => (string) SchoolSetting::get('school_name', ''),
            'address' => (string) SchoolSetting::get('school_address', ''),
            'logo' => $this->imageDataUri(SchoolSetting::get('school_logo')),
            'signature' => $this->imageDataUri(SchoolSetting::get('principal_signature')),
        ];
    }

    private function imageDataUri(?string $path): ?string
    {
        if (blank($path)) {
            return null;
        }

        $storage = Storage::disk('public');

        if (! $storage->exists($path)) {
            return null;
        }

        $mime = $storage->mimeType($path) ?: 'image/png';

        return "data:{$mime};base64,".base64_encode((string) $storage->get($path));
    }

    private function svgDataUri(string $view): string
    {
        return 'data:image/svg+xml;base64,'.base64_encode(view($view, [
            'width' => self::CARD_WIDTH,
            'height' => self::CARD_HEIGHT,
        ])->render());
    }
}
