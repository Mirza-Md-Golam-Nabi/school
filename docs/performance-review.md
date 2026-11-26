# Performance ও Query রিভিউ — ফলাফল

> রিভিউয়ের তারিখ: ২০২৬-১১-২৬ · ব্রাঞ্চ: `dev` (commit `1fd6d4e`)
> কোন সমস্যা ঠিক হয়েছে আর কোনটা বাকি, তা দেখুন: [performance-fix-plan.md](performance-fix-plan.md)

## কীভাবে রিভিউ করা হয়েছে

1. **Static review:** Filament table, widget, form/filter option, Blade view, Action, Livewire page, scheduler ও migration-এর index হাতে পরীক্ষা করা হয়েছে। loop-এর ভিতরে query খুঁজতে grep ব্যবহার করা হয়েছে।
2. **N+1 ধরার জন্য test চালানো:** অস্থায়ীভাবে `Model::preventLazyLoading()` চালু করা হয়েছিল, তবে exception ছুঁড়ে না দিয়ে শুধু log করার জন্য। এভাবে পুরো test suite চালানো হয়েছে (১৪৬৩টি test)। প্রতিটি lazy load কোন ফাইলের কোন লাইন থেকে হচ্ছে, সেটা log-এ লেখা হয়েছে। পরে পরিবর্তনটি সরিয়ে ফেলা হয়েছে।
   - ফলাফল: ১৪৬২টি pass। ১টি fail (`PushNotificationDeliveryTest`, সময় মাপার test)। parallel run ধীর হওয়ায় এটা fail করেছে, performance-এর সাথে সম্পর্ক নেই।
3. **সীমাবদ্ধতা:** ৭৮১টি PHP ফাইলের প্রতিটি লাইন ধরে পড়া হয়নি। যে কোড path-এ test নেই, সেখানে আরও N+1 থাকতে পারে। বড় data দিয়ে load test করা হয়নি (local DB-তে মাত্র ৬৯ জন ছাত্র)।

## সারসংক্ষেপ

সার্বিকভাবে কোড ভালোভাবে optimize করা আছে। নিচে চিহ্নিত জায়গাগুলোতে বাস্তব সমস্যা আছে। এর মধ্যে **widget polling** সরাসরি browser ও server-এ ধারাবাহিক লোড ফেলছে।

---

## 🔴 গুরুত্বপূর্ণ সমস্যা

### R1. ৪টি widget প্রতি ৫ সেকেন্ডে নিজে থেকে reload হয়

Filament-এর `StatsOverviewWidget` ও `ChartWidget` default হিসেবে `CanPoll` trait থেকে `$pollingInterval = '5s'` পায়। কোডের কোথাও এটা বন্ধ করা নেই। তাই পেজ খোলা থাকলেই প্রতি ৫ সেকেন্ডে একটি Livewire request যায় এবং widget-এর সব query আবার চলে। Session ও cache দুটোই `database` driver-এ, তাই প্রতিটি request-এ বাড়তি DB read/write-ও হয়।

| Widget | প্রতি ৫ সেকেন্ডে যে কাজ হয় |
|---|---|
| `Resources/StudentFeeInvoices/Widgets/FeeReconciliationWidget` | সব সময়ের invoice-এর উপর প্রায় ১০টি `SUM` query |
| `Resources/StudentFeeInvoices/Widgets/ClassInvoiceDuesSummaryWidget` | ৩টি query, partial invoice সব memory-তে load হয় |
| `Widgets/IncomeExpenseChartWidget` (admin dashboard) | ৩টি query |
| `Resources/StudentProfiles/Widgets/GroupStudentCountsWidget` | count query |

> সংশোধন: প্রথম রিপোর্টে `TeacherProfileOverview`, `TeacherSubjectsOverview` ও `StudentProfileOverview`-কেও polling-এর তালিকায় ধরা হয়েছিল। পরে দেখা গেছে এগুলো নিজস্ব Blade view ব্যবহার করে, যাতে `wire:poll` নেই, তাই এগুলো poll করে না।

**প্রভাব:** উদাহরণ হিসেবে, ১০ জন অ্যাডমিন ড্যাশবোর্ড বা fee পেজ খোলা রাখলে প্রতি মিনিটে প্রায় ১২০টি অপ্রয়োজনীয় request যায়।
**সমাধান:** প্রতিটিতে `protected ?string $pollingInterval = null;` যোগ করা।

### R2. Notice তালিকায় N+1, আর সব notice-এর বিস্তারিত আগেই DOM-এ রেন্ডার হয়

`resources/views/filament/{student,teacher}/pages/notices.blade.php` এবং দুই panel-এর notice widget-এ, loop-এর ভিতরে প্রতিটি notice-এর জন্য `$this->getNoticeDetail($notice->id)` ডাকা হয়। এটা `InteractsWithNotices::authorizedNotice()`-এ visibility subquery সহ **notice-টা আবার query করে**। ফলে পেজে ১০টি notice থাকলে ১০টি বাড়তি query হয়। কেউ কোনো notice-এ ক্লিক করলেই (`markAsRead`) পুরো তালিকা আবার render হয়।

**সমাধান:** loop-এ আগে থেকেই load করা `$notice` (যা ইতিমধ্যেই visibility দিয়ে filter করা) সরাসরি view-এ পাঠানো। `markAsRead`-এ authorization যেমন আছে তেমনই থাকবে।

### R3. Class card পেজগুলোতে প্রতি class-এ একটি query (N+1), সাথে একটি লুকানো bug

এই ৮টি পেজের `mount()`-এ `->each()`-এর ভিতরে প্রতিটি class-এর জন্য আলাদা `whereHas` query চলে:

- `Resources/StudentFeeInvoices/Pages/ListStudentFeeInvoices`
- `Resources/FeePayments/Pages/ListFeePayments`
- `Resources/AdmitCards/Pages/ListAdmitCards`: সব row PHP-তে এনে গোনা হয়
- `Resources/Marksheets/Pages/ListMarksheets`: সব row PHP-তে এনে গোনা হয়
- `Resources/StudentFeeDiscounts/Pages/ListStudentFeeDiscounts`
- `Teacher/Resources/Exams/Pages/ListExams`
- `Teacher/Resources/FeePayments/Pages/ListFeePayments`
- `Teacher/Resources/StudentFeeInvoices/Pages/ListStudentFeeInvoices`

**লুকানো bug:** এই পেজগুলো ফলাফল `public Collection $classes`-এ রাখে। Livewire-এর `EloquentCollectionSynth` পরের request-এ শুধু ID থেকে model-গুলো **DB থেকে নতুন করে query করে আনে**। তখন `->each()`-এ বসানো `total_invoices`, `pending_count` এর মতো হিসাব করা মান হারিয়ে যায়। যেমন: header action-এর modal খুললেই পরের render-এ card-এর সংখ্যা ফাঁকা বা ০ দেখাতে পারে। এছাড়া প্রতিটি request-এ একটি বাড়তি query হয়।

**সমাধান:** সব class-এর হিসাব একটি `groupBy` query-তে বের করা। ফলাফল public property-তে না রেখে `getViewData()` অথবা `#[Computed]` থেকে দেওয়া।

---

## 🟡 Test চালিয়ে পাওয়া N+1 (lazy loading)

| সংখ্যা | স্থান | সমস্যা |
|---|---|---|
| ২০ | `Actions/BuildCombinedClassMarksheetPdfAction.php:122` | `student.group` eager load নেই। marksheet view-এ প্রতি ছাত্রের জন্য ১টি query হয় |
| ৭+৪ | `Models/Exam.php:139` `displayLabel()` | কিছু caller `examType` ও `class` load না করেই ডাকে |
| ২+২ | `Actions/GenerateOneTimeFeeInvoicesAction.php:45, 115` | `$structure->feeType` ও `->class` lazy load হয় |
| ৪ | `Models/FeePayment.php:62` (deleted event) | bulk delete-এ প্রতিটির জন্য `invoice` আলাদা query |
| ২ | `Models/Attendance.php:203` | activity log label-এর জন্য `attendable` lazy load হয় |
| ৬+২ | `TeacherProfile::user`, `FeePayment::student/receivedBy` | উৎস compiled view বা vendor থেকে, নির্দিষ্ট লাইন পাওয়া যায়নি |

## 🟡 অন্যান্য উন্নতির সুযোগ

1. **একই হিসাব দুইবার চলে:** `PendingAlertsWidget` ও `FinanceOverviewWidget` দুটোই "payable invoice-এর net_amount sum + paid sum" আলাদাভাবে হিসাব করে, প্রতি dashboard load-এ।
2. **`CalculateFeeReconciliationAction`:** প্রায় ১০টি আলাদা `SUM` query চলে। এগুলো একটি conditional aggregate (`SUM(CASE WHEN …)`) query-তে আনা যায়।
3. **Closure ছাড়া `->options(Model::pluck(...))`:** প্রায় ১৫টি জায়গায় আছে, যেমন `FeeStructureForm`, `LateFeeRuleForm`, `StudentFeeDiscountForm` (`FeeDiscount::all()`), `SubjectsRelationManager`, `FeeStructuresTable`, `LateFeeRulesTable`, `StudentFeeDiscountsTable`, `StudentFeeInvoicesTable`, `NoticeForm`। এগুলো form বা table তৈরি হওয়ার মুহূর্তেই query চালায়, এমনকি filter না খুললেও।
4. **`databaseNotifications()`:** তিনটি panel-ই প্রতি ৩০ সেকেন্ডে poll করে।
5. **ছবি না থাকলে avatar আসে `ui-avatars.com` থেকে:** StudentProfilesTable-এ প্রতি row-এ একটি external request যায়।
6. **Blade-এ সরাসরি query:** `student-profiles/infolists/fee-summary.blade.php` ও `subjects-list.blade.php`। একক record-এর পেজ, তাই প্রভাব কম।

## ✅ যা ভালোভাবে করা আছে

- প্রধান table-গুলোতে (`student_fee_invoices`, `attendances`, `student_profiles`, `fee_payments`, `account_transactions`, `student_results`, `teacher_subjects`, `exams`) সঠিক composite index আছে।
- Filament table-এ dotted column (Filament নিজেই eager load করে), `getEloquentQuery()->with(...)`, `withSum`, `withExists`, `withCount` ব্যবহার করা হয়েছে।
- `SchoolSettingStore` ও `ActivityLogLabelCache` request-scoped, তাই settings একবারই load হয়।
- Attendance, marks entry ও teacher widget-গুলো আগে batch query চালায়, পরে memory-তে group করে। `getStudents()` memoize করা।
- ছবি upload-এ ৬০০px-এ resize হয়। PDF bundle queue job-এ তৈরি হয়। Scheduler-এ `withoutOverlapping()` আছে।
- Pagination-এ "all" option নেই (Filament 5-এর default `[5, 10, 25, 50]`)।

## 📋 ভবিষ্যতে যেসব বিষয়ে লক্ষ্য রাখা দরকার

1. **`Model::preventLazyLoading(! app()->isProduction())`** স্থায়ীভাবে চালু রাখলে নতুন N+1 development ও test-এই ধরা পড়বে।
2. **Production DB:** SQLite-এ একসাথে একাধিক write হলে পুরো database lock হয়, যেমন একসাথে কয়েকজন শিক্ষকের attendance বা marks entry। MySQL/MariaDB নেওয়া ভালো। SQLite রাখলে অন্তত WAL mode নিশ্চিত করুন।
3. **Cache, session ও queue তিনটিই `database` driver-এ।** Redis পাওয়া গেলে এগুলো সেখানে নিলে DB-র চাপ কমবে।
4. **Deploy-এ চালানো দরকার:** `php artisan optimize`, `php artisan filament:optimize`। OPcache চালু রাখুন।
5. Filament panel-এ `->spa()` চালু করলে পেজ বদলানোর সময় asset আবার load হয় না।
6. নতুন `StatsOverviewWidget` বা `ChartWidget` বানালে সবসময় `$pollingInterval` নিজে ঠিক করে দিন।
7. Livewire-এর public property-তে Eloquent collection রাখবেন না। এতে payload বড় হয় এবং প্রতি request-এ DB থেকে আবার query হয়।
8. `->options()`-এ query থাকলে সবসময় `fn () =>` দিয়ে মুড়ে দিন। বড় তালিকা হলে `->searchable()` + `getSearchResultsUsing()` ব্যবহার করুন।
9. loop-এর ভিতরে `Model::where()->…` লিখবেন না। আগে `whereIn` দিয়ে একবারে এনে `keyBy`/`groupBy` করুন।
