<?php

namespace App\Http\Controllers;

use App\Actions\BuildStaffIdCardsPdfAction;
use App\Models\StaffProfile;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class StaffIdCardsPdfController extends Controller
{
    /**
     * Stream every active staff member's ID card inline so it opens in the browser.
     */
    public function view(Request $request, BuildStaffIdCardsPdfAction $action): Response
    {
        $this->ensureSchoolStaff($request->user());

        return $this->pdfResponse($action->handle(), 'staff-id-cards.pdf', 'inline');
    }

    /**
     * Download every active staff member's ID card.
     */
    public function download(Request $request, BuildStaffIdCardsPdfAction $action): Response
    {
        $this->ensureSchoolStaff($request->user());

        return $this->pdfResponse($action->handle(), 'staff-id-cards.pdf', 'attachment');
    }

    /**
     * Stream one staff member's ID card inline.
     */
    public function show(Request $request, StaffProfile $staff, BuildStaffIdCardsPdfAction $action): Response
    {
        $this->ensureSchoolStaff($request->user());

        return $this->pdfResponse($action->handle($staff), "staff-id-card-{$staff->id}.pdf", 'inline');
    }

    /**
     * Staff cards carry phone numbers and addresses, so only people who run
     * the school from the admin panel may print them.
     */
    private function ensureSchoolStaff(User $user): void
    {
        abort_unless($user->canAccessPanel(Filament::getPanel('admin')), 403);
    }

    private function pdfResponse(string $pdf, string $filename, string $disposition): Response
    {
        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => "{$disposition}; filename=\"{$filename}\"",
        ]);
    }
}
