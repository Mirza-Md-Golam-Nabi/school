# ৯. Salary Management — Developer Reference

ব্যবহারকারী নির্দেশিকা: [../../user-guide/features/09-salary-management.md](../../user-guide/features/09-salary-management.md)

## সূচিপত্র

- [৯.১ কোড ফাইল](#৯১-কোড-ফাইল)
- [৯.২ Database টেবিল](#৯২-database-টেবিল)
- [৯.৩ Permission](#৯৩-permission)
- [৯.৪ Salary structure](#৯৪-salary-structure)
- [৯.৫ Invoice generation](#৯৫-invoice-generation)
- [৯.৬ Deduction](#৯৬-deduction)
- [৯.৭ Payment](#৯৭-payment)
- [৯.৮ Account ledger sync](#৯৮-account-ledger-sync)
- [৯.৯ Notification](#৯৯-notification)
- [৯.১০ টেস্ট](#৯১০-টেস্ট)
- [৯.১১ জানা সমস্যা ও সতর্কতা](#৯১১-জানা-সমস্যা-ও-সতর্কতা)

---

## ৯.১ কোড ফাইল

| অংশ | ফাইল |
|---|---|
| Resources | `app/Filament/Resources/{SalaryComponents, SalaryStructures, SalaryInvoices, SalaryPayments, SalaryBulkPayments}/` |
| Shared field | `app/Filament/Resources/Concerns/HasProfileableFields.php` — Teacher / Staff type + person select |
| Actions | `GenerateMonthlySalaryInvoicesAction`, `CreateManualSalaryInvoiceAction`, `ProcessIndividualSalaryPaymentAction`, `ProcessPayrollBatchPaymentAction`, `SyncSalaryPaymentAccountTransactionAction` |
| Command | `GenerateMonthlySalaryInvoices` (মাসের শেষ দিন ২৩:০০) |
| Notifications | `SalaryInvoiceGeneratedNotification`, `SalaryPaymentReceivedNotification` |
| Teacher panel | `Teacher/Pages/MySalary`, `Teacher/Widgets/TeacherSalaryOverview` |
| Models | `SalaryComponent`, `SalaryStructure`, `SalaryStructureComponent`, `SalaryInvoice`, `SalaryInvoiceComponent`, `SalaryInvoiceDeduction`, `SalaryPayment`, `SalaryBulkPayment` |
| Enums | `SalaryComponentType` (allowance / deduction), `InvoiceStatus`, `PaymentMethod` |

## ৯.২ Database টেবিল

| টেবিল | নোট |
|---|---|
| `salary_components` | `name`, `type`, `is_active` |
| `salary_structures` | polymorphic `profileable` (Teacher / StaffProfile), `effective_from`, `effective_to`, `use_components`, `flat_amount` / `amount` |
| `salary_structure_components` | `salary_structure_id`, `salary_component_id`, `amount` |
| `salary_invoices` | `invoice_no`, polymorphic `profileable`, `month`, `year`, `gross_amount`, `deduction_amount`, `net_amount`, `status`, `is_manual` |
| `salary_invoice_components` | invoice-এর component snapshot |
| `salary_invoice_deductions` | ad-hoc deduction (`amount`, `reason`) — invoice total পুনর্হিসাব |
| `salary_payments` | `salary_invoice_id`, `amount_paid`, `school_account_id`, `payment_method`, `transaction_id`, `payment_date`, `salary_bulk_payment_id` |
| `salary_bulk_payments` | batch wrapper — `total_amount`, `school_account_id`, `payment_method`, `payment_date`, `paid_by`, `remarks` |

## ৯.৩ Permission

`SalaryPermission` — components, structures, invoices, payments, bulk payments প্রতিটার view / create / edit / delete; পাঁচটা resource `HasEntityPermissions` ব্যবহার করে।

## ৯.৪ Salary structure

- `use_components = false` → `flat_amount`; `true` → Repeater (active component + amount)
- নতুন structure save হলে একই ব্যক্তির ongoing (`effective_to = null`) structure নতুনটার `effective_from − 1 day`-এ বন্ধ হয় (`SalaryStructureAutoCloseTest`); ভবিষ্যৎ-তারিখের ongoing structure backdated নতুনটায় ছোঁয় না; অন্য ব্যক্তির structure-এ নয়
- বেতন বদলাতে পুরনোটা edit নয়, নতুন structure

## ৯.৫ Invoice generation

`GenerateMonthlySalaryInvoicesAction::handle(?$month, ?$year)` (ডিফল্ট = now — তাই মাসের শেষ দিন ২৩:০০-এ চললে সেই মাসেরই invoice):

- active teacher / staff
- ব্যক্তি প্রতি সর্বশেষ effective structure (এক query)
- skip: structure নেই, target মাসের পরে যোগদান, ওই মাসে invoice আছে
- `gross = Σ allowance` (বা flat), `deduction = Σ deduction component`, `net = max(0, gross − deduction)`; component snapshot `salary_invoice_components`-এ

`CreateManualSalaryInvoiceAction`: flat `net_amount`, `is_manual = true`, component breakdown নেই; একই মাসে থাকলে ব্যর্থ।

Invoice-এ payment থাকলে delete নিষেধ (`SalaryInvoiceStatusTest`)।

## ৯.৬ Deduction

`SalaryInvoiceDeduction` তৈরি / মোছায় invoice-এর `deduction_amount` ও `net_amount` পুনর্হিসাব; payment থাকা invoice-এ deduction যোগ নিষেধ (`SalaryInvoiceDeductionTest`)।

## ৯.৭ Payment

| Action | নিয়ম |
|---|---|
| `ProcessIndividualSalaryPaymentAction` | এক ব্যক্তির বাছাই করা invoice-এ admin-এর দেওয়া amount **oldest-first**; শেষটায় partial allowed; একাধিক `SalaryPayment` হলে `SalaryBulkPayment` wrapper |
| `ProcessPayrollBatchPaymentAction` | একাধিক ব্যক্তির invoice **পুরো** due পরিশোধ, partial নয়, এক account; একাধিক হলে wrapper; form-এ running total |

Status: full → Paid, আংশিক → Partial; শেষ payment মুছলে Unpaid-এ ফেরে।

Default account: teacher / staff profile-এর `default_school_account_id`।

## ৯.৮ Account ledger sync

`SalaryPayment` model event → `SyncSalaryPaymentAccountTransactionAction::sync()` — account-এ expense এন্ট্রি; update-এ reverse + repost, delete-এ reverse। বিস্তারিত [Fund Management](10-fund-management.md)।

## ৯.৯ Notification

invoice তৈরি → `SalaryInvoiceGeneratedNotification`; payment → `SalaryPaymentReceivedNotification` (teacher / staff user)।

## ৯.১০ টেস্ট

`GenerateMonthlySalaryInvoicesActionTest`, `CreateManualSalaryInvoiceActionTest`, `ProcessIndividualSalaryPaymentActionTest`, `ProcessPayrollBatchPaymentActionTest`, `SalaryInvoiceDeductionTest`, `SalaryInvoiceStatusTest`, `SalaryStructureAutoCloseTest`, `SalaryStructuresTableSortTest`, `SalaryPaymentAccountTransactionTest`, `SalaryPaymentNotificationTest`, `SalaryFilamentPagesSmokeTest`, `MySalaryPageTest`, `TeacherSalaryOverviewTest`, `ActivityLogSalaryTest`।

## ৯.১১ জানা সমস্যা ও সতর্কতা

1. `SalaryInvoiceDeduction` (ad-hoc deduction) model ও logic আছে, কিন্তু কোনো Filament UI নেই — ব্যবহারকারী স্ক্রিন থেকে যোগ করতে পারে না।
2. `SalaryInvoiceResource`-এ শুধু List page; invoice edit করার পেজ নেই (view modal আছে)।
3. Salary SMS নেই — শুধু database / push notification।
