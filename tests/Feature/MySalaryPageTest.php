<?php

use App\Enums\EmploymentStatus;
use App\Enums\Gender;
use App\Enums\InvoiceStatus;
use App\Enums\UserType;
use App\Filament\Teacher\Pages\MySalary;
use App\Models\SalaryInvoice;
use App\Models\TeacherProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function createMySalaryPageTeacher(): TeacherProfile
{
    $user = User::factory()->create(['user_type' => UserType::Teacher, 'is_active' => true]);

    return TeacherProfile::create([
        'user_id' => $user->id,
        'gender' => Gender::Male,
        'status' => EmploymentStatus::Active,
    ]);
}

function createMySalaryPageInvoice(TeacherProfile $teacher, int $month, int $year, float $netAmount, InvoiceStatus $status): SalaryInvoice
{
    return SalaryInvoice::create([
        'invoice_no' => 'SAL-'.$year.'-'.str_pad((string) $month, 2, '0', STR_PAD_LEFT).'-'.str_pad((string) random_int(1, 9999), 4, '0', STR_PAD_LEFT),
        'profileable_type' => TeacherProfile::class,
        'profileable_id' => $teacher->id,
        'month' => $month,
        'year' => $year,
        'gross_amount' => $netAmount,
        'deduction_amount' => 0,
        'net_amount' => $netAmount,
        'status' => $status,
        'is_manual' => true,
    ]);
}

it('splits invoices into due and paid buckets, each ordered most-recent-first', function () {
    $teacher = createMySalaryPageTeacher();

    createMySalaryPageInvoice($teacher, 3, 2026, 10000, InvoiceStatus::Unpaid);
    createMySalaryPageInvoice($teacher, 5, 2026, 10000, InvoiceStatus::Partial);
    createMySalaryPageInvoice($teacher, 1, 2026, 10000, InvoiceStatus::Paid);
    createMySalaryPageInvoice($teacher, 4, 2026, 10000, InvoiceStatus::Paid);

    $this->actingAs($teacher->user);

    $data = (new MySalary)->getViewData();

    expect($data['dueInvoices'])->toHaveCount(2);
    expect($data['dueInvoices'][0]->month)->toBe(5);
    expect($data['dueInvoices'][1]->month)->toBe(3);

    expect($data['paidInvoices'])->toHaveCount(2);
    expect($data['paidInvoices'][0]->month)->toBe(4);
    expect($data['paidInvoices'][1]->month)->toBe(1);

    expect($data['totalDue'])->toBe(20000.0);
});

it('renders the due section before the paid section on the page', function () {
    $teacher = createMySalaryPageTeacher();

    createMySalaryPageInvoice($teacher, 5, 2026, 10000, InvoiceStatus::Unpaid);
    createMySalaryPageInvoice($teacher, 4, 2026, 10000, InvoiceStatus::Paid);

    $response = $this->actingAs($teacher->user)->get(MySalary::getUrl(panel: 'teacher'));

    $response->assertOk();

    $content = $response->getContent();
    $duePosition = strpos($content, '>Due<');
    $paidPosition = strpos($content, '>Paid<');

    expect($duePosition)->not->toBeFalse()
        ->and($paidPosition)->not->toBeFalse()
        ->and($duePosition)->toBeLessThan($paidPosition);
});

it('shows an empty state when the teacher has no salary invoices', function () {
    $teacher = createMySalaryPageTeacher();

    $response = $this->actingAs($teacher->user)->get(MySalary::getUrl(panel: 'teacher'));

    $response->assertOk()
        ->assertSee('কোনো বকেয়া নেই')
        ->assertSee('এখনো কোনো পেমেন্ট রেকর্ড নেই');
});
