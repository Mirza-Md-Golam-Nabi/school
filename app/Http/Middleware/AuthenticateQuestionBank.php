<?php

namespace App\Http\Middleware;

use App\Support\HmacSignature;
use App\Support\QuestionBankConnection;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateQuestionBank
{
    /**
     * Question Bank থেকে আসা অনুরোধ তখনই ঢুকতে দেয় যখন এই স্কুল Question Bank-এর সাথে
     * যুক্ত, X-School-Code এই স্কুলের কোড, আর X-Signature এই স্কুলের secret দিয়ে
     * "{X-Timestamp}.{body}"-এর HMAC-SHA256 — সাম্প্রতিক সময়ের।
     */
    public function handle(Request $request, Closure $next): Response
    {
        $isAuthentic = QuestionBankConnection::isConnected()
            && hash_equals((string) QuestionBankConnection::code(), (string) $request->header('X-School-Code'))
            && HmacSignature::verify(
                (string) QuestionBankConnection::secret(),
                $request->header('X-Timestamp'),
                $request->getContent(),
                $request->header('X-Signature'),
                QuestionBankConnection::SIGNATURE_TOLERANCE_SECONDS,
            );

        if (! $isAuthentic) {
            return response()->json(['message' => 'Unauthenticated Question Bank.'], Response::HTTP_UNAUTHORIZED);
        }

        return $next($request);
    }
}
