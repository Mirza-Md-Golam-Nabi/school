<?php

namespace App\Http\Controllers;

use App\Actions\BuildTeacherIdCardsPdfAction;
use App\Models\TeacherProfile;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class TeacherIdCardsPdfController extends Controller
{
    /**
     * Stream every active teacher's ID card inline so it opens in the browser.
     */
    public function view(Request $request, BuildTeacherIdCardsPdfAction $action): Response
    {
        $this->ensureSchoolStaff($request->user());

        return $this->pdfResponse($action->handle(), 'teacher-id-cards.pdf', 'inline');
    }

    /**
     * Download every active teacher's ID card.
     */
    public function download(Request $request, BuildTeacherIdCardsPdfAction $action): Response
    {
        $this->ensureSchoolStaff($request->user());

        return $this->pdfResponse($action->handle(), 'teacher-id-cards.pdf', 'attachment');
    }

    /**
     * Stream one teacher's ID card inline — for the school, or for the teacher
     * checking their own card.
     */
    public function show(Request $request, TeacherProfile $teacher, BuildTeacherIdCardsPdfAction $action): Response
    {
        /** @var User $user */
        $user = $request->user();

        if ($teacher->user_id !== $user->id) {
            $this->ensureSchoolStaff($user);
            abort_unless($user->can('view_teacher_profiles'), 403);
        }

        return $this->pdfResponse($action->handle($teacher), "teacher-id-card-{$teacher->id}.pdf", 'inline');
    }

    /**
     * Teacher cards carry phone numbers and addresses, so only people who run
     * the school from the admin panel may print other teachers' cards.
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
