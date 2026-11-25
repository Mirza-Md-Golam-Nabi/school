# কলেজ সেশন (জুলাই–জুন) পরিকল্পনা

> অবস্থা: **ধাপ ১, ২ ও ৩ সম্পন্ন।** পরের কাজ শুরু করতে বলুন: "docs/college-session-plan.md অনুযায়ী ধাপ ৪ শুরু করো"।

## সমস্যা

স্কুলের সব ক্লাসের সেশন জানুয়ারি–ডিসেম্বর, কিন্তু কলেজের (ক্লাস ১১-১২, `ClassLevel::College`) সেশন জুলাই থেকে শুরু হয়।
আগে পুরো সিস্টেমে "চলতি সেশন" মানে ছিল `now()->year` (প্রায় ৪০ জায়গায়)। ফলে জানুয়ারি–জুনে কলেজের
শিক্ষক-assignment, ছাত্র-তালিকা, পরীক্ষা, ফি — সব "ভুল বছরে" পড়ে যেত।

## মূল নীতি

1. **`session_year` কলাম অপরিবর্তিত** — কোনো migration নেই। অর্থ: সেশন **যে বছরে শুরু**।
   - স্কুল: `2026` = জানু ২০২৬ – ডিসে ২০২৬
   - কলেজ: `2026` = জুলাই ২০২৬ – জুন ২০২৭, দেখাবে **"2026-27"**
2. "চলতি সেশন" একটাই জায়গা থেকে আসবে: `App\Support\AcademicSession`।
3. শুধু `College` লেভেলের শুরুর মাস আলাদা (School Settings → `college_session_start_month`, ডিফল্ট জুলাই); বাকি সব লেভেল জানুয়ারি।
4. ফি ইনভয়েস / বেতন / ছুটি / নোটিশ ক্যালেন্ডার মাস-বছরেই থাকবে; শুধু কোন সেশনের fee structure লাগবে সেটা সেশন ধরে খোঁজা হবে।

---

## ধাপ ১ — ভিত্তি ✅

- School Settings-এ `college_session_start_month` (Select, ডিফল্ট ৭)।
- `App\Support\AcademicSession`:
  - `startMonthFor(Classes|ClassLevel|null)` — কলেজ হলে সেটিং থেকে, নইলে ১
  - `yearForDate(date, class)` — তারিখটা কোন সেশনে পড়ে
  - `currentYearFor(class)` — আজকের তারিখে চলতি সেশন
  - `periodFor(year, class)` — সেশনের শুরু ও শেষ তারিখ
  - `label(year, class)` — `"2026"` বা `"2026-27"`
  - `optionsFor(class, from)` — ড্রপডাউন অপশন (লেবেলসহ)
- `Classes::isCollege()`, `Classes::currentSessionYear()`, `Classes::sessionLabel()`।
- Trait `App\Traits\HasAcademicSession` → `inCurrentSession()` scope (StudentProfile-এ class কলাম `current_class_id`):
  `(কলেজ ক্লাস AND session_year = কলেজের চলতি সেশন) OR (অন্য ক্লাস AND session_year = স্কুলের চলতি সেশন)`।
  বসানো হয়েছে `TeacherSubject`, `StudentProfile`, `Exam`, `FeeStructure`, `StudentClassHistory`-এ।
- Unit/Feature টেস্ট।

## ধাপ ২ — "চলতি সেশন" ডিফল্ট বদলানো ✅

বাস্তবায়নের সিদ্ধান্ত:
- পরীক্ষা তৈরিতে একসাথে কয়েকটা ক্লাস বাছাই করা যায় — জানুয়ারি–জুনে কলেজ আর স্কুলের ক্লাস একসাথে বাছাই করলে validation error (সেশন আলাদা); জুলাই–ডিসেম্বরে মেশানো যায়।
- Teacher-এর My Subjects-এ প্রথম অপশন "Current Session" (প্রতি ক্লাস নিজের সেশন), কার্ডে "Session 2026-27"।
- Fee structure তালিকার সেশন ফিল্টার: সব ক্লাসের পেজে জানুয়ারি–জুনে স্কুল ও কলেজ দুই চলতি সেশনই ডিফল্টে থাকে।
- ফি ইনভয়েসের ফর্মের "Year" ক্যালেন্ডার বছর — বদলানো হয়নি।
- অব্যবহৃত `sessionYear()` helper সরানো হয়েছে।

**Admin**
- `TeacherSubjectsRelationManager` — ডিফল্ট `$ownerRecord->currentSessionYear()`, অপশন/কলামে লেবেল
- `StudentProfileForm`, `ExamForm` — class বাছাই করলে session ডিফল্ট বদলাবে (`live()` + `afterStateUpdated`)
- `ImportStudentsFromSpreadsheetAction` — session না থাকলে ক্লাস অনুযায়ী
- `ListStudentProfiles`, `StudentsByClass`, `GroupStudentCountsWidget`, `AcademicStatsWidget`, `PendingAlertsWidget`, `ClassStudentListPdfController` — `inCurrentSession()` বা ক্লাসের নিজের সেশন
- Fee structure ফর্ম/টেবিল/পেজ, `StudentFeeDiscountForm`, `StudentFeeInvoiceForm` — ক্লাস অনুযায়ী ডিফল্ট

**Teacher**
- `MySubjects` — প্রথম অপশন **"চলতি সেশন"** (scope), তারপর পুরনো বছর
- `TeacherSubjectsOverview`, `TeacherMarksPendingWidget`, `TeacherUpcomingExamsWidget` — `inCurrentSession()`

**Student**
- `StudentUpcomingExamWidget` ইত্যাদি — ছাত্রের ক্লাসের `currentSessionYear()`

## ধাপ ৩ — ফি ✅

বাস্তবায়নের সিদ্ধান্ত:
- One-time ফি আগে থেকেই structure-এর `session_year` ধরে চলে, আর ইনভয়েসের `year`-এ সেশন বছরই রাখে — বদলানো হয়নি। জেনারেট মডালের "Year" ঘরে helper text দিয়ে বোঝানো হয়েছে (মাসিক = ক্যালেন্ডার বছর, one-time = সেশন বছর)।

- `GenerateMonthlyFeeInvoicesAction` — প্রতি ক্লাসে `AcademicSession::yearForDate(Carbon::create($year, $month, 1), $class)` দিয়ে fee structure ও discount খোঁজা
- ইনভয়েসের `month`/`year` ক্যালেন্ডারই থাকবে (unique key অপরিবর্তিত)
- One-time ফি ইনভয়েসের ডিফল্ট সেশন ক্লাস অনুযায়ী

## ধাপ ৪ — প্রমোশন

- `PromoteStudentsAction`-এর `year → year+1` কলেজেও ঠিক — অপরিবর্তিত
- `PromoteStudents` / `PromoteStudentsForClass` — "থেকে" সেশনের ডিফল্ট ক্লাস অনুযায়ী; কলেজ কার্ডে "2026-27 → 2027-28"

## ধাপ ৫ — উপস্থিতি

- `ResolveStudentAttendanceTotals` — `inYear($year)` → সেশনের সময়সীমা (`periodFor()`)
- ClassAttendanceRanking, StudentAttendanceRanking, MyAttendanceRanking, StudentAttendanceRankOverview, StudentAttendanceRankingWidget, StudentAttendanceReport — প্রতি ক্লাস নিজের সেশনের সময়সীমা
- `WorkingDaysCalculator` — recurring ছুটি সময়সীমার প্রতিটা বছরে বসানো

## ধাপ ৬ — সেশন দেখানো

- সব টেবিল/infolist/AcademicHistory/মার্কশিট ও অ্যাডমিট কার্ড PDF/ID card-এ `AcademicSession::label()`
- `sessionYear()` helper লেবেলসহ অপশন

## ধাপ ৭ — টেস্ট (প্রতি ধাপের সাথে)

- কলেজ শিক্ষক ফেব্রুয়ারি ২০২৭-এ My Subjects-এ 2026 সেশন দেখে
- ছাত্র তালিকায় একাদশের ছাত্র থাকে
- মার্চ ২০২৭-এর কলেজ ইনভয়েস 2026-এর fee structure নেয়
- উপস্থিতি জুলাই–জুনের মধ্যে
- স্কুল ক্লাসে আচরণ অপরিবর্তিত (regression)
