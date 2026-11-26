<?php

namespace App\Actions;

use App\Enums\StudentIdCardTemplate;
use App\Models\StaffProfile;
use App\Support\Concerns\BuildsMpdfDocuments;
use App\Support\Concerns\LaysOutIdCardSheets;
use App\Support\IdCardPhotoRenderer;
use App\Support\StaffIdCardLayout;
use Illuminate\Support\Collection;

class BuildStaffIdCardsPdfAction
{
    use BuildsMpdfDocuments;
    use LaysOutIdCardSheets;

    public function __construct(private IdCardPhotoRenderer $photoRenderer) {}

    /**
     * Build an ID card PDF for one staff member, or for every active staff
     * member when none is given. The card shares the teacher card's layout;
     * the portrait is the staff member's profile photo, centred.
     */
    public function handle(?StaffProfile $staff = null, ?StudentIdCardTemplate $template = null): string
    {
        $template ??= StaffIdCardLayout::template();

        $staffMembers = $this->staffMembers($staff);

        abort_if($staffMembers->isEmpty(), 404, 'এখনো কোনো active staff নেই।');

        $mpdf = $this->makeMpdf('A4', 'L');

        $sharedViewData = [
            'school' => $this->schoolBranding(StaffIdCardLayout::logoColour()),
            'theme' => $template->theme(),
            'frontBackground' => $template->backgroundDataUri('front', self::CARD_WIDTH, self::CARD_HEIGHT),
            'backBackground' => $template->backgroundDataUri('back', self::CARD_WIDTH, self::CARD_HEIGHT),
            'cardWidth' => self::CARD_WIDTH,
            'cardHeight' => self::CARD_HEIGHT,
            'frontFields' => StaffIdCardLayout::frontFields(),
            'backFields' => StaffIdCardLayout::backFields(),
            'issueDate' => StaffIdCardLayout::issueDate(),
            'showQrCode' => StaffIdCardLayout::showsQrCode(),
            'role' => 'STAFF',
        ];

        // Every card embeds a base64 portrait, so mpdf is fed one page of cards
        // per WriteHTML() call to keep each call's HTML small.
        foreach ($staffMembers->chunk(self::CARDS_PER_PAGE) as $pageIndex => $pageStaff) {
            if ($pageIndex > 0) {
                $mpdf->AddPage();
            }

            $cards = $pageStaff->values()->map(fn (StaffProfile $profile, int $slot): array => [
                'person' => $profile,
                'photo' => $this->photoRenderer->render($profile->user?->avatar, ringColour: $template->photoRing()),
                ...$this->slotOrigin($slot),
            ]);

            $mpdf->WriteHTML(view('documents.employee-id-card-sheet', [...$sharedViewData, 'cards' => $cards])->render());
        }

        return $this->outputMpdfString($mpdf);
    }

    /**
     * @return Collection<int, StaffProfile>
     */
    private function staffMembers(?StaffProfile $staff): Collection
    {
        if ($staff !== null) {
            return collect([$staff->loadMissing(['user', 'addresses'])]);
        }

        return StaffProfile::with(['user', 'addresses'])
            ->active()
            ->orderedByUserName()
            ->get();
    }
}
