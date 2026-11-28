# ১৪. তিন প্যানেলের কাঠামো — Developer Reference

ব্যবহারকারী নির্দেশিকা: [Admin](../user-guide/panels/admin-panel.md) · [Teacher](../user-guide/panels/teacher-panel.md) · [Student](../user-guide/panels/student-panel.md)

Provider, auth ও middleware: [00-architecture.md](00-architecture.md#০৫-তিনটি-filament-প্যানেল)।

## সূচিপত্র

- [১৪.১ Admin panel](#১৪১-admin-panel)
- [১৪.২ Teacher panel](#১৪২-teacher-panel)
- [১৪.৩ Student panel](#১৪৩-student-panel)
- [১৪.৪ শেয়ার করা কোড](#১৪৪-শেয়ার-করা-কোড)
- [১৪.৫ টেস্ট](#১৪৫-টেস্ট)
- [১৪.৬ জানা সমস্যা ও সতর্কতা](#১৪৬-জানা-সমস্যা-ও-সতর্কতা)

---

## ১৪.১ Admin panel

Dashboard: `app/Filament/Admin/Pages/Dashboard.php` — columns `default 2, md 2, lg 4`; widgets `app/Filament/Widgets/` (sort অনুযায়ী):

| Sort | Widget | দেখায় |
|---|---|---|
| 1 | `StudentAttendanceOverviewWidget` | Total Students, Present, Absent (আজ) |
| 2 | `TeacherAttendanceOverviewWidget` | শিক্ষকের আজকের উপস্থিতি |
| 3 | `StaffAttendanceOverviewWidget` | স্টাফের আজকের উপস্থিতি |
| 4 | `AcademicStatsWidget` | Total Exams, Published, Pending |
| 5 | `StudentAttendanceRankingWidget` | Top Attendance |
| 6 | `AcademicQuickActionsWidget` | Create Exam, Result Publish, Marksheet, Admit Card, Manage Class |
| 7 | `PendingAlertsWidget` | exam শেষ কিন্তু unpublished; pending leave; due fee invoice সংখ্যা |
| 8 | `FinanceOverviewWidget` | Net Balance, Total Due Fee, Defaulted Fee (`view_account_transactions` লাগে) |
| 9 | `IncomeExpenseChartWidget` | Income / Expense chart (`view_account_transactions` লাগে) |

Navigation ম্যাপ: [00-architecture.md ০.৮](00-architecture.md#০৮-admin-প্যানেলের-navigation-ম্যাপ)।

## ১৪.২ Teacher panel

### Pages (`app/Filament/Teacher/Pages/`)

| Page | Group / Sort | Access |
|---|---|---|
| `Dashboard` | — | teacher |
| `Notices` | — / 0 | — |
| `MyIdCard` | — / 1 | ID card design submit থাকলে (`canAccess`) |
| `MySubjects` | Academics / 1 | — |
| `StudentAttendance` | Attendance / 1 | `view_attendance` |
| `MarkStudentAttendance` | লুকানো | `mark_attendance` (`ManagesClassAttendance` শেয়ার) |
| `StudentAttendanceDetail`, `StudentAttendanceHistory` | লুকানো | `view_attendance` |
| `MyAttendance` | Attendance / 2 | — |
| `ExamAttendanceTotals` | Attendance / 3 | attendance permission **এবং** কোনো class-এর class teacher; লেখা শুধু নিজের class (`canManageClass`) |
| `MyLeave` | Leave / 4 | — |
| `LeaveApplications` | Leave / 5 | — (Apply for Leave action) |
| `MySalary` | Salary / 5 | — |
| `MyProfile` | লুকানো (dashboard profile card থেকে) | — |
| `EnterStudentMarks` | লুকানো | `isAssignedToTeach(class, subject, exam session)` নইলে 403 |

### Resources (`app/Filament/Teacher/Resources/`)

| Resource | Nav | Scope | Permission |
|---|---|---|---|
| `Exams` (`ListExams`, `ManageClassExams`, `ViewExam`) | Academics | `ScopesToTaughtClasses` — যে class-এ কখনো কোনো subject পড়িয়েছে | — |
| `StudentFeeInvoices` | Fee → Fee Invoices | `ScopesToClassTeacherStudents` — class teacher যে class-এর, সেই class-এর student | `HasEntityPermissions` (`*_student_fee_invoices`) |
| `FeePayments` | Fee → Collect Fee | একই | `HasEntityPermissions` (`*_fee_payments`) |

Exam view-এ admin-এর `SubjectConfigsRelationManager` ব্যবহার হয়, কিন্তু `isAdminPanel()` false হওয়ায় create / delete / restore লুকানো, আর `Enter Marks` শুধু assigned subject-এ; `Send Marks` / `Send Result` দেখায় না (`canSend()` admin panel চায়)।

### Widgets (`app/Filament/Teacher/Widgets/`)

`TeacherProfileOverview`, `TeacherQuickActionsWidget` (Take Attendance, Fee Collection, Exam & Marks — resource `canViewAny()` অনুযায়ী), `TeacherSubjectsOverview` (My Teaching Load), `ClassAttendanceTodayWidget` (class teacher), `TeacherMarksPendingWidget`, `TeacherUpcomingExamsWidget`, `TeacherAttendanceOverview`, `TeacherLeaveOverview`, `TeacherSalaryOverview`, `TeacherFeeDuesWidget` (`StudentFeeInvoiceResource::canViewAny()`), `TeacherNoticesOverview`।

### Teacher role-এর permission

`teacher` role ডিফল্টে কোনো permission পায় না। Teacher panel-এ Student Attendance / Mark / Attendance Totals / Fee দেখাতে Super Admin-কে `teacher` role-এ অন্তত `view_attendance`, `mark_attendance`, `view/create_student_fee_invoices`, `view/create_fee_payments` দিতে হবে।

## ১৪.৩ Student panel

### Pages / Resources

| Class | Nav | নোট |
|---|---|---|
| `Dashboard` | — | |
| `Notices` | — / 0 | `InteractsWithNotices` (read tracking) |
| `Resources/ExamResults` | Results → Exam Results | শুধু student-এর class-এর `is_published = true` exam; `ViewExamResult` — পুরো class-এর merit list (rank, section rank, roll, name, section, marks, GPA) + নিজের marks detail |
| `Resources/FeeInvoices` | Fees → Fee Invoices | নিজের invoice; view modal (amount breakdown, payments); `AcademicHistoryFees` page |
| `AcademicHistory` | History | `student_class_history` card; `AcademicHistoryClass` (লুকানো) — সেই বছরের attendance, fee paid, Main exam result |
| `MyAttendanceRanking` | লুকানো | class-এর attendance ranking |
| `SchoolQuestions` | — / 89 | Question Bank সংযুক্ত হলে; signed iframe |
| `MyProfile` | লুকানো (dashboard profile card থেকে) | Basic / Guardian / Fee Info tabs |
| `Auth/EditProfile` | user menu | avatar, password, PIN; নাম ও Student ID read-only |

### Widgets (`app/Filament/Student/Widgets/`)

`StudentProfileOverview`, `StudentTodayAttendance`, `StudentAttendanceRankOverview` (Present Days, Your Rank, Top), `StudentLatestResultWidget` (Class Position), `StudentFeeDueOverview` (Total Due, discount), `StudentUpcomingExamWidget` (subject-wise schedule), `StudentNoticesOverview`।

Middleware: `EnsurePasswordIsChanged` — `must_change_password` থাকলে profile page ছাড়া সব redirect।

## ১৪.৪ শেয়ার করা কোড

| কী | কোথায় |
|---|---|
| Marks entry view | `filament.shared.enter-student-marks` (admin + teacher) |
| Exam attendance totals | `ManagesExamAttendanceTotals` + `filament.shared.exam-attendance-totals` |
| Class attendance marking | `ManagesClassAttendance` + `SaveClassAttendanceAction` |
| Fee form + scanner | `ScansStudentIdCard`; teacher form admin form-এর কপি |
| Notices | `Teacher/Concerns/InteractsWithNotices`, `Student/Concerns/InteractsWithNotices` |
| PWA / push / locale | সব প্যানেলে render hook ([architecture](00-architecture.md#০১৫-pwa-ও-web-push)) |

## ১৪.৫ টেস্ট

`AdminDashboardWidgetsTest`, `StudentAttendanceOverviewWidgetTest`, `TeacherAttendanceOverviewWidgetTest`, `StaffAttendanceOverviewWidgetTest`, `IncomeExpenseOverviewWidgetTest`, `TeacherDashboardWidgetsTest`, `TeacherSubjectsOverviewTest`, `TeacherSalaryOverviewTest`, `TeacherAttendanceOverviewTest`, `TeacherExamClassListTest`, `TeacherEnterMarksRestrictionTest`, `TeacherFeeCollectionTest`, `TeacherFeeInvoiceCrudTest`, `TeacherMyProfileTest`, `TeacherNoticesTest`, `MySalaryPageTest`, `StudentDashboardWidgetsTest`, `StudentAttendanceDashboardTest`, `StudentMyAttendanceRankingTest`, `StudentMyProfileTest`, `StudentNoticesTest`, `AcademicHistoryTest`, `ViewExamResultMarksDetailTest`, `PanelLogoutRedirectTest`, `PwaInstallTest`।

## ১৪.৬ জানা সমস্যা ও সতর্কতা

1. **Teacher self-attendance:** `Teacher\Pages\MyAttendance::markAs()` শিক্ষককে আজকের জন্য নিজেকে Present / Absent / Leave দিতে দেয় (`updateOrCreate`, `source = manual`)। কোনো permission বা approval নেই, আর device-এর তৈরি row-ও manual দিয়ে overwrite হয় (তারপর device আর সেটা বদলাতে পারে না)। স্কুল device ব্যবহার করলে এটা উপস্থিতির নির্ভরযোগ্যতা নষ্ট করে।
2. ~~Admin dashboard widget-এ `canView()` নেই~~ — ঠিক করা হয়েছে: `FinanceOverviewWidget` ও `IncomeExpenseChartWidget` শুধু `view_account_transactions` থাকলে দেখায়; `PendingAlertsWidget`-এর প্রতিটা alert শুধু তার লিংকের পেজ (`Exam`, `LeaveApplication`, `StudentFeeInvoice`) দেখার অনুমতি থাকলে দেখায় (`DashboardWidgetPermissionTest`)।
3. Teacher `StudentAttendance` সব active class দেখায় (শুধু নিজের নয়); wrong-teacher notification দিয়ে নজরদারি।
4. Teacher Exams resource-এ permission নেই, শুধু taught-class scope।
5. Student exam result পেজে পুরো class-এর নাম ও নম্বর দেখায় — privacy নীতির বিষয়, ইচ্ছাকৃত হলে নথিভুক্ত রাখুন।
