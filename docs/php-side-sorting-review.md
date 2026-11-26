# ডেটা ফেচের পর PHP-তে সর্টিং — কোড রিভিউ তালিকা

রিভিউ তারিখ: 2026-11-26 · ব্রাঞ্চ: `dev`

এই ফাইলে সেই সব জায়গা আছে যেখানে ডাটাবেস থেকে ডেটা (`->get()`, relationship, cache) আনার **পরে** Collection/array-তে
`sortBy()`, `sortByDesc()`, `sort()`, `sortDesc()` ইত্যাদি দিয়ে সর্ট করা হয়েছে।
সংশোধনের সময় শুধু নাম্বার বললেই হবে (যেমন: "#২৬ ঠিক করো")।

> যেসব সর্টিং ডাটাবেস লেভেলে সরানো সম্ভব ছিল (আগের #১–২৫ ও #৩৩), সেগুলো DB-তে সরিয়ে এই তালিকা থেকে বাদ দেওয়া হয়েছে।
> নাম্বারগুলো আগের মতোই রাখা হয়েছে, যাতে আগের রেফারেন্স না বদলায়।

## বাকি — হিসাব করা মান দিয়ে সর্ট (DB-তে সরানো কঠিন)

এগুলোতে সর্ট হচ্ছে DB থেকে আনা ডেটা থেকে **হিসাব করে বের করা মান** দিয়ে (present count, GPA, percentage, accessor ইত্যাদি),
তাই সরাসরি `orderBy` দিয়ে করা যায় না।

| # | ফাইল:লাইন | কী সর্ট হচ্ছে | মন্তব্য |
|---|---|---|---|
| 26 | [ClassAttendanceRanking.php:67](../app/Filament/Pages/ClassAttendanceRanking.php#L67) | Student — `present_count` ↓ | count আসে `ResolveStudentAttendanceTotals` থেকে (device + summary মিলিয়ে), DB-তে এক কুয়েরিতে আনা কঠিন |
| 27 | [MyAttendanceRanking.php:55](../app/Filament/Student/Pages/MyAttendanceRanking.php#L55) | Student — `present_count` ↓ | #২৬-এর মতো |
| 28 | [StudentAttendanceRankOverview.php:56](../app/Filament/Student/Widgets/StudentAttendanceRankOverview.php#L56) | Student — `present_count` ↓ | #২৬-এর মতো |
| 29 | [StudentAttendanceRanking.php:42](../app/Filament/Pages/StudentAttendanceRanking.php#L42) | পুরো স্কুলের present count ↓, তারপর `take($limit)` | সব student-এর count এনে top N নেওয়া হচ্ছে |
| 30 | [StudentAttendanceRanking.php:70](../app/Filament/Pages/StudentAttendanceRanking.php#L70) | প্রতি class-এর present count ↓, `take(3)` | প্রতি class-এ আলাদা resolver কল (loop) |
| 31 | [StudentAttendanceRankingWidget.php:34](../app/Filament/Widgets/StudentAttendanceRankingWidget.php#L34) | পুরো স্কুলের present count ↓, `take(2)` | #২৯-এর মতো |
| 32 | [AcademicHistoryClass.php:78](../app/Filament/Student/Pages/AcademicHistoryClass.php#L78) | Classmate — present count ↓ | #২৬-এর মতো |
| 34 | [GradeScale.php:100](../app/Models/GradeScale.php#L100) | Grade scale — `min_mark` ↑, `first()` | যেকোনো ক্রমের collection parameter হিসেবে আসতে পারে; ছোট collection, প্রভাব নগণ্য |
| 35 | [GradeScale.php:112](../app/Models/GradeScale.php#L112) | Grade scale — `grade_point` ↓ | ছোট cached collection; প্রভাব নগণ্য |
| 36 | [CalculateExamRankings.php:327](../app/Actions/CalculateExamRankings.php#L327) | Student — GPA ↓, তারপর total marks ↓ | হিসাব করা GPA দিয়ে ranking — PHP-তেই থাকা উচিত |
| 37 | [CalculateWeightedResults.php:210](../app/Actions/CalculateWeightedResults.php#L210) | Exam part — `percentage` ↓ (best-N) | হিসাব করা percentage — PHP-তেই ঠিক |
| 38 | [BuildStudentMarksDetail.php:158](../app/Actions/BuildStudentMarksDetail.php#L158) | Marks row — `position_group` | তৈরি করা row (merge সহ) — PHP-তেই ঠিক |
| 39 | [BuildExamResultReport.php:110](../app/Actions/BuildExamResultReport.php#L110) | Failed list — failed subject count (`sortKeys`) | হিসাব করা মান |
| 40 | [BuildExamResultReport.php:176](../app/Actions/BuildExamResultReport.php#L176) | Student row — class rank, তারপর roll | row তৈরি হয় হিসাব থেকে (merit ranking + result মিলিয়ে) |
| 41 | [BuildExamResultReport.php:265](../app/Actions/BuildExamResultReport.php#L265) | Passing grade — `grade_point` ↓ | ছোট grade scale collection |
| 42 | [subjects-list.blade.php:18](../resources/views/filament/resources/student-profiles/infolists/subjects-list.blade.php#L18) | Compulsory subject — `display_name` (accessor) | accessor (locale অনুযায়ী নাম) — DB-তে সরানো যায় না |
| 43 | [subjects-list.blade.php:36](../resources/views/filament/resources/student-profiles/infolists/subjects-list.blade.php#L36) | Group + optional subject — `display_name` | #৪২-এর মতো |

---

## তালিকার বাইরে রাখা হয়েছে (DB ডেটা নয়)

এগুলোতে `sort` আছে কিন্তু ডাটাবেস থেকে আনা ডেটা নয়, তাই নাম্বার দেওয়া হয়নি:

- [CollectPromotionFeesAction.php](../app/Actions/CollectPromotionFeesAction.php) — ইনপুট month array-র `->sort()`
- [FeeStructuresTable.php](../app/Filament/Resources/FeeStructures/Tables/FeeStructuresTable.php) — হিসাব করা session year array (`rsort`)
- [QuestionBankConnection.php:125](../app/Support/QuestionBankConnection.php#L125) — navigation item-এর `->sort(90)` (ডেটা সর্ট নয়)
- [MergesTwoSubjects.php:86](../app/Traits/MergesTwoSubjects.php#L86) — enum value তুলনার জন্য sort
