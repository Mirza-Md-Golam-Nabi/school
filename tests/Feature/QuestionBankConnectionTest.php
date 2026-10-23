<?php

use App\Enums\UserType;
use App\Filament\Pages\QuestionBankSettings;
use App\Models\SchoolSetting;
use App\Models\User;
use App\Support\HmacSignature;
use App\Support\QuestionBankConnection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    config(['app.url' => 'https://school.test']);
    test()->actingAs(grantSuperAdmin(User::factory()->create(['user_type' => UserType::Admin, 'is_active' => true])));

    $this->details = ['url' => 'https://qb.test/', 'code' => 'QB-ABCDEFGHIJ', 'secret' => 'the-school-secret'];
});

it('saves the details and connects with a signed request', function () {
    Http::fake(['qb.test/api/school/connect' => Http::response(['school_name' => 'Sunrise High School', 'connected_at' => now()->toIso8601String()])]);

    Livewire::test(QuestionBankSettings::class)
        ->fillForm($this->details)
        ->call('saveAndConnect')
        ->assertNotified(__('Connected to the Question Bank as :school.', ['school' => 'Sunrise High School']));

    Http::assertSent(function (Request $request): bool {
        return $request->url() === 'https://qb.test/api/school/connect'
            && $request['installation_url'] === 'https://school.test'
            && $request->header('X-School-Code')[0] === 'QB-ABCDEFGHIJ'
            && HmacSignature::verify('the-school-secret', $request->header('X-Timestamp')[0], $request->body(), $request->header('X-Signature')[0], 300);
    });

    expect(QuestionBankConnection::isConnected())->toBeTrue()
        ->and(QuestionBankConnection::schoolName())->toBe('Sunrise High School')
        ->and(QuestionBankConnection::url())->toBe('https://qb.test')
        // Kept encrypted, never as typed.
        ->and(SchoolSetting::query()->where('key', 'question_bank_secret')->value('value'))->not->toBe('the-school-secret')
        ->and(QuestionBankConnection::secret())->toBe('the-school-secret');
});

it('says what went wrong when the question bank refuses', function (int $status, string $message) {
    Http::fake(['qb.test/*' => Http::response(['message' => 'no'], $status)]);

    Livewire::test(QuestionBankSettings::class)
        ->fillForm($this->details)
        ->call('saveAndConnect')
        ->assertNotified(__('Not connected'));

    expect(QuestionBankConnection::isConnected())->toBeFalse();
})->with([
    'wrong code or secret' => [401, 'The connection code or secret is wrong.'],
    'account not approved' => [403, 'The school account in the Question Bank is not approved yet, or is suspended.'],
]);

it('keeps the saved secret when the field is left empty, and needs connecting again after a change', function () {
    Http::fakeSequence('qb.test/*')
        ->push(['school_name' => 'Sunrise High School'])
        ->push(['message' => 'no'], 401);

    Livewire::test(QuestionBankSettings::class)->fillForm($this->details)->call('saveAndConnect');
    expect(QuestionBankConnection::isConnected())->toBeTrue();

    Livewire::test(QuestionBankSettings::class)
        ->fillForm([...$this->details, 'code' => 'QB-OTHERCODE1', 'secret' => null])
        ->call('saveAndConnect')
        ->assertHasNoFormErrors();

    expect(QuestionBankConnection::secret())->toBe('the-school-secret')
        ->and(QuestionBankConnection::code())->toBe('QB-OTHERCODE1')
        ->and(QuestionBankConnection::isConnected())->toBeFalse();
});

it('shows the panels a link to the question bank once connected', function () {
    $link = QuestionBankConnection::navigationItem('teacher');
    expect($link->isVisible())->toBeFalse();

    QuestionBankConnection::save('https://qb.test', 'QB-ABCDEFGHIJ', 'the-school-secret');
    QuestionBankConnection::markConnected('Sunrise High School');

    expect($link->isVisible())->toBeTrue()
        ->and($link->getUrl())->toBe('https://qb.test/teacher')
        ->and(QuestionBankConnection::navigationItem('school')->getUrl())->toBe('https://qb.test/school');
});
