<?php

use App\Enums\EmploymentStatus;
use App\Enums\Gender;
use App\Enums\InvoiceStatus;
use App\Enums\UserType;
use App\Filament\Teacher\Pages\MySalary;
use App\Filament\Teacher\Widgets\TeacherSalaryOverview;
use App\Models\SalaryInvoice;
use App\Models\TeacherProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function createSalaryOverviewTeacher(): TeacherProfile
{
    $user = User::factory()->create(['user_type' => UserType::Teacher, 'is_active' => true]);

    return TeacherProfile::create([
        'user_id' => $user->id,
        'gender' => Gender::Male,
        'status' => EmploymentStatus::Active,
    ]);
}

function createSalaryOverviewInvoice(TeacherProfile $teacher, int $month, int $year, float $netAmount, InvoiceStatus $status): SalaryInvoice
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

it('sums the due amount across unpaid and partial invoices only', function () {
    $teacher = createSalaryOverviewTeacher();

    createSalaryOverviewInvoice($teacher, 5, 2026, 20000, InvoiceStatus::Unpaid);
    createSalaryOverviewInvoice($teacher, 6, 2026, 20000, InvoiceStatus::Partial);
    createSalaryOverviewInvoice($teacher, 7, 2026, 20000, InvoiceStatus::Paid);

    $this->actingAs($teacher->user);

    $data = (new TeacherSalaryOverview)->getViewData();

    expect($data['dueCount'])->toBe(2)
        ->and($data['totalDue'])->toBe(40000.0)
        ->and($data['url'])->toBe(MySalary::getUrl(panel: 'teacher'));
});

it('shows zero due when every invoice is paid', function () {
    $teacher = createSalaryOverviewTeacher();

    createSalaryOverviewInvoice($teacher, 7, 2026, 20000, InvoiceStatus::Paid);

    $this->actingAs($teacher->user);

    $data = (new TeacherSalaryOverview)->getViewData();

    expect($data['dueCount'])->toBe(0)
        ->and($data['totalDue'])->toBe(0.0);
});

it('renders the widget on the dashboard linking to the my-salary page', function () {
    $teacher = createSalaryOverviewTeacher();

    createSalaryOverviewInvoice($teacher, 7, 2026, 20000, InvoiceStatus::Unpaid);

    $response = $this->actingAs($teacher->user)->get(route('filament.teacher.pages.dashboard'));

    $response->assertOk()
        ->assertSee('My Salary')
        ->assertSee('href="'.MySalary::getUrl(panel: 'teacher').'"', false);
});
