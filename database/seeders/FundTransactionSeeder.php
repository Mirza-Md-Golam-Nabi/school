<?php

namespace Database\Seeders;

use App\Enums\TransactionSource;
use App\Enums\TransactionType;
use App\Enums\UserType;
use App\Models\AccountTransaction;
use App\Models\FundTransaction;
use App\Models\SchoolAccount;
use App\Models\TransactionCategory;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class FundTransactionSeeder extends Seeder
{
    private const TOTAL = 25;

    /**
     * Sample title/party pairs per category — a transaction's amount is randomized,
     * but its title and party come from here so the demo data reads sensibly
     * (e.g. an "Electricity Bill" fund transaction paid to "DESCO", not a donor).
     *
     * @var array<string, array<int, array{title: string, party: string}>>
     */
    private array $samples = [
        'Donation' => [
            ['title' => 'Annual Donation', 'party' => 'Rahman Enterprise'],
            ['title' => 'Eid Donation Drive', 'party' => 'Al-Amin Trading'],
            ['title' => 'Alumni Donation', 'party' => 'School Alumni Association'],
            ['title' => 'Community Donation', 'party' => 'Local Community Committee'],
        ],
        'Government Grant' => [
            ['title' => 'Government Education Grant', 'party' => 'Ministry of Education'],
            ['title' => 'Upazila Education Grant', 'party' => 'Upazila Education Office'],
            ['title' => 'Annual Development Grant', 'party' => 'Directorate of Secondary and Higher Education'],
        ],
        'Development' => [
            ['title' => 'Building Extension Fund', 'party' => 'School Development Committee'],
            ['title' => 'Development Fund Contribution', 'party' => 'Parent-Teacher Association'],
            ['title' => 'Lab Equipment Development Fund', 'party' => 'School Development Committee'],
        ],
        'Maintenance' => [
            ['title' => 'Classroom Furniture Repair', 'party' => 'City Hardware & Furniture'],
            ['title' => 'Building Painting', 'party' => 'Green Painting Service'],
            ['title' => 'AC Servicing', 'party' => 'ABC Electric Works'],
            ['title' => 'Plumbing Repair', 'party' => 'Karim Plumbing Service'],
        ],
        'Utility Bill' => [
            ['title' => 'Electricity Bill', 'party' => 'DESCO'],
            ['title' => 'Gas Bill', 'party' => 'Titas Gas'],
            ['title' => 'Water Bill', 'party' => 'WASA'],
            ['title' => 'Internet Bill', 'party' => 'Link3 Technologies'],
        ],
    ];

    /**
     * Run the database seeds.
     *
     * Creates 25 fund transactions against "Main Account" — a mix of income
     * (donations, grants) deposited into it and expense (maintenance, utility
     * bills) spent from it. Skips seeding if fund transactions already exist,
     * since there's no natural unique key to firstOrCreate() against.
     *
     * Bulk-inserts the transactions and their ledger entries directly instead of
     * calling SyncFundTransactionAccountTransactionAction (its own DB::transaction,
     * reverse-check, and increment/decrement) once per record, and posts one
     * aggregate net balance update at the end.
     */
    public function run(): void
    {
        if (FundTransaction::count() >= self::TOTAL) {
            return;
        }

        $account = SchoolAccount::firstOrCreate(
            ['name' => 'Main Account'],
            ['current_balance' => 0]
        );

        $categories = TransactionCategory::all()->keyBy('name');

        if ($categories->isEmpty()) {
            return;
        }

        $createdBy = User::where('user_type', UserType::Admin)->value('id')
            ?? User::query()->value('id');

        $now = now();
        $fundTransactionRows = [];

        for ($i = 0; $i < self::TOTAL; $i++) {
            $categoryName = array_rand($this->samples);
            $category = $categories->get($categoryName);

            if (! $category) {
                continue;
            }

            $sample = fake()->randomElement($this->samples[$categoryName]);

            $fundTransactionRows[] = [
                'transaction_category_id' => $category->id,
                'school_account_id' => $account->id,
                'type' => $category->type->value,
                'title' => $sample['title'],
                'amount' => fake()->numberBetween(1000, 50000),
                'transaction_date' => fake()->dateTimeBetween('-6 months', 'now')->format('Y-m-d'),
                'party_name' => $sample['party'],
                'description' => null,
                'created_by' => $createdBy,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        if (empty($fundTransactionRows)) {
            return;
        }

        DB::transaction(function () use ($fundTransactionRows, $account, $now) {
            $maxIdBeforeInsert = (int) (FundTransaction::max('id') ?? 0);

            collect($fundTransactionRows)->chunk(500)->each(
                fn ($chunk) => FundTransaction::insert($chunk->all())
            );

            $insertedFundTransactions = FundTransaction::where('id', '>', $maxIdBeforeInsert)
                ->get(['id', 'type', 'amount', 'title', 'transaction_date', 'created_by']);

            $transactionRows = $insertedFundTransactions->map(fn (FundTransaction $fundTransaction): array => [
                'account_id' => $account->id,
                'transaction_type' => $fundTransaction->type->value,
                'source_type' => TransactionSource::Other->value,
                'source_id' => $fundTransaction->id,
                'amount' => $fundTransaction->amount,
                'description' => $fundTransaction->title,
                'transaction_date' => $fundTransaction->transaction_date?->toDateString(),
                'created_by' => $fundTransaction->created_by,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            $transactionRows->chunk(500)->each(
                fn ($chunk) => AccountTransaction::insert($chunk->all())
            );

            $netChange = $insertedFundTransactions->sum(
                fn (FundTransaction $fundTransaction) => $fundTransaction->type === TransactionType::Income
                    ? (float) $fundTransaction->amount
                    : -(float) $fundTransaction->amount
            );

            SchoolAccount::whereKey($account->id)->increment('current_balance', $netChange);
        });
    }
}
