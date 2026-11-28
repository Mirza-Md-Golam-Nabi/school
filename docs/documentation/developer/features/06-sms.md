# ৬. SMS ও Central API — Developer Reference

ব্যবহারকারী নির্দেশিকা: [../../user-guide/features/06-sms.md](../../user-guide/features/06-sms.md)

## সূচিপত্র

- [৬.১ কোড ফাইল](#৬১-কোড-ফাইল)
- [৬.২ Database টেবিল](#৬২-database-টেবিল)
- [৬.৩ Permission](#৬৩-permission)
- [৬.৪ ডিজাইনের মূল সিদ্ধান্ত](#৬৪-ডিজাইনের-মূল-সিদ্ধান্ত)
- [৬.৫ পাঠানোর pipeline](#৬৫-পাঠানোর-pipeline)
- [৬.৬ Segment ও খরচ](#৬৬-segment-ও-খরচ)
- [৬.৭ Event ও template](#৬৭-event-ও-template)
- [৬.৮ Vendor ও failover](#৬৮-vendor-ও-failover)
- [৬.৯ Recharge ও balance](#৬৯-recharge-ও-balance)
- [৬.১০ Central API](#৬১০-central-api)
- [৬.১১ Config ও command](#৬১১-config-ও-command)
- [৬.১২ টেস্ট](#৬১২-টেস্ট)
- [৬.১৩ জানা সমস্যা ও সতর্কতা](#৬১৩-জানা-সমস্যা-ও-সতর্কতা)

---

## ৬.১ কোড ফাইল

| অংশ | ফাইল |
|---|---|
| Page | `app/Filament/Pages/SmsSettings.php` |
| Resources | `app/Filament/Resources/{SmsVendors, SmsRechargeRequests, SmsBalanceTransactions, SmsLogs}/` |
| Concern | `app/Filament/Concerns/HasSmsPermission.php` |
| Actions (`app/Actions/Sms/`) | `QueueSmsBatchAction` (একমাত্র entry point), `SendAttendanceSmsAction`, `SendFeeInvoiceSmsAction`, `SendFeePaymentSmsAction`, `SendNoticeSmsAction`, `ResolveNoticeSmsRecipientsAction`, `CreditSmsBalanceAction`, `SubmitSmsRechargeRequestAction`, `SyncSmsResponseCodesAction`, `SyncSmsVendorsAction`, `UpdateSmsRateAction`, `Concerns/BuildsStudentSms` |
| Support (`app/Support/Sms/`) | `SmsGateway` (interface), `BulkSmsBdGateway`, `OutgoingSms`, `SmsBatchResult`, `SmsSendResult`, `SmsSegments`, `SmsWallet`, `BangladeshPhone` |
| Job | `app/Jobs/SendSmsJob.php` |
| Controllers | `CentralSmsCreditController`, `CentralSmsRateController`, `CentralSmsResponseCodesController`, `CentralSmsVendorsController` |
| Requests | `CreditSmsBalanceRequest`, `UpdateSmsRateRequest`, `SyncSmsResponseCodesRequest`, `SyncSmsVendorsRequest` (body HMAC যাচাই) |
| Command | `CreditSmsBalance` (`sms:credit`) |
| Seeders | `SmsVendorSeeder`, `SmsVendorResponseCodeSeeder` |
| Models | `SmsSetting`, `SmsLog`, `SmsVendor`, `SmsVendorResponseCode`, `SmsBalanceTransaction`, `SmsRechargeRequest` |
| Enums | `SmsEvent`, `SmsSource`, `SmsStatus`, `SmsLanguage`, `SmsVendorDriver`, `SmsBalanceTransactionType`, `SmsRechargeRequestStatus` |
| Config | `config/sms.php` |

## ৬.২ Database টেবিল

| টেবিল | নোট |
|---|---|
| `sms_settings` | single row — `is_enabled`, `language`, `balance`, `rate_per_sms`, event toggle ও template (custom template চিহ্নিত) |
| `sms_balance_transactions` | ledger — `type` (recharge / debit / refund), `amount`, `sms_parts`, `balance_after`, `description`, `reference` (credit-এ unique) |
| `sms_logs` | `recipient_name`, `phone`, `event`, `message`, `parts`, `cost`, `status`, `sms_vendor_id`, `response_code`, `error_message`, `sent_at` |
| `sms_vendors` | `name`, `driver`, `sender_id`, API credential, `priority`, `is_active` — central থেকে sync |
| `sms_vendor_response_codes` | driver অনুযায়ী `code`, `is_success`, `message` |
| `sms_recharge_requests` | `amount`, `estimated_sms`, `status`, `delivered_at`, `credited_amount`, `requested_by`, `note`, `completed_at` |

## ৬.৩ Permission

`SmsPermission`: `manage_sms_settings` (SmsSettings, Vendors, Recharge Requests, Balance History), `view_sms_logs` (SmsLogs)। `HasSmsPermission::canAccess()`।

`SmsVendorResource`: create / edit / delete / deleteAny সবসময় `Response::deny('SMS vendor শুধু NextFusion থেকে সেট করা যায়।')` — শুধু list।

## ৬.৪ ডিজাইনের মূল সিদ্ধান্ত

- এক installation = এক স্কুল → স্কুলের SMS balance নিজের DB-তে (`sms_settings.balance`)
- Vendor account platform owner-এর। Vendor-এর **আসল balance কখনো কাউকে দেখানো হয় না** — শুধু bulk batch আটকানোর জন্য ভেতরে তুলনা হয় (`provider_balance_cache_seconds = 60`); balance পড়া না গেলে "can cover" ধরা হয়, যাতে flaky API sending আটকে না দেয়
- Recharge সিস্টেমের **বাইরে**: স্কুল request পাঠায়, credit আসে signed central API বা `sms:credit` দিয়ে
- Rate ও vendor central থেকে সেট হয়; স্কুল admin শুধু on/off, event ও template বদলায়
- চার্জ প্রতি SMS part-এ
- Bulk = all-or-nothing; queue করার সময় debit, ব্যর্থ হলে refund

## ৬.৫ পাঠানোর pipeline

```
Send*SmsAction / ExamNotifications / Notice
        │  OutgoingSms[] (phone, message, event, recipient)
        ▼
QueueSmsBatchAction
  ├─ SMS disabled / event disabled → skip
  ├─ BangladeshPhone::normalize() — অবৈধ নম্বর বাদ
  ├─ de-dup (একই নম্বর + একই লেখা)
  ├─ SmsSegments::count() × rate → মোট cost
  ├─ school balance < cost → কিছুই নয় (SmsBatchResult)
  ├─ কোনো vendor balance cover করে না → কিছুই নয় (shortfall alert, shortfall_alert_minutes)
  └─ সব সফল → SmsWallet debit + SmsLog (pending) + SendSmsJob
        ▼
SendSmsJob → vendor priority অনুযায়ী gateway → response code
  ├─ success → status sent, sent_at
  └─ fail    → status failed, error_message, refund ledger
```

## ৬.৬ Segment ও খরচ

`SmsSegments::isUnicode()` / `count()`:

| Encoding | Single | Multipart per part |
|---|---|---|
| GSM (English) | ১৬০ | ১৫৩ |
| Unicode (বাংলা) | ৭০ | ৬৭ |

`cost = parts × rate_per_sms`।

## ৬.৭ Event ও template

`SmsEvent` — `attendance_present`, `attendance_absent`, `device_entry`, `device_exit`, `fee_invoice_generated`, `fee_payment_received`, `notice`, `exam_subject_marks`, `exam_result`। প্রতিটার `getLabel()`, `description()`, `source()` (`SmsSource`: attendance / fee / notice / exam), `defaultTemplate(SmsLanguage)`, `placeholders()`।

- Template খালি → default
- Language বদলালে শুধু default (অপরিবর্তিত) template নতুন ভাষায় বদলায়; custom template থাকে
- `attendance_absent` manual এবং auto-absent (`MarkAbsentForDateAction`) দুটোতেই
- আজকের তারিখ ছাড়া attendance SMS নয়

## ৬.৮ Vendor ও failover

- `SmsVendorDriver` — এখন শুধু `bulksmsbd` (`BulkSmsBdGateway`, base URL `SMS_BULKSMSBD_BASE_URL`)। নতুন provider = নতুন enum case + `SmsGateway` implement করা class
- `priority` অনুযায়ী চেষ্টা; একটা ব্যর্থ হলে পরেরটা
- `sms_vendor_response_codes`-এর `is_success` ঠিক করে সফল কিনা; `message` ব্যর্থতার কারণ; `sms_logs.response_code`-এ শেষ code

## ৬.৯ Recharge ও balance

- `SubmitSmsRechargeRequestAction` — `config('sms.recharge_request_url')` (`SMS_RECHARGE_REQUEST_URL`)-এ POST; পৌঁছালে `delivered_at`; না পৌঁছালে table-এ "Resend"
- Credit: `CreditSmsBalanceAction` — unique `reference` (double credit আটকায়), ledger `recharge`, pending request থাকলে completed + `credited_amount`
- SmsSettings-এ balance, approx SMS count (`balance / rate`), rate — সব read-only

## ৬.১০ Central API

সব `POST /api/central/*`, `throttle:30,1`, body-HMAC (`CENTRAL_SECRET`, `CentralSignature`, `signature_tolerance_seconds = 300`) Form Request-এ যাচাই।

| Route | Request | কাজ |
|---|---|---|
| `/api/central/sms-credit` | `CreditSmsBalanceRequest` | balance রিচার্জ (unique reference) |
| `/api/central/sms-rate` | `UpdateSmsRateRequest` | প্রতি SMS চার্জ |
| `/api/central/sms-response-codes` | `SyncSmsResponseCodesRequest` | provider response code sync |
| `/api/central/sms-vendors` | `SyncSmsVendorsRequest` | vendor তালিকা sync |

একই central সংযোগ দিয়ে `GET /api/central/student-report` (`auth.central`) এবং মাসিক `SendCentralStudentReport` (১৫ তারিখ) — `app/Actions/Central/`।

## ৬.১১ Config ও command

`config/sms.php`: `recharge_request_url`, `drivers.bulksmsbd.base_url`, `provider_balance_cache_seconds` (৬০), `shortfall_alert_minutes` (৬০)।

`php artisan sms:credit` — হাতে balance credit (`--help` দেখুন)।

## ৬.১২ টেস্ট

`SmsAdminPanelTest`, `SmsSendingTest`, `SmsEventNotificationsTest`, `SmsRateTest`, `SmsRechargeTest`, `SmsResponseCodeSyncTest`, `SmsVendorSyncTest`, `ExamMarksNotificationTest`, `CentralStudentReportTest`।

## ৬.১৩ জানা সমস্যা ও সতর্কতা

1. `SMS_RECHARGE_REQUEST_URL` এখনো খালি রাখা — URL না দেওয়া পর্যন্ত recharge request পৌঁছাবে না (Resend দরকার হবে)।
2. User-facing লেখায় platform-এর নাম ("NextFusion") hardcoded আছে (`SmsVendorResource`, `SmsSettings` helper text) — white-label করতে চাইলে config-এ সরান।
3. শুধু একটা vendor driver।
