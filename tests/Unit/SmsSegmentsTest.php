<?php

use App\Support\Sms\BangladeshPhone;
use App\Support\Sms\SmsSegments;

it('counts plain text as 160 characters per SMS and 153 per part after that', function (int $length, int $parts) {
    expect(SmsSegments::count(str_repeat('a', $length)))->toBe($parts);
})->with([
    'empty' => [0, 0],
    'one char' => [1, 1],
    'exactly 160' => [160, 1],
    '161 splits' => [161, 2],
    'exactly 306' => [306, 2],
    '307' => [307, 3],
]);

it('counts Bangla text as 70 characters per SMS and 67 per part after that', function (int $length, int $parts) {
    expect(SmsSegments::count(str_repeat('ক', $length)))->toBe($parts);
})->with([
    'exactly 70' => [70, 1],
    '71 splits' => [71, 2],
    'exactly 134' => [134, 2],
    '135' => [135, 3],
]);

it('treats a single Bangla character as turning the whole message Unicode', function () {
    expect(SmsSegments::count(str_repeat('a', 100).'ক'))->toBe(2);
});

it('counts GSM extended characters twice', function () {
    expect(SmsSegments::count(str_repeat('a', 159).'{'))->toBe(2);
});

it('normalizes Bangladeshi mobile numbers', function (?string $input, ?string $expected) {
    expect(BangladeshPhone::normalize($input))->toBe($expected);
})->with([
    'local' => ['01712345678', '8801712345678'],
    'with plus' => ['+8801712345678', '8801712345678'],
    'with country code' => ['8801712345678', '8801712345678'],
    'with spaces and dash' => ['01712-345 678', '8801712345678'],
    'too short' => ['0171234567', null],
    'landline' => ['0212345678', null],
    'invalid operator' => ['01212345678', null],
    'empty' => ['', null],
    'null' => [null, null],
]);
