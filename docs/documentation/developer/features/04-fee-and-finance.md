# ৪. Fee & Finance — Developer Reference

ব্যবহারকারী নির্দেশিকা: [../../user-guide/features/04-fee-and-finance.md](../../user-guide/features/04-fee-and-finance.md)

## সূচিপত্র

- [৪.১ কোড ফাইল](#৪১-কোড-ফাইল)
- [৪.২ Database টেবিল](#৪২-database-টেবিল)
- [৪.৩ Permission](#৪৩-permission)
- [৪.৪ Fee type ↔ school account](#৪৪-fee-type--school-account)
- [৪.৫ Invoice generation](#৪৫-invoice-generation)
- [৪.৬ Discount](#৪৬-discount)
- [৪.৭ Payment processing](#৪৭-payment-processing)
- [৪.৮ Payment update / delete](#৪৮-payment-update--delete)
- [৪.৯ Default ও recover](#৪৯-default-ও-recover)
- [৪.১০ Reconciliation ও dues](#৪১০-reconciliation-ও-dues)
- [৪.১১ Receipt slip ও verification](#৪১১-receipt-slip-ও-verification)
- [৪.১২ Notification ও SMS](#৪১২-notification-ও-sms)
- [৪.১৩ Teacher panel fee](#৪১৩-teacher-panel-fee)
- [৪.১৪ টেস্ট](#৪১৪-টেস্ট)
- [৪.১৫ জানা সমস্যা ও সতর্কতা](#৪১৫-জানা-সমস্যা-ও-সতর্কতা)

---

## ৪.১ কোড ফাইল

| অংশ | ফাইল |
|---|---|
| Resources | `app/Filament/Resources/{FeeTypes, FeeDiscounts, StudentFeeDiscounts, SchoolAccounts, FeeStructures, StudentFeeInvoices, FeePayments, LateFeeRules}/` |
| Class-card pages | `ManageClassFeeStructures`, `ManageClassStudentFeeDiscounts`, `ManageClassStudentFeeInvoices`, `ManageClassFeePayments` (class cards-এর stat: `AttachClassFee*StatsAction`) |
| Invoice extras | `StudentFeeInvoices/Actions/FeeDefaultActions.php`, `Tables/ClassInvoicesByStudentTable.php`, `Widgets/ClassInvoiceDuesSummaryWidget.php`, `Widgets/FeeReconciliationWidget.php` |
| School account form | `SchoolAccounts/Schemas/SchoolAccountForm.php`, `FeeTypeResyncPolicyFields.php`, table-এর `addFeeType` action, view `filament.resources.school-accounts.fee-type-assignment-summary` |
| Payment form | `FeePayments/Schemas/FeePaymentForm.php` (+ `ScansStudentIdCard`), view `filament.resources.fee-payments.invoice-summary` |
| Actions | `GenerateMonthlyFeeInvoicesAction`, `GenerateOneTimeFeeInvoicesAction`, `GenerateOneTimeFeeInvoicesForAllClassesAction`, `CopyFeeStructuresToSessionYearAction`, `ProcessFeePaymentAction`, `SyncFeePaymentAccountTransactionAction`, `SyncFeeTypeAccountTransactionsAction`, `MarkFeeInvoicesAsDefaultAction`, `RecoverDefaultedFeeInvoicesAction`, `CalculateFeeDuesAction`, `CalculateFeeReconciliationAction`, `BuildStudentFeeSummaryAction`, `BuildFeePaymentSlipPdfAction`, `CollectPromotionFeesAction`, `FindStudentByScannedCardAction`, `Concerns/ResolvesFeeDiscount` |
| SMS | `app/Actions/Sms/SendFeeInvoiceSmsAction`, `SendFeePaymentSmsAction` |
| Notifications | `StudentFeeInvoiceGeneratedNotification`, `StudentFeeInvoiceUpdatedNotification`, `FeePaymentReceivedNotification`, `Concerns/NotifiesStudentFeeInvoice` |
| Controllers | `FeePaymentSlipPdfController` (`/fee-payments/{batchId}/slip`), `VerifyFeeReceiptController` (`/verify/receipt/{code}`, `/r/{code}`) |
| Command | `GenerateMonthlyFeeInvoices` (মাসের ১ তারিখ ০৭:০০) |
| Models | `FeeType`, `FeeStructure`, `FeeDiscount`, `StudentFeeDiscount`, `StudentFeeInvoice`, `FeePayment`, `LateFeeRule`, `SchoolAccount`, `AccountTransaction` |
| Enums | `InvoiceStatus`, `PaymentMethod`, `FeeDiscountType`, `FineType`, `TransactionType`, `TransactionSource` |

## ৪.২ Database টেবিল

| টেবিল | নোট |
|---|---|
| `fee_types` | `name`, `is_monthly`, `is_active`, `school_account_id` (এক fee type এক fund) |
| `fee_structures` | `class_id`, `fee_type_id`, `amount`, `due_day` (nullable ১–৩১), `session_year`, `is_active` |
| `fee_discounts` | `name`, `discount_type` (percent/fixed), `discount_value` |
| `student_fee_discounts` | `student_id`, `fee_type_id`, `discount_id`, `session_year`, `approved_by`, `remarks` |
| `student_fee_invoices` | `student_id`, `fee_type_id`, `month` (one-time-এ null), `year`, `original_amount`, `discount_amount`, `fine_amount`, `waiver_amount`, `net_amount`, `status`, `waiver_by`, `waiver_reason`, `default_amount`, `defaulted_at`, `default_reason` |
| `fee_payments` | `invoice_id`, `student_id`, `receipt_no` (প্রতি row-এ unique — একই submission-এর row-গুলো একই base receipt, শেষে `/n` suffix), `payment_batch_id` (একই submission), `amount_paid`, `payment_method`, `transaction_id`, `payment_date`, `received_by`, `remarks` |
| `late_fee_rules` | `class_id`, `fee_type_id`, `grace_days`, `fine_type`, `fine_value`, `is_active` |
| `school_accounts` | `name`, `current_balance` (opening balance create-এ, পরে auto) |
| `account_transactions` | ledger — fee payment-এর income এন্ট্রি; বিস্তারিত [Fund Management](10-fund-management.md) |

## ৪.৩ Permission

সব fee resource `HasEntityPermissions` (`{action}_{table}`) — `view/create/edit/delete` × `fee_types`, `fee_structures`, `fee_discounts`, `student_fee_invoices`, `fee_payments`, `late_fee_rules`, `school_accounts`। অতিরিক্ত: `mark_fee_default`, `recover_fee_default` (`FeeDefaultActions`)।

**সতর্কতা:** `StudentFeeDiscountResource` `HasEntityPermissions` ব্যবহার করে না এবং কোনো override নেই — permission ছাড়া যেকোনো admin-panel ইউজার শিক্ষার্থীকে ছাড় দিতে পারে। এর জন্য `FeePermission`-এ আলাদা কোনো permission-ও নেই (`view_student_fee_discounts` ইত্যাদি enum-এ নেই)।

## ৪.৪ Fee type ↔ school account

- `fee_types.school_account_id` — একটা fee type সর্বোচ্চ একটা fund-এ
- Account form-এ শুধু unassigned (বা এই account-এর) fee type দেখায়; অন্য fund থেকে সরাতে table-এর "+" (`addFeeType`) action
- `FeeTypeResyncPolicyFields`: `resync_mode` = `all` / `years` (+ `resync_years`) / `none` — `SyncFeeTypeAccountTransactionsAction` পুরনো payment-এর account transaction নতুন fund-এ সরায়
- `current_balance` create-এ opening balance; edit-এ disabled — payment/transaction থেকে auto

## ৪.৫ Invoice generation

`GenerateMonthlyFeeInvoicesAction::handle($month, $year, ?$classId)`:

- active student, class-এর active monthly fee structure
- calendar month → প্রতিটা class-এর session (`AcademicSession::yearForDate`) — যেমন মার্চ ২০২৭ college-এর session ২০২৬
- একই student + fee type + month + year থাকলে skip
- `original_amount = structure.amount`, `discount_amount = resolveDiscount()`, `fine = 0`, `waiver = 0`, `net = max(0, original − discount)`, status `Unpaid`
- guardian SMS batch + student notification

`GenerateOneTimeFeeInvoicesAction` — structure-এর class-এর সব active student, `month = null`; duplicate key student + fee type + year। `...ForAllClassesAction` সব class-এর SMS একটা batch-এ পাঠাতে inner method SMS ছাড়া চালায়।

Scheduler: `GenerateMonthlyFeeInvoices` — `monthlyOn(1, '07:00')`।

Manual invoice form: `has_duplicate` hidden + Callout — duplicate হলে তৈরি আটকায়; `net_amount = max(0, original − discount + fine − waiver)`; Defaulted status হলে status select disabled (`InvoiceStatus::selectableOptions()`)।

## ৪.৬ Discount

`ResolvesFeeDiscount::resolveDiscount($student, $feeTypeId, $amount, $sessionYear)`:

```
percent → round(amount × value / 100, 2)
fixed   → min(value, amount)
```

Student-এর সেই fee type + session-এর discount না থাকলে ০। ইতিমধ্যে তৈরি invoice-এ পরে দেওয়া discount retroactively বসে না।

## ৪.৭ Payment processing

`ProcessFeePaymentAction`:

- বাছাই করা invoice-গুলো **oldest-first** বণ্টন
- প্রতি invoice একটা `FeePayment` — একই `payment_batch_id`, receipt base একই (row-এ `/n` suffix দিয়ে unique); slip ও verification batch ধরে কাজ করে
- invoice status: সম্পূর্ণ → Paid, আংশিক → Partial
- Defaulted invoice-এ টাকা নেওয়া যায় না (আগে recover)
- কোনো invoice-এ বাকি না থাকলে `ValidationException`
- প্রতি paid invoice-এর জন্য student notification; guardian SMS
- `receipt_no` ডিফল্ট `'RCP-'.strtoupper(uniqid())`, form-এ `unique(ignoreRecord)`

Student বাছাই: `ScansStudentIdCard` — USB 2D scanner QR লিংক টাইপ করে Enter চাপে → form submit না করে student বাছাই; হাতে Student ID; ক্যামেরা দিয়ে QR (`FindStudentByScannedCardAction`)। `student_type` (`current` / `former`, dehydrated false) — former student-এর বকেয়া নিতে।

## ৪.৮ Payment update / delete

`FeePayment` model events:

- `updated` (`amount_paid`, `invoice_id`, `payment_date`, `received_by`, `receipt_no` বদলালে) → `SyncFeePaymentAccountTransactionAction::sync()` — পুরনো transaction reverse, fee type-এর বর্তমান fund-এ repost
- `deleted` → `reverse()` (balance ফেরত), ওই receipt-এর `FeePaymentReceivedNotification` database notification মুছে, invoice status পুনর্হিসাব: Waived হলে কিছু নয়; Defaulted হলে শুধু `default_amount = max(0, net − totalPaid)`; অন্যথায় Unpaid / Partial / Paid

## ৪.৯ Default ও recover

`MarkFeeInvoicesAsDefaultAction` — Unpaid/Partial invoice → `Defaulted`, `default_amount` = বাকি, `defaulted_at`, `default_reason`। **কোনো account transaction নয়** (টাকা আসেনি)। আগের payment যেমন ছিল থাকে। একজন student-এর সব বকেয়া একসাথেও।

`RecoverDefaultedFeeInvoicesAction` — status payment অনুযায়ী Unpaid/Partial-এ ফেরে, default তথ্য মোছে (activity log-এ থাকে)।

UI (`FeeDefaultActions`): `markAsDefault` (row + bulk), `recoverDefault` (row), `markStudentDuesAsDefault`, `recoverStudentDues` (student edit header; শুধু `defaultedDueAmount() > 0` হলে visible), `defaultedDuesWarning()` (restore ও promotion confirmation-এ)। Permission `mark_fee_default` / `recover_fee_default`।

## ৪.১০ Reconciliation ও dues

`CalculateFeeReconciliationAction` (দুই query):

```
billed = collected + waived + defaulted + receivable
```

`FeeReconciliationWidget` — `ListStudentFeeInvoices` header-এ, সব সময়ের। `ClassInvoiceDuesSummaryWidget` — class পাতায় Unpaid / Partial / Defaulted / Total Outstanding (partial-এর বাকি = net − paid)। `CalculateFeeDuesAction` — dashboard-এর আদায়যোগ্য ও default বকেয়া।

## ৪.১১ Receipt slip ও verification

- `BuildFeePaymentSlipPdfAction` — batch অনুযায়ী slip; QR + verification code
- Public: `/verify/receipt/{code}` (`throttle:30,1`), SMS-এর ছোট লিংক `/r/{code}` → redirect
- `FeePayment`-এ code lookup — সব payment মুছে গেলে unknown

## ৪.১২ Notification ও SMS

| Event | Notification | SMS event |
|---|---|---|
| Invoice তৈরি (auto / manual / one-time / promotion) | `StudentFeeInvoiceGeneratedNotification` | `fee_invoice_generated` |
| Invoice update | `StudentFeeInvoiceUpdatedNotification` | — |
| Payment | `FeePaymentReceivedNotification` | `fee_payment_received` (receipt short link) |

SMS অভিভাবকের নম্বরে, `SmsSetting`-এ event চালু থাকলে।

## ৪.১৩ Teacher panel fee

`app/Filament/Teacher/Resources/StudentFeeInvoices/` ও `FeePayments/` — admin form-এর কপি, `HasEntityPermissions` দিয়ে গেট করা এবং শিক্ষকের নিজের class-এর শিক্ষার্থীতে সীমিত। বিস্তারিত: [panels.md](../panels.md)।

## ৪.১৪ টেস্ট

`GenerateMonthlyFeeInvoicesActionTest`, `GenerateOneTimeFeeInvoicesActionTest`, `GenerateOneTimeFeeInvoicesForAllClassesActionTest`, `FeeStructuresSessionYearCopyTest`, `FeeStructureDueDayNullableTest`, `FeeStructureStatusLabelTest`, `FeeInvoiceDefaultTest`, `FeePaymentDeletionRollbackTest`, `FeePaymentNotificationTest`, `FeePaymentReceivedByFieldTest`, `FeePaymentSlipPdfTest`, `FeePaymentClassFilterTest`, `FeeCounterIdCardScanTest`, `SchoolAccountFeeSyncTest`, `SchoolAccountFormBilingualHelperTextTest`, `FeeTypeFundLinkActivityLogTest`, `StudentFeeDiscountApproverFilterTest`, `StudentFeeInvoiceMonthSelectTest`, `StudentFeeInvoiceNotificationTest`, `StudentFeeInvoiceWaiverByFilterTest`, `EditStudentFeeInvoiceClassPrefillTest`, `ClassInvoicesByStudentTable*Test`, `TeacherFeeCollectionTest`, `TeacherFeeInvoiceCrudTest`, `VerifyFeeReceiptTest`, `ActivityLogFee*Test`।

## ৪.১৫ জানা সমস্যা ও সতর্কতা

1. `StudentFeeDiscountResource`-এ authorization নেই এবং permission enum-ও নেই (৪.৩)।
2. **Late fee কার্যকর নয়:** `LateFeeRuleResource` navigation থেকে লুকানো (`LateFeeRuleNavigationHiddenTest`), আর কোনো action বা command `late_fee_rules` পড়ে invoice-এ `fine_amount` বসায় না — সব generator `fine_amount = 0` দেয়। জরিমানা শুধু হাতে।
3. Payment gateway (bKash ইত্যাদি) নেই — ইচ্ছাকৃতভাবে স্থগিত। `PaymentMethod::Bkash` / `Nagad` শুধু হাতে এন্ট্রির লেবেল।
4. `FeePaymentForm`-এর class filter `Classes::pluck()` — inactive class-ও দেখায় (former student-এর জন্য সম্ভবত ইচ্ছাকৃত)।
