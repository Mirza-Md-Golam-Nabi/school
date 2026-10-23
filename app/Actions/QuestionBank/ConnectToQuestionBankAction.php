<?php

namespace App\Actions\QuestionBank;

use App\Support\QuestionBankConnection;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class ConnectToQuestionBankAction
{
    /**
     * Saved connection details দিয়ে Question Bank-এ "connect" অনুরোধ পাঠায়: secret
     * দিয়ে সই করা, সাথে এই installation-এর ঠিকানা, যাতে Question Bank পরে এখান
     * থেকে ক্লাস জানতে ও নম্বর পাঠাতে পারে। সফল হলে Question Bank-এ স্কুলের নাম ফেরত আসে।
     *
     * @return string The school's name in the Question Bank
     *
     * @throws RuntimeException with a message for the admin when the details are missing, wrong, or the Question Bank can't be reached
     */
    public function handle(): string
    {
        if (! QuestionBankConnection::isConfigured()) {
            throw new RuntimeException(__('Enter the Question Bank address, connection code and secret first.'));
        }

        $body = json_encode(['installation_url' => rtrim((string) config('app.url'), '/')]);

        try {
            $response = Http::withHeaders(QuestionBankConnection::signedHeaders($body))
                ->withBody($body, 'application/json')
                ->acceptJson()
                ->connectTimeout(10)
                ->timeout(20)
                ->post(QuestionBankConnection::url().'/api/school/connect');
        } catch (ConnectionException) {
            throw new RuntimeException(__('The Question Bank could not be reached. Check its address and the internet connection.'));
        }

        if ($response->unauthorized()) {
            throw new RuntimeException(__('The connection code or secret is wrong.'));
        }

        if ($response->forbidden()) {
            throw new RuntimeException(__('The school account in the Question Bank is not approved yet, or is suspended.'));
        }

        if ($response->failed()) {
            throw new RuntimeException(__('The Question Bank refused the connection (:status).', ['status' => $response->status()]));
        }

        $schoolName = (string) $response->json('school_name');
        QuestionBankConnection::markConnected($schoolName);

        return $schoolName;
    }
}
