# ২. Attendance — Developer Reference

ব্যবহারকারী নির্দেশিকা: [../../user-guide/features/02-attendance.md](../../user-guide/features/02-attendance.md)

## সূচিপত্র

- [২.১ কোড ফাইল](#২১-কোড-ফাইল)
- [২.২ Database টেবিল](#২২-database-টেবিল)
- [২.৩ Permission](#২৩-permission)
- [২.৪ Manual attendance](#২৪-manual-attendance)
- [২.৫ AttendanceSetting ও late নিয়ম](#২৫-attendancesetting-ও-late-নিয়ম)
- [২.৬ Working days](#২৬-working-days)
- [২.৭ Device pipeline (ZKTeco)](#২৭-device-pipeline-zkteco)
- [২.৮ Device API](#২৮-device-api)
- [২.৯ Enrollment lifecycle](#২৯-enrollment-lifecycle)
- [২.১০ Auto absent](#২১০-auto-absent)
- [২.১১ Exam attendance totals](#২১১-exam-attendance-totals)
- [২.১২ Notification ও SMS](#২১২-notification-ও-sms)
- [২.১৩ Laptop sync agent](#২১৩-laptop-sync-agent)
- [২.১৪ টেস্ট](#২১৪-টেস্ট)
- [২.১৫ জানা সমস্যা ও সতর্কতা](#২১৫-জানা-সমস্যা-ও-সতর্কতা)

---

## ২.১ কোড ফাইল

| অংশ | ফাইল |
|---|---|
| Pages | `app/Filament/Pages/` — `StudentAttendance` (class card), `MarkStudentAttendance` (`?classId=&date=`), `StudentAttendanceDetail` (`?studentId=&from=&to=`), `StudentAttendanceHistory` (`?classId=&from=&to=`), `TeacherAttendance`, `TeacherAttendanceDetail`, `TeacherAttendanceHistory`, `StaffAttendance`, `StaffAttendanceHistory`, `StudentAttendanceRanking`, `ClassAttendanceRanking` (`?classId=`), `ExamAttendanceTotals` (`?examId=`), `LateArrivalsReport`, `ManageAttendanceSettings`, `AttendanceModificationDetails` (`?batch=`) |
| Views | `resources/views/filament/pages/{student-attendance,mark-student-attendance,teacher-attendance,staff-attendance,...}.blade.php`, `filament/shared/exam-attendance-totals.blade.php` |
| Concerns | `HasAttendancePagePermission`, `ManagesClassAttendance` (admin ও teacher marking শেয়ার করে), `ManagesExamAttendanceTotals` |
| Device resource | `app/Filament/Resources/AttendanceDevices/` — `AttendanceDeviceForm`, `AttendanceDevicesTable`, `CreateAttendanceDevice` (token), `RelationManagers/DeviceUsersRelationManager` |
| Actions (`app/Actions/Attendance/`) | `SaveClassAttendanceAction`, `IngestDevicePunchesAction`, `ProcessAttendancePunchesAction`, `MarkAbsentForDateAction`, `EnrollPersonOnDevicesAction`, `AssignDeviceEnrollIdsAction`, `ApplyProfileStatusToEnrollmentsAction`, `BuildDeviceSyncPlanAction`, `ApplyDeviceSyncReportAction`, `ResolveStudentAttendanceTotals`, `SaveExamAttendanceSummariesAction` |
| Report actions | `BuildClassAttendanceReportRows`, `BuildClassAttendanceReportPdfAction`, `BuildEmployeeAttendanceReportData`, `BuildEmployeeAttendanceReportPdfAction`, `BuildTeacherAttendanceSheetRows`, `BuildTeacherAttendanceSheetPdfAction` |
| Controllers | `DevicePunchController`, `DeviceSyncPlanController`, `DeviceSyncReportController`, `ClassAttendanceReportPdfController`, `EmployeeAttendanceReportPdfController`, `TeacherAttendanceSheetPdfController` |
| Middleware | `AuthenticateAttendanceDevice` (`auth.device`) |
| Requests | `StoreDevicePunchesRequest`, `StoreDeviceSyncReportRequest`, `DownloadEmployeeAttendanceReportRequest`, `DownloadTeacherAttendanceSheetRequest` |
| Observers | `AttendanceObserver` (leave log sync), `DeviceEnrollmentObserver` |
| Command | `MarkAbsentAttendance` (hourly) |
| Service | `app/Services/WorkingDaysCalculator.php` |
| Config | `config/attendance.php` |
| Enums | `AttendanceStatus`, `AttendanceSource`, `AttendanceMode`, `AttendanceDeviceDriver`, `DeviceUserRemovalStatus`, `PunchDirection` |
| Models | `Attendance`, `AttendanceSetting`, `AttendanceDevice`, `DeviceUser`, `AttendancePunch`, `AttendanceStatusChange`, `AttendanceNotification`, `StudentAttendanceSummary` |

## ২.২ Database টেবিল

| টেবিল | নোট |
|---|---|
| `attendances` | polymorphic `attendable_type` / `attendable_id` (Student/Teacher/StaffProfile), `date`, `class_id` (student-এর জন্য; employee-র null), `subject_id` (ClassWise mode; Daily-তে null), `status`, `source`, `entry_time`, `exit_time`, `marked_by` |
| `attendance_settings` | single row (`AttendanceSetting::current()`): `attendance_mode`, `late_threshold_minutes` (default ১৫), `entry_time`, `exit_time`, `teacher_late_after`, `count_late_students/teachers/staff` |
| `attendance_status_changes` | backdated পরিবর্তনের log — `batch_id`, `old_status`, `new_status` (AttendanceModificationDetails পেজ) |
| `attendance_devices` | `name`, `serial_number`, `driver`, `is_active`, `api_token_hash`, capacity কলাম, `log_retention_days`, `last_synced_at`, শেষ report |
| `device_users` | `attendance_device_id`, polymorphic `enrollable`, `enroll_id`, `card_number`, `fingerprint_count`, `removal_status`, `removal_due_at`, `removed_at`, `last_seen_on_device_at` |
| `attendance_punches` | raw punch — device, `enroll_id`, `punched_at`, `verify_type`, `state`, processed flag; duplicate-এর জন্য unique index |
| `student_attendance_summaries` | exam অনুযায়ী হাতে লেখা মোট উপস্থিতি ও working days |
| `attendance_notifications`, `late_penalties` | CLAUDE.md-তে তালিকাভুক্ত; late কোনো penalty-তে যুক্ত নয় |

## ২.৩ Permission

`AttendancePermission`: `mark_attendance`, `view_attendance`, `manage_attendance_settings`, `manage_attendance_devices`।

| Page / Resource | Permission |
|---|---|
| `StudentAttendance`, `StudentAttendanceRanking`, `ClassAttendanceRanking`, `LateArrivalsReport`, সব `*Detail` / `*History`, `AttendanceModificationDetails` | `view_attendance` |
| `MarkStudentAttendance`, `TeacherAttendance`, `StaffAttendance`, `ExamAttendanceTotals` | `mark_attendance` |
| `ManageAttendanceSettings` | `manage_attendance_settings` |
| `AttendanceDeviceResource` | — (নিচে দেখুন) |

সব page `HasAttendancePagePermission::canAccess()` ব্যবহার করে। `RolePermissionEnforcementTest` Mark Attendance গেট যাচাই করে।

**সতর্কতা:** `AttendanceDeviceResource`-এ কোনো authorization override নেই — permission ছাড়া staff-এর জন্যও `canViewAny()` `true`। `manage_attendance_devices` permission enum-এ আছে, কিন্তু resource-এ ব্যবহার হয় না। ফলে যেকোনো admin-panel ইউজার device token regenerate করতে, enrollment মুছতে বা removal approve করতে পারে।

## ২.৪ Manual attendance

`SaveClassAttendanceAction::handle($classId, $class, $date, $students, $presentIds, $markedBy, $markedByName)`:

- একটা query-তে existing row (`attendable`, `date`, `class_id`, `subject_id = null`)
- tick → `Present`, না → `Absent`; `countsLateFor()` চালু এবং আগে `Late` থাকলে tick থাকা অবস্থায়ও `Late` রাখে
- `entry_time` শুধু নতুন বা status বদলালে নতুন stamp — unchanged row touch হয় না (activity log noise এড়াতে)
- `!$existing || isDirty()` হলেই save
- নতুন বা status বদলালে → `StudentAttendanceMarkedNotification`
- backdated (আজ নয়) + নতুন / বদল → `attendance_status_changes`-এ batch, admin-দের notification (`AttendanceModificationDetails?batch=`)
- আজকের দিনে নতুন / বদল → guardian SMS (`AttendancePresent` / `AttendanceAbsent`) একটা batch-এ `SendAttendanceSmsAction`
- class teacher ছাড়া অন্য teacher mark করলে admin ও class teacher-কে notification (block করে না)

Student list (`ManagesClassAttendance::loadStudents`): `current_class_id` = class, active ও non-trashed, **অথবা** সেই তারিখে যার attendance row আছে (withTrashed) — পুরনো তারিখ সংশোধনে প্রাক্তন শিক্ষার্থী হারায় না।

Teacher / Staff page: একই প্যাটার্ন inline (`TeacherAttendance::save()`, `StaffAttendance::save()`), `class_id = null`; backdated হলে `admin` ও `super-admin` role-এর ইউজারদের database notification "Backdated Attendance Marked"। Teacher list = `EmploymentStatus::Active`; Staff list = active + সেই তারিখে row আছে এমন।

## ২.৫ AttendanceSetting ও late নিয়ম

```php
lateAfterFor(TeacherProfile::class) = teacher_late_after ?? entry_time + late_threshold_minutes
lateAfterFor(others)                = entry_time + late_threshold_minutes
```

`countsLateFor($type)` — `count_late_*` চালু থাকলে Late `countedPresent()` scope-এ present হিসেবে গোনা হয়। `presentStatusesFor($type)` card/summary-তে ব্যবহার হয়।

Late শুধু report — কোনো leave বা penalty-র সাথে যুক্ত নয়।

## ২.৬ Working days

`WorkingDaysCalculator`: `count()`, `getWorkingDays()`, `isWorkingDay()`। Weekend = School Settings key `weekend_days` (JSON); holiday = `public_holidays` (`start_date..end_date`, `is_recurring`)।

`ResolveStudentAttendanceTotals`: `presentCountsForYear($year, $classId)`, `deviceWorkingDays()`, `workingDaysForYear()` — exam summary থাকলে সর্বশেষ exam-এর হাতে লেখা `working_days` অগ্রাধিকার পায়।

## ২.৭ Device pipeline (ZKTeco)

```
K40 ──LAN──> laptop sync agent ──HTTPS──> POST /api/device/punches
                                             │
                          IngestDevicePunchesAction (attendance_punches, unique → dup drop)
                                             │
                          ProcessAttendancePunchesAction (person-day recompute)
                                             │
                                      attendances (source=device)
```

Device-agnostic রাখা হয়েছে, যাতে ভবিষ্যতে ADMS `/iclock/cdata` endpoint একই pipeline-এ ঢুকতে পারে (`AttendanceDeviceDriver::Adms` enum আছে, endpoint নেই)।

`ProcessAttendancePunchesAction`:

- প্রভাবিত প্রতিটা person-day **সব device-এর সব punch** থেকে নতুন করে হিসাব — idempotent; দেরিতে আসা আগের punch entry time ঠিক করে
- Unmapped enroll ID-র punch unprocessed থাকে; mapping যোগ হলে "Process Pending Punches" বা পরের ingest-এ প্রসেস হয়
- প্রথম punch = entry
- Exit: দিনে check-in ও check-out দুই state থাকলে state বিশ্বাসযোগ্য → entry-র পরের শেষ check-out; সব punch একই state হলে শেষ punch, শুধু যদি entry থেকে `attendance.exit_after_minutes` (২৫) দূরে
- Status: entry > `lateAfterFor()` → `Late`, না হলে `Present`
- **Manual বা Leave row device কখনো overwrite করে না** — শুধু ফাঁকা `exit_time` পূরণ করতে পারে; Device-sourced row recompute হয়
- শুধু আজকের punch notify করে (laptop অনেকদিন বন্ধ থাকার পর প্রথম sync-এ পুরনো punch notify হয় না)
- guardian SMS একটা batch-এ (`DeviceEntry` / `DeviceExit`)

## ২.৮ Device API

সব রাউট `throttle:120,1` + `auth.device` (per-device bearer token)। Token `Str::random(48)`, শুধু hash (`AttendanceDevice::hashToken`) DB-তে; plain token শুধু create বা regenerate-এর পরে একবার persistent notification-এ দেখায়। Inactive device → 401 `{"message":"Unauthenticated device."}`।

| Method | Path | Request | কাজ |
|---|---|---|---|
| POST | `/api/device/punches` | `punches[]` (১–১০০০): `enroll_id` (string ≤৫০), `punched_at` (date), `verify_type`, `state` (int ০–৬৫৫৩৫, nullable) | ingest + process |
| GET | `/api/device/sync-plan` | — | `users` (device-এ যারা থাকবে, grace/approval-এর অপেক্ষায় থাকারাও), `remove` (স্পষ্ট তালিকা — "যারা নেই" নয়), `log_retention_days`। নাম ASCII-তে transliterate (K40 বাংলা দেখায় না), সর্বোচ্চ দৈর্ঘ্যে কাটা |
| POST | `/api/device/sync-report` | user list (card, fingerprint), সম্পন্ন deletion, record count, log cleared | `ApplyDeviceSyncReportAction` — card/fingerprint আপডেট, শুধু নির্ধারিত deletion মানে, unknown user রেকর্ড (touch করে না), capacity "used" server-side গণনা |

Capacity limit admin form-এ দেয় (pyzk `read_sizes()` নির্ভরযোগ্য নয়), "used" আসে report-এর user list থেকে।

## ২.৯ Enrollment lifecycle

`DeviceEnrollmentObserver` (Student / Teacher / StaffProfile):

| Event | কাজ |
|---|---|
| `created` | `attendance.auto_enroll` চালু থাকলে `EnrollPersonOnDevicesAction` — সব active device-এ পরের খালি numeric enroll ID |
| `updated` (status) | `ApplyProfileStatusToEnrollmentsAction` |
| `deleted` (soft) | approval-এর অপেক্ষা |
| `restored` | removal বাতিল / restore |
| `forceDeleted` | graduation-এর মতো schedule (approve করার কেউ নেই) |

`ApplyProfileStatusToEnrollmentsAction`:

- আবার Active → pending/scheduled removal বাতিল; removed হলে একই enroll ID-তে restore
- Graduated / Transferred → `removal_status = queued`, `removal_due_at = now + removal_grace_hours (২৪)`
- Dropped / Inactive student, Resigned / Terminated / Retired employee, soft-delete → `pending_approval`

`device_users` row কখনো hard delete হয় না (`removed_at`) — enroll ID কখনো পুনর্ব্যবহার হয় না, পুরনো punch সঠিক ব্যক্তির থাকে। এক sync-এ সর্বোচ্চ `max_removals_per_sync` (৫০) deletion।

বড় bulk import-এর আগে `ATTENDANCE_AUTO_ENROLL=false`, পরে device-এ "Auto-Assign Enroll IDs" (`AssignDeviceEnrollIdsAction`)।

## ২.১০ Auto absent

`MarkAbsentForDateAction` (command `MarkAbsentAttendance`, hourly, `withoutOverlapping`) — নিচের সবগুলো সত্য না হলে 0 ফেরত দেয়:

1. working day
2. `now >= exit_time + MINUTES_AFTER_EXIT (60)`
3. school end-এর পরে কোনো active device report করেছে
4. সেদিন অন্তত একটা punch আছে

Skip: approved leave, ইতিমধ্যে যেকোনো attendance row আছে, কোনো device-এ enroll নেই। Absent row `source = device` — দেরিতে punch এলে present/late-এ upgrade হয়। ClassWise mode উপেক্ষিত।

## ২.১১ Exam attendance totals

`ManagesExamAttendanceTotals` (admin `ExamAttendanceTotals`, teacher প্যানেলেও):

- class teacher বা admin লিখতে পারে
- class-এর exam (নতুন আগে), active student + যাদের লেখা total আছে
- পাশে daily attendance-এর সংখ্যা (`getDeviceTotals()`)
- `SaveExamAttendanceSummariesAction` → `student_attendance_summaries`; খালি ঘর = device হিসাব
- "ডিভাইসের হিসাবে ফেরাও" (`resetToDeviceCount`) exam-এর সব summary মুছে দেয়
- Marksheet ও attendance ranking summary থাকলে সেটাই দেখায় (`BuildStudentMarksDetailAttendanceSummaryTest`)

## ২.১২ Notification ও SMS

| Trigger | Notification / SMS |
|---|---|
| Manual নতুন / বদল | `StudentAttendanceMarkedNotification` (database + webpush) |
| Device entry / প্রথম exit (আজ) | `StudentDevicePunchNotification` |
| Manual আজ | SMS `attendance_present` / `attendance_absent` |
| Device আজ | SMS `device_entry` / `device_exit` |
| Wrong teacher | Filament database notification → admin + class teacher |
| Backdated | Filament database notification → `admin`, `super-admin` |

SMS শুধু `SmsSetting`-এ event চালু থাকলে যায়, অভিভাবকের নম্বরে। ডিফল্ট বন্ধ।

## ২.১৩ Laptop sync agent

Repo-র **বাইরে** আলাদা Python প্রজেক্ট (pyzk, stdlib urllib)। কমান্ড: `check`, `sync [--dry-run] [--full]`, `run` (live capture + interval-এ sync)। Windows Task Scheduler দিয়ে চলে। গুরুত্বপূর্ণ আচরণ:

- deletion-এর আগে punch upload
- server batch গ্রহণ করলেই cursor এগোয়
- go-live guard `punch_start_date`
- OS file lock — দুবার চালু হয় না
- `max_removals_per_run` guard
- device clock auto-sync
- K40: TCP port 4370; card punch state 4, fingerprint state 1

Agent-এর config-এ device API token লাগে।

## ২.১৪ টেস্ট

`AttendanceDevicePunchApiTest`, `AttendanceDeviceResourceTest`, `AttendanceModificationNotifiesAdminTest`, `DeviceEnrollmentLifecycleTest`, `DeviceSyncApiTest`, `DeviceUserRemovalActionsTest`, `ProcessAttendancePunchesActionTest`, `MarkAbsentForDateActionTest`, `SaveClassAttendanceActionTest`, `StudentAttendanceNotificationTest`, `ClassTeacherAttendanceNotificationTest`, `ClassAttendanceRankingTest`, `StudentAttendanceRankingTest`, `ExamAttendanceTotalsTest`, `LateArrivalsReportTest`, `CountLateArrivalsTest`, `TeacherLateTimeSettingTest`, `StaffAttendanceTest`, `TeacherStaffAttendanceNoSpuriousUpdateTest`, `CollegeSessionAttendanceTest`, `ActivityLogAttendanceTest`, `ActivityLogAttendanceSettingTest`, `BuildClassAttendanceReportRowsTest`, `BuildClassAttendanceReportPdfActionTest`, `EmployeeAttendanceReportTest`, `TeacherAttendanceSheetTest`।

## ২.১৫ জানা সমস্যা ও সতর্কতা

1. `AttendanceDeviceResource`-এ authorization নেই (২.৩)।
2. `AttendanceMode::ClassWise` setting আছে, কিন্তু marking UI সবসময় `subject_id = null` (daily) লেখে — ClassWise কার্যত বাস্তবায়িত নয়। Device ব্যবহার করলে Daily রাখতেই হবে।
3. ADMS driver enum আছে, endpoint নেই।
4. Card number হাতে দেওয়ার স্ক্রিন (USB reader) এখনো নেই — card number device থেকে sync report-এ আসে।
5. Teacher / Staff marking logic `SaveClassAttendanceAction`-এ নয়, page-এ inline এবং প্রায় একই কোড দুবার — পরিবর্তন করলে দুই জায়গায় করতে হবে।
