<?php

use App\Enums\TransactionType;
use App\Enums\UserType;
use App\Models\FundTransaction;
use App\Models\SchoolAccount;
use App\Models\TransactionCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

function createAttachmentTestTransaction(?string $attachmentPath): FundTransaction
{
    $account = SchoolAccount::create(['name' => 'Main Fund', 'current_balance' => 100000]);
    $category = TransactionCategory::create(['name' => 'Maintenance', 'type' => TransactionType::Expense, 'is_active' => true]);

    return FundTransaction::create([
        'transaction_category_id' => $category->id,
        'school_account_id' => $account->id,
        'type' => TransactionType::Expense,
        'title' => 'Repair work',
        'amount' => 3000,
        'transaction_date' => now()->toDateString(),
        'attachment_path' => $attachmentPath,
    ]);
}

it('lets an admin view an existing attachment', function () {
    Storage::fake('local');
    Storage::disk('local')->put('fund-transaction-attachments/receipt.pdf', 'pdf content');

    $fundTransaction = createAttachmentTestTransaction('fund-transaction-attachments/receipt.pdf');
    $admin = User::factory()->create(['user_type' => UserType::Admin, 'is_active' => true]);

    $response = $this->actingAs($admin)->get(route('fund-transactions.attachment', $fundTransaction));

    $response->assertOk();
});

it('lets staff view an existing attachment', function () {
    Storage::fake('local');
    Storage::disk('local')->put('fund-transaction-attachments/receipt.pdf', 'pdf content');

    $fundTransaction = createAttachmentTestTransaction('fund-transaction-attachments/receipt.pdf');
    $staff = User::factory()->create(['user_type' => UserType::Staff, 'is_active' => true]);

    $response = $this->actingAs($staff)->get(route('fund-transactions.attachment', $fundTransaction));

    $response->assertOk();
});

it('forbids a teacher from viewing a fund transaction attachment', function () {
    Storage::fake('local');
    Storage::disk('local')->put('fund-transaction-attachments/receipt.pdf', 'pdf content');

    $fundTransaction = createAttachmentTestTransaction('fund-transaction-attachments/receipt.pdf');
    $teacher = User::factory()->create(['user_type' => UserType::Teacher, 'is_active' => true]);

    $response = $this->actingAs($teacher)->get(route('fund-transactions.attachment', $fundTransaction));

    $response->assertForbidden();
});

it('forbids a student from viewing a fund transaction attachment', function () {
    Storage::fake('local');
    Storage::disk('local')->put('fund-transaction-attachments/receipt.pdf', 'pdf content');

    $fundTransaction = createAttachmentTestTransaction('fund-transaction-attachments/receipt.pdf');
    $student = User::factory()->create(['user_type' => UserType::Student, 'is_active' => true]);

    $response = $this->actingAs($student)->get(route('fund-transactions.attachment', $fundTransaction));

    $response->assertForbidden();
});

it('rejects a guest instead of exposing the attachment', function () {
    Storage::fake('local');
    Storage::disk('local')->put('fund-transaction-attachments/receipt.pdf', 'pdf content');

    $fundTransaction = createAttachmentTestTransaction('fund-transaction-attachments/receipt.pdf');

    $response = $this->get(route('fund-transactions.attachment', $fundTransaction));

    $response->assertUnauthorized();
});

it('returns 404 when the fund transaction has no attachment', function () {
    $fundTransaction = createAttachmentTestTransaction(null);
    $admin = User::factory()->create(['user_type' => UserType::Admin, 'is_active' => true]);

    $response = $this->actingAs($admin)->get(route('fund-transactions.attachment', $fundTransaction));

    $response->assertNotFound();
});

it('returns 404 when the attachment path is set but the file is missing from disk', function () {
    Storage::fake('local');

    $fundTransaction = createAttachmentTestTransaction('fund-transaction-attachments/missing.pdf');
    $admin = User::factory()->create(['user_type' => UserType::Admin, 'is_active' => true]);

    $response = $this->actingAs($admin)->get(route('fund-transactions.attachment', $fundTransaction));

    $response->assertNotFound();
});
