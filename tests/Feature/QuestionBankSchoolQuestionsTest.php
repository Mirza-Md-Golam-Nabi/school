<?php

use App\Enums\UserType;
use App\Filament\Student\Pages\SchoolQuestions;
use App\Models\User;
use App\Support\HmacSignature;
use App\Support\QuestionBankConnection;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    Filament::setCurrentPanel('student');
    test()->actingAs(User::factory()->create(['user_type' => UserType::Student, 'is_active' => true]));
});

it('is only there once the school is connected to the question bank', function () {
    expect(SchoolQuestions::canAccess())->toBeFalse()
        ->and(QuestionBankConnection::schoolQuestionsUrl('bn'))->toBeNull();

    QuestionBankConnection::save('https://qb.test', 'QB-ABCDEFGHIJ', 'the-school-secret');
    expect(SchoolQuestions::canAccess())->toBeFalse();

    QuestionBankConnection::markConnected('Sunrise High School');
    expect(SchoolQuestions::canAccess())->toBeTrue();
});

it('opens the school\'s questions through a short-lived link signed with the secret', function () {
    $this->freezeTime();
    QuestionBankConnection::save('https://qb.test', 'QB-ABCDEFGHIJ', 'the-school-secret');
    QuestionBankConnection::markConnected('Sunrise High School');

    $url = QuestionBankConnection::schoolQuestionsUrl('bn');
    parse_str((string) parse_url($url, PHP_URL_QUERY), $query);

    expect(strtok($url, '?'))->toBe('https://qb.test/school-questions/QB-ABCDEFGHIJ')
        ->and((int) $query['expires'])->toBe(now()->addSeconds(QuestionBankConnection::QUESTIONS_LINK_SECONDS)->timestamp)
        ->and($query['signature'])->toBe(HmacSignature::sign('the-school-secret', $query['expires'], 'link:QB-ABCDEFGHIJ'))
        ->and($query['lang'])->toBe('bn');

    Livewire::test(SchoolQuestions::class)
        ->assertOk()
        ->assertSee('https://qb.test/school-questions/QB-ABCDEFGHIJ', false);
});
