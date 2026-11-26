<?php

namespace App\Actions;

use App\Enums\StudentIdCardTemplate;
use App\Models\TeacherProfile;
use App\Support\Concerns\BuildsMpdfDocuments;
use App\Support\Concerns\LaysOutIdCardSheets;
use App\Support\IdCardPhotoRenderer;
use App\Support\TeacherIdCardLayout;
use Illuminate\Support\Collection;

class BuildTeacherIdCardsPdfAction
{
    use BuildsMpdfDocuments;
    use LaysOutIdCardSheets;

    public function __construct(private IdCardPhotoRenderer $photoRenderer) {}

    /**
     * Build an ID card PDF for one teacher, or for every active teacher when
     * none is given. Unlike the student card, the portrait is the teacher's own
     * profile photo, cropped where the teacher placed it — so a new profile
     * photo shows up the next time the card is printed.
     */
    public function handle(?TeacherProfile $teacher = null, ?StudentIdCardTemplate $template = null): string
    {
        $template ??= TeacherIdCardLayout::template();

        $teachers = $this->teachers($teacher);

        abort_if($teachers->isEmpty(), 404, 'এখনো কোনো active teacher নেই।');

        $mpdf = $this->makeMpdf('A4', 'L');

        $sharedViewData = [
            'school' => $this->schoolBranding(TeacherIdCardLayout::logoColour()),
            'theme' => $template->theme(),
            'frontBackground' => $template->backgroundDataUri('front', self::CARD_WIDTH, self::CARD_HEIGHT),
            'backBackground' => $template->backgroundDataUri('back', self::CARD_WIDTH, self::CARD_HEIGHT),
            'cardWidth' => self::CARD_WIDTH,
            'cardHeight' => self::CARD_HEIGHT,
            'frontFields' => TeacherIdCardLayout::frontFields(),
            'backFields' => TeacherIdCardLayout::backFields(),
            'issueDate' => TeacherIdCardLayout::issueDate(),
            'showQrCode' => TeacherIdCardLayout::showsQrCode(),
            'role' => 'TEACHER',
        ];

        // Every card embeds a base64 portrait, so mpdf is fed one page of cards
        // per WriteHTML() call to keep each call's HTML small.
        foreach ($teachers->chunk(self::CARDS_PER_PAGE) as $pageIndex => $pageTeachers) {
            if ($pageIndex > 0) {
                $mpdf->AddPage();
            }

            $cards = $pageTeachers->values()->map(fn (TeacherProfile $profile, int $slot): array => [
                'person' => $profile,
                'photo' => $this->photoRenderer->render(
                    $profile->user?->avatar,
                    ringColour: $template->photoRing(),
                    position: $profile->idCardPhotoPosition(),
                ),
                ...$this->slotOrigin($slot),
            ]);

            $mpdf->WriteHTML(view('documents.employee-id-card-sheet', [...$sharedViewData, 'cards' => $cards])->render());
        }

        return $this->outputMpdfString($mpdf);
    }

    /**
     * @return Collection<int, TeacherProfile>
     */
    private function teachers(?TeacherProfile $teacher): Collection
    {
        if ($teacher !== null) {
            return collect([$teacher->loadMissing(['user', 'addresses'])]);
        }

        return TeacherProfile::with(['user', 'addresses'])
            ->active()
            ->orderedByUserName()
            ->get();
    }
}
