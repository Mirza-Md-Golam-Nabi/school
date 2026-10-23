<?php

use App\Models\Classes;
use App\Models\Section;
use App\Support\HmacSignature;
use App\Support\QuestionBankConnection;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    QuestionBankConnection::save('https://qb.test', 'QB-ABCDEFGHIJ', 'the-school-secret');
    QuestionBankConnection::markConnected('Sunrise High School');

    /** A GET the Question Bank signs as it does: the body (empty) under the secret. */
    $this->askAsQuestionBank = function (string $secret = 'the-school-secret', string $code = 'QB-ABCDEFGHIJ', ?int $at = null) {
        $timestamp = (string) ($at ?? now()->timestamp);

        // A plain GET with no body — getJson() would send "[]" as one.
        return $this->withHeaders([
            'Accept' => 'application/json',
            'X-School-Code' => $code,
            'X-Timestamp' => $timestamp,
            'X-Signature' => HmacSignature::sign($secret, $timestamp, ''),
        ])->get('/api/question-bank/classes');
    };
});

it('lists the active classes in order, with their active sections', function () {
    $nine = Classes::create(['name' => 'Class Nine', 'name_bn' => 'নবম শ্রেণি', 'order' => 9, 'has_section' => true, 'is_active' => true]);
    Section::create(['class_id' => $nine->id, 'name' => 'B', 'is_active' => true]);
    Section::create(['class_id' => $nine->id, 'name' => 'A', 'is_active' => true]);
    Section::create(['class_id' => $nine->id, 'name' => 'Closed', 'is_active' => false]);
    $six = Classes::create(['name' => 'Class Six', 'order' => 6, 'has_section' => false, 'is_active' => true]);
    Classes::create(['name' => 'Old class', 'order' => 1, 'is_active' => false]);

    ($this->askAsQuestionBank)()
        ->assertOk()
        ->assertExactJson(['classes' => [
            ['id' => $six->id, 'name' => 'Class Six', 'name_bn' => null, 'sections' => []],
            ['id' => $nine->id, 'name' => 'Class Nine', 'name_bn' => 'নবম শ্রেণি', 'sections' => [
                ['id' => Section::where('name', 'A')->value('id'), 'name' => 'A'],
                ['id' => Section::where('name', 'B')->value('id'), 'name' => 'B'],
            ]],
        ]]);
});

it('refuses anyone without this school\'s code and secret, or an old request', function () {
    ($this->askAsQuestionBank)(secret: 'not-the-secret')->assertUnauthorized();
    ($this->askAsQuestionBank)(code: 'QB-SOMEONEELS')->assertUnauthorized();
    ($this->askAsQuestionBank)(at: now()->subMinutes(10)->timestamp)->assertUnauthorized();
});

it('refuses everything while the school is not connected', function () {
    QuestionBankConnection::save('https://qb.test', 'QB-ABCDEFGHIJ', null);

    ($this->askAsQuestionBank)()->assertUnauthorized();
});
