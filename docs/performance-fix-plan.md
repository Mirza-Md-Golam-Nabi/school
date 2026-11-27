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

## ধাপ ২: code-এর ছোট কাজ (২০২৬-১১-২৬)

| # | কাজ | অবস্থা | কী করা হয়েছে | Test |
|---|---|---|---|---|
| L1 | One-time fee invoice তৈরিতে `feeType` ও `class` eager load | ✅ | `generateWithoutSms()`-এ `loadMissing(['feeType', 'class'])`। সব class-এর জন্য চালালে `with('class')`, আর আগে থেকে load করা fee type `setRelation` দিয়ে দেওয়া হয়। ৩টি class-এ lookup ৭টি থেকে ১টি। | `GenerateOneTimeFeeInvoicesForAllClassesActionTest`: "does not look up the fee type or class again…" |
| L2 | `Exam::displayLabel()`-এর caller | ✅ যাচাই, পরিবর্তন লাগেনি | সব caller (merit ranking, admit card, marksheet, question bank, result notification) একটি exam-এর জন্য একবার ডাকে। Question bank-এ আগেই eager load আছে। কোনো loop-এ N+1 নেই। | — |
| L3 | Attendance activity log-এর নামের জন্য প্রতি row-এ query | ✅ | Teacher ও Staff attendance পেজ, auto-absent (`MarkAbsentForDateAction`) আর device punch (`ProcessAttendancePunchesAction`)-এ আগে থেকে load করা ব্যক্তিকে `setRelation('attendable', …)` দিয়ে দেওয়া হয়, আর `user` eager load হয়। আগে প্রতি ব্যক্তির জন্য ২টি বাড়তি query হতো। `FeePayment`-এর deleted event বদলানো হয়নি: প্রতিটি payment-এ এমনিতেই ৫-৬টি দরকারি query হয়, `invoice` load তার মাত্র একটি, আর এটা শুধু bulk delete-এ ঘটে। | `MarkAbsentForDateActionTest`, `ProcessAttendancePunchesActionTest`, `StaffAttendanceTest`: "…without loading them one by one" (নতুন helper `singleRowLookupsDuring()`, `tests/Pest.php`-এ) |
| L4 | Dropdown-এর `->options(Model::…)` closure-এ | ✅ | ১৩টি ফাইলে ১৯টি জায়গায় `fn () =>` দেওয়া হয়েছে। এখন query শুধু তখনই চলে, যখন field বা filter আসলে render হয়। | বিদ্যমান resource test |
| L5 | `CalculateFeeReconciliationAction` | ✅ | ৯টি query থেকে ২টি: invoice-এর যোগফল status অনুযায়ী `groupBy`, আর পরিশোধ invoice-এর status-এর সাথে `joinSub` করে। | `FeeInvoiceDefaultTest`: "reconciles a single year with two queries" (বছর filter, waiver, আংশিক পরিশোধ) + পুরনো reconciliation test |
| L6 | Dashboard-এর due হিসাব শেয়ার | ✅ | নতুন `CalculateFeeDuesAction`, যেখানে সংখ্যা, বকেয়া আর default হওয়া অংশ একটি conditional aggregate-এ আসে। `PendingAlertsWidget` ও `FinanceOverviewWidget` দুটোই এটা ব্যবহার করে। dashboard-এ ৬টি query থেকে ৪টি। | `FeeInvoiceDefaultTest`: "calculates the dashboard dues with two queries" |
| L7 | `Model::preventLazyLoading()` স্থায়ীভাবে | ✅ | `AppServiceProvider`-এ production ছাড়া অন্য সব জায়গায় চালু, তাই local আর test-এ কোনো lazy load হলে সাথে সাথে exception আসে। চালু করার পর পুরো suite-এ নতুন ৫টি N+1 ধরা পড়েছিল, সবগুলো ঠিক করা হয়েছে: exam type ধরে admit card ও marksheet তৈরি (`examType`/`class`), leave application table (`applicant.user`), salary structure table (`profileable.user`)। | বিদ্যমান test (lazy load হলে fail করে) |
| L8 | `databaseNotifications()` polling ৩০ থেকে ৬০ সেকেন্ড | ⏸ সিদ্ধান্ত লাগবে | নোটিফিকেশন কত দ্রুত দেখাতে হবে, সেটা আপনার সিদ্ধান্ত। | — |
| L9 | `ui-avatars.com`-এর বদলে local avatar | ⏸ সিদ্ধান্ত লাগবে | ডিজাইনের সিদ্ধান্ত লাগবে। | — |
| L10 | Profile পেজের fee summary ও subject list-এর query Blade থেকে PHP-তে | ✅ | নতুন `BuildStudentFeeSummaryAction` ও `BuildStudentSubjectListAction`, `ViewEntry::viewData()` দিয়ে view-এ পাঠানো হয়। বকেয়ার হিসাব এখন প্রতিটি invoice-এর সব payment load না করে `withSum` দিয়ে হয়। | `FeeInvoiceDefaultTest`: "summarises a student's fees…", `ViewStudentProfileSubjectsTabTest` |

**যাচাই:** lazy loading বন্ধ রেখে পুরো test suite চালানো হয়েছে, ১৪৯০টি test-এর সবগুলো pass।

**বাকি:** শুধু L8 আর L9। দুটোর জন্যই আপনার সিদ্ধান্ত লাগবে।

**খেয়াল রাখুন (L7-এর পর):** local-এ এমন কোনো পেজ, যেটা test-এ cover হয়নি, সেখানে যদি "Attempted to lazy load … but lazy loading is disabled" error আসে, তার মানে ওই জায়গায় N+1 আছে। Error-কে বন্ধ করবেন না। query-তে `with()` যোগ করুন, অথবা আগে থেকে load করা model `setRelation()` দিয়ে দিন। Production-এ এই চেক বন্ধ থাকে, তাই সেখানে কোনো ব্যবহারকারী এই error দেখবেন না।

## ধাপ ৩: Infrastructure / deploy (সিদ্ধান্ত লাগবে, code নয়)

| # | কাজ |
|---|---|
| I1 | Production-এ SQLite-এর বদলে MySQL/MariaDB। SQLite থাকলে WAL mode নিশ্চিত করা |
| I2 | Cache, session ও queue-এর জন্য Redis (বর্তমানে সব `database`-এ) |
| I3 | Deploy script-এ `php artisan optimize` + `php artisan filament:optimize`, OPcache চালু |
| I4 | Filament panel-এ `->spa()` চালু করে দেখা (JS ও Alpine-এর আচরণ test করা দরকার) |
