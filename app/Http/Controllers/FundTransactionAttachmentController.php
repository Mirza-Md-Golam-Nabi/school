<?php

namespace App\Http\Controllers;

use App\Models\FundTransaction;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class FundTransactionAttachmentController extends Controller
{
    /**
     * Stream a fund transaction's receipt/bill attachment. Financial documents,
     * so restricted to admin/super-admin/staff regardless of who else is
     * authenticated (unlike the admit-card viewer, which any authenticated
     * user can reach).
     */
    public function __invoke(FundTransaction $fundTransaction): Response|StreamedResponse
    {
        abort_unless(auth()->check(), 401);

        $user = auth()->user();

        abort_unless($user->isAdmin() || $user->isSuperAdmin() || $user->isStaff(), 403);

        abort_unless($fundTransaction->attachment_path, 404);

        $disk = Storage::disk('local');

        abort_unless($disk->exists($fundTransaction->attachment_path), 404);

        return $disk->response($fundTransaction->attachment_path);
    }
}
