<?php

namespace App\Actions;

use App\Models\StudentProfile;

/**
 * Turns what a fee-counter scanner (or a person) typed into the student it
 * names: the student ID card's QR link (…/verify/id-card/{token}), the bare
 * token, or the 8-digit Student ID printed on the card. A reissued card's old
 * token and deleted students find nothing.
 */
class FindStudentByScannedCardAction
{
    public function handle(string $scanned): ?StudentProfile
    {
        $scanned = trim($scanned);

        if ($scanned === '') {
            return null;
        }

        if (preg_match('#/verify/id-card/([A-Za-z0-9]{1,32})#', $scanned, $matches)) {
            return StudentProfile::query()->where('id_card_token', $matches[1])->first();
        }

        if (preg_match('/^\d{1,20}$/', $scanned)) {
            return StudentProfile::query()->where('student_id_no', $scanned)->first();
        }

        if (preg_match('/^[A-Za-z0-9]{16}$/', $scanned)) {
            return StudentProfile::query()->where('id_card_token', $scanned)->first();
        }

        return null;
    }
}
