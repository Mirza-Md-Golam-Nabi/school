# Performance সমাধান — কাজের তালিকা

> রিভিউয়ের বিস্তারিত: [performance-review.md](performance-review.md)
> পরের কাজ শুরু করতে বলুন: "docs/performance-fix-plan.md অনুযায়ী পরের ধাপের কাজ শুরু করো"।

## ধাপ ১: সম্পন্ন ✅ (২০২৬-১১-২৬)

প্রভাব সবচেয়ে বেশি, ঝুঁকি কম, আর সমস্যার জায়গা নির্দিষ্ট।

| # | কাজ | কী করা হয়েছে | Test |
|---|---|---|---|
| R1 | Widget-এর ৫ সেকেন্ডের auto-polling বন্ধ | `FeeReconciliationWidget`, `ClassInvoiceDuesSummaryWidget`, `IncomeExpenseChartWidget`, `GroupStudentCountsWidget`-এ `$pollingInterval = null`। (Teacher/Student-এর ৩টি widget নিজস্ব view ব্যবহার করে, সেগুলো আগেও poll করত না।) | `AdminDashboardWidgetsTest`: "does not auto-poll…" |
| R2 | Notice তালিকার N+1 | ৪টি জায়গায় (Student/Teacher-এর notices পেজ ও widget) loop-এ আগে থেকেই load করা `$notice` সরাসরি `@include` করা হয়েছে। query-তে `with('createdBy')` যোগ হয়েছে। অব্যবহৃত `getNoticeDetail()` মুছে ফেলা হয়েছে। `markAsRead`-এর authorization আগের মতোই আছে। | `StudentNoticesTest`, `TeacherNoticesTest`: "renders every notice body without a query per notice"। আগের কোডে ৬টি notice-এ ১৫টি query হতো, এখন ৫টি। |
| R3 | ৮টি class card পেজের N+1 ও হিসাব হারানোর bug | নতুন Action: `AttachClassFeeInvoiceStatsAction`, `AttachClassFeePaymentStatsAction`, `AttachClassDocumentStatsAction` (admit card + marksheet), `AttachClassFeeDiscountStatsAction`, সাথে shared trait `Actions/Concerns/GroupsRowsByStudentClass`। সব class-এর হিসাব একটি `groupBy` query-তে বের হয়। Teacher `ListExams`-এ সরাসরি grouped query। হিসাব এখন `public Collection $classes`-এর বদলে `getViewData()` থেকে আসে, তাই পরের Livewire request-এ আর হারায় না। | `ClassCardStatsActionsTest`: প্রতি class-এর সঠিক হিসাব, ১টি query, soft-deleted ছাত্র বাদ, `$refresh`-এর পরেও হিসাব থাকে |
| N1 | Marksheet PDF-এ `student.group` eager load | `BuildCombinedClassMarksheetPdfAction`-এর `with()`-এ যোগ করা হয়েছে | বিদ্যমান `BuildCombinedClassMarksheetPdfActionTest` |

**যাচাই:** পুরো test suite চালানো হয়েছে, ১৪৭৪টি test-এর সবগুলো pass (নতুন ১১টি test সহ)।

**লক্ষ্য করুন:** class card পেজগুলোতে এখন প্রতিটি Livewire request-এ (যেমন header action-এর modal খোলা) ২টি query চলে (classes + একটি grouped query)। আগে এখানে ১টি query চলত, কিন্তু card-এর হিসাব হারিয়ে যেত। এটা ইচ্ছাকৃত।

## ধাপ ২: পরে করলেও চলবে (code)

| # | কাজ | কেন পরে | আনুমানিক কাজ |
|---|---|---|---|
| L1 | `GenerateOneTimeFeeInvoicesAction`-এর caller-এ `feeType` ও `class` eager load | মাসে কয়েকবার চলে, প্রতিবার মাত্র ২টি query | ছোট |
| L2 | `Exam::displayLabel()`-এর যেসব caller eager load ছাড়া ডাকে, সেগুলো খুঁজে ঠিক করা | প্রতি জায়গায় ১-২টি query | ছোট |
| L3 | `FeePayment` deleted event ও `Attendance` activity label-এ bulk ক্ষেত্রে `load()` | শুধু bulk delete-এ প্রভাব পড়ে | ছোট |
| L4 | প্রায় ১৫টি `->options(Model::pluck(...))` জায়গায় `fn () =>` closure দেওয়া | ছোট table, প্রতিটি query দ্রুত | ছোট, অনেক ফাইল |
| L5 | `CalculateFeeReconciliationAction`-এর প্রায় ১০টি `SUM` একটি conditional aggregate query-তে আনা | R1-এর পর polling না থাকায় এখন শুধু পেজ load-এ চলে | মাঝারি, হিসাব মেলাতে test জরুরি |
| L6 | `PendingAlertsWidget` ও `FinanceOverviewWidget`-এর একই due হিসাব শেয়ার করা | dashboard load-এ ২-৩টি বাড়তি query | ছোট |
| L7 | `Model::preventLazyLoading(! app()->isProduction())` স্থায়ীভাবে চালু করা | L1–L3 ও compiled view-এর অজানা ২-৩টি জায়গা আগে ঠিক না করলে local-এ exception আসবে | ছোট, L1–L3-এর পরে |
| L8 | Panel-গুলোর `databaseNotifications()` polling ৩০ থেকে ৬০ সেকেন্ড করা | সিদ্ধান্ত লাগবে: নোটিফিকেশন কত দ্রুত দেখাতে হবে | এক লাইন |
| L9 | StudentProfilesTable-এ `ui-avatars.com`-এর বদলে local avatar | external dependency, ডিজাইনের সিদ্ধান্ত লাগবে | ছোট |
| L10 | `fee-summary.blade.php` ও `subjects-list.blade.php`-এর query PHP-তে সরানো | একক record-এর পেজ, প্রভাব কম | ছোট |

## ধাপ ৩: Infrastructure / deploy (সিদ্ধান্ত লাগবে, code নয়)

| # | কাজ |
|---|---|
| I1 | Production-এ SQLite-এর বদলে MySQL/MariaDB। SQLite থাকলে WAL mode নিশ্চিত করা |
| I2 | Cache, session ও queue-এর জন্য Redis (বর্তমানে সব `database`-এ) |
| I3 | Deploy script-এ `php artisan optimize` + `php artisan filament:optimize`, OPcache চালু |
| I4 | Filament panel-এ `->spa()` চালু করে দেখা (JS ও Alpine-এর আচরণ test করা দরকার) |
