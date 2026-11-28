# ১০. Fund Management — Developer Reference

ব্যবহারকারী নির্দেশিকা: [../../user-guide/features/10-fund-management.md](../../user-guide/features/10-fund-management.md)

## সূচিপত্র

- [১০.১ কোড ফাইল](#১০১-কোড-ফাইল)
- [১০.২ Database টেবিল](#১০২-database-টেবিল)
- [১০.৩ Permission](#১০৩-permission)
- [১০.৪ Ledger মডেল](#১০৪-ledger-মডেল)
- [১০.৫ Sync action](#১০৫-sync-action)
- [১০.৬ Attachment](#১০৬-attachment)
- [১০.৭ Widget](#১০৭-widget)
- [১০.৮ টেস্ট](#১০৮-টেস্ট)
- [১০.৯ জানা সমস্যা ও সতর্কতা](#১০৯-জানা-সমস্যা-ও-সতর্কতা)

---

## ১০.১ কোড ফাইল

| অংশ | ফাইল |
|---|---|
| Resources | `app/Filament/Resources/TransactionCategories/`, `FundTransactions/` (category select-এ inline create option), `AccountTransactions/` (read-only ledger + `Widgets/IncomeExpenseOverviewWidget`, `TransactionTypeBreakdownWidget`) |
| Actions | `SyncFundTransactionAccountTransactionAction`, `SyncFeePaymentAccountTransactionAction`, `SyncSalaryPaymentAccountTransactionAction`, `SyncFeeTypeAccountTransactionsAction` |
| Controller | `FundTransactionAttachmentController` (`/fund-transactions/{fundTransaction}/attachment`) |
| Models | `TransactionCategory`, `FundTransaction`, `AccountTransaction`, `SchoolAccount` |
| Enums | `TransactionType` (income / expense), `TransactionSource` (fee_payment / salary / other) |

## ১০.২ Database টেবিল

| টেবিল | নোট |
|---|---|
| `transaction_categories` | `name`, `type`, `is_active` |
| `fund_transactions` | `transaction_category_id`, `type` (category থেকে), `school_account_id`, `title`, `amount`, `transaction_date`, `party_name`, `attachment_path` (private disk), `description`, `created_by` |
| `account_transactions` | `account_id`, `transaction_type`, `source_type` + source id (polymorphic-like), `amount`, `transaction_date`, `description`, `created_by` |
| `school_accounts` | `current_balance` |

## ১০.৩ Permission

`FeePermission` এর ভেতরে: `view/create/edit/delete_transaction_categories`, `*_fund_transactions`, `*_account_transactions`, `*_school_accounts` — সব resource `HasEntityPermissions`।

## ১০.৪ Ledger মডেল

`account_transactions` = সব টাকার চলাচলের একমাত্র ledger:

| Source | Type | তৈরি করে |
|---|---|---|
| `fee_payment` | income | `FeePayment` events → `SyncFeePaymentAccountTransactionAction` (fee type-এর fund-এ) |
| `salary` | expense | `SalaryPayment` events → `SyncSalaryPaymentAccountTransactionAction` |
| `other` | category type | `FundTransaction` events → `SyncFundTransactionAccountTransactionAction` |

প্রতিটা sync: পুরনো transaction reverse (balance ফেরত) → নতুন post (balance আপডেট)। Delete → reverse। `SchoolAccount::current_balance` কখনো হাতে edit নয়। Fee default-এ কোনো ledger এন্ট্রি নেই।

`AccountTransaction::party` / `partyRoleLabel()` — source অনুযায়ী student, teacher/staff বা vendor/donor (`AccountLedgerPartyLabelTest`)।

## ১০.৫ Sync action

`FundTransaction::booted()`:

- `created` → `sync()`
- `updated` (`amount`, `type`, `school_account_id`, `transaction_date` বদলালে) → `sync()`
- `deleted` → `reverse()`

## ১০.৬ Attachment

- `FileUpload` private disk — helper text: শুধু admin/staff দেখবে
- Controller: `auth()->check()` নইলে 401; `isAdmin() || isSuperAdmin() || isStaff()` নইলে 403; file না থাকলে 404। Route-এ `auth` middleware নেই (named `login` route না থাকায়)
- `FundTransactionAttachmentAccessTest`

## ১০.৭ Widget

- `IncomeExpenseOverviewWidget` — URL state `type`, `from`, `until`; income / expense মোট; ক্লিক → filtered ledger
- `TransactionTypeBreakdownWidget` — `source_type` অনুযায়ী যোগফল
- `IncomeExpenseOverviewWidgetTest`

## ১০.৮ টেস্ট

`FundTransactionAccountBalanceTest`, `FundTransactionAttachmentAccessTest`, `FundTransactionFilamentPagesSmokeTest`, `AccountLedgerPartyLabelTest`, `IncomeExpenseOverviewWidgetTest`, `ActivityLogFinanceLedgerTest`, `SchoolAccountFeeSyncTest`, `SalaryPaymentAccountTransactionTest`।

## ১০.৯ জানা সমস্যা ও সতর্কতা

1. Attachment controller `isStaff()` দেখে, fund transaction permission নয় — যেকোনো staff যেকোনো বিল দেখতে পারে।
2. Account থেকে account-এ transfer-এর আলাদা ধরন নেই — দুটো fund transaction (expense + income) দিয়ে করতে হয়।
3. Account balance negative হতে বাধা নেই।
