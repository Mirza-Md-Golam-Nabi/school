# ৮. HR & Staff Management — Developer Reference

ব্যবহারকারী নির্দেশিকা: [../../user-guide/features/08-hr-and-staff.md](../../user-guide/features/08-hr-and-staff.md)

## সূচিপত্র

- [৮.১ কোড ফাইল](#৮১-কোড-ফাইল)
- [৮.২ Database টেবিল](#৮২-database-টেবিল)
- [৮.৩ Permission](#৮৩-permission)
- [৮.৪ Leave type ও assignment](#৮৪-leave-type-ও-assignment)
- [৮.৫ Leave application lifecycle](#৮৫-leave-application-lifecycle)
- [৮.৬ Leave ও attendance](#৮৬-leave-ও-attendance)
- [৮.৭ Acting admin](#৮৭-acting-admin)
- [৮.৮ Public holiday](#৮৮-public-holiday)
- [৮.৯ টেস্ট](#৮৯-টেস্ট)
- [৮.১০ জানা সমস্যা ও সতর্কতা](#৮১০-জানা-সমস্যা-ও-সতর্কতা)

---

## ৮.১ কোড ফাইল

| অংশ | ফাইল |
|---|---|
| Leave types | `app/Filament/Resources/LeaveTypes/` + `RelationManagers/` (assignment: Assign / Unassign) |
| Leave applications | `app/Filament/Resources/LeaveApplications/` — `Schemas/LeaveApplicationForm` (working days auto), table actions `approve` / `reject` |
| Acting admins | `app/Filament/Resources/ActingAdmins/` |
| Public holidays | `app/Filament/Resources/PublicHolidays/` |
| Actions | `app/Actions/Leave/ApproveLeaveApplicationAction`, `RejectLeaveApplicationAction`, `SyncActingAdminRolePermissionsAction` |
| Observers | `AttendanceObserver` (leave log sync), `ActingAdminObserver` |
| Job | `DeactivateExpiredActingAdminsJob` (দৈনিক ০০:১০) |
| Command | `CheckLeaveExcess` (`hr:check-leave-excess`, দৈনিক ০৮:০০) |
| Seeder | `ActingAdminRoleSeeder` |
| Service | `WorkingDaysCalculator` |
| Teacher panel | `Teacher/Pages/MyLeave`, `Teacher/Pages/LeaveApplications`, `Teacher/Widgets/TeacherLeaveOverview` |
| Models | `LeaveType`, `LeaveTypeAssignment`, `LeaveApplication`, `LeaveAttendanceLog`, `LeaveExcessLog`, `ActingAdmin`, `PublicHoliday` |
| Enums | `LeaveApplicability`, `LeaveApplicationStatus`, `LeaveAttendanceStatus`, `ActingAdminLevel`, `PublicHolidayType` |

## ৮.২ Database টেবিল

| টেবিল | নোট |
|---|---|
| `leave_types` | `name`, `allowed_days_per_year`, `applicable_gender` (male / female / all), `is_active` |
| `leave_type_assignments` | polymorphic `assignable` (Teacher / StaffProfile), `assigned_by`, `assigned_at` |
| `leave_applications` | polymorphic `applicant`, `leave_type_id`, `from_date`, `to_date`, `total_days`, `reason`, `status`, `applied_by`, `actioned_by`, `action_remarks`, `actioned_at` |
| `leave_attendance_logs` | `leave_application_id`, `date` (unique pair), `status` (on_leave / joined / absent), `is_within_approved_range`, `remarks` |
| `leave_excess_logs` | ছুটি শেষে টানা অনুপস্থিতি |
| `acting_admins` | `user_id`, `level` (`super_admin_acting` / `acting_admin`), `from_date`, `to_date` (nullable), `is_active`, `assigned_by`, `remarks` |
| `public_holidays` | `name`, `type` (single / range), `start_date`, `end_date`, `is_recurring`, `description` |

## ৮.৩ Permission

`HrPermission`: leave types CRUD; leave applications `view`, `create`, `edit`, `delete`, `approve`, `reject`; public holidays CRUD; acting admins CRUD; `view_school_settings`, `edit_school_settings`। (`edit_leave_applications` ও `delete_leave_applications` নতুন — production-এ `php artisan db:seed --class=PermissionSeeder` ও `permission:cache-reset` চালাতে হবে।)

**ঠিক করা হয়েছে:** `LeaveTypeResource`, `LeaveApplicationResource`, `PublicHolidayResource` এখন `HasEntityPermissions` ব্যবহার করে (`*_leave_types`, `*_leave_applications`, `*_public_holidays`)। Leave application edit-এ permission ছাড়াও আবেদনটা approved না হওয়া লাগে। Table-এর `approve` / `reject` বাটন `LeaveApplicationResource::canDecide()` দিয়ে দেখায় — আবেদন pending, ইউজারের `approve_` / `reject_leave_applications` আছে, আর আবেদনকারী নিজে নন (Super Admin ছাড়া) (`HrPermissionTest`)।

`ActingAdminResource` ঠিক করা হয়েছে: প্রতিটা কাজে `*_acting_admins` permission লাগে; Acting Super Admin level শুধু Super Admin দিতে বা বদলাতে পারে; Super Admin ছাড়া কেউ নিজেকে বেছে নিতে বা নিজের assignment বদলাতে পারে না (`AdminAccessControlTest`)।

## ৮.৪ Leave type ও assignment

- Application form-এ শুধু আবেদনকারীর assigned leave type (`helperText`)
- `applicable_gender` assignment-এর সময় enforce হয় কিনা — `assignableOptions()` দেখুন; validation-এ নেই বলে ধরে নিন

## ৮.৫ Leave application lifecycle

```
Pending ──approve──> Approved (leave_attendance_logs on_leave per working day)
   │
   └──reject──> Rejected (reason)
Cancelled — enum-এ আছে
```

- `total_days` = `WorkingDaysCalculator::count(from, to)` (weekend + public holiday বাদ)
- `ApproveLeaveApplicationAction` (transaction): status, `actioned_by`, `action_remarks`, `actioned_at`; working day প্রতি `LeaveAttendanceLog` upsert (`on_leave`, `is_within_approved_range = true`)
- Approved application edit বন্ধ (table action hidden, edit page forbidden — `LeaveApplicationEditRestrictionTest`)
- `MyLeave` (teacher): `remaining = max(0, allowed_days_per_year − taken)`

## ৮.৬ Leave ও attendance

- `AttendanceObserver` — attendance তৈরি/আপডেট হলে leave log sync: ছুটির দিনে present/late হলে `joined` (Joined Early)
- `CheckLeaveExcess` — to_date পার হওয়া approved leave-এর পরের দিন থেকে টানা absent দিন গোনে, প্রথম present/late-এ থামে → `leave_excess_logs` (`absent` / Absent (Excess))
- `MarkAbsentForDateAction` approved leave-এ থাকা ব্যক্তিকে absent করে না
- `RecordLeaveAttendanceAction` (`attendance:record-leave`, hourly; গতকাল ও আজ) — দিন শেষ হলে (`exit_time` + `MarkAbsentForDateAction::MINUTES_AFTER_EXIT`) approved leave-এর working day-তে `attendances`-এ `Leave` row লেখে, `source = device`। আগেভাগে লেখে না, তাই ছুটির মধ্যে কাজে ফিরলে সেদিন Present থাকে আর observer log-কে `joined` করে। আগে থেকে row থাকলে ছোঁয় না — ব্যতিক্রম auto Absent (device-sourced), সেটা Leave হয়
- Approve-এর সময় `handleApplication()` — দেরিতে অনুমোদিত ছুটির ইতিমধ্যে শেষ হওয়া দিনগুলো পূরণ করে
- Device processing শুধু manual row (হাতে দেওয়া Leave-সহ) overwrite করে না; system-এর লেখা Leave row punch পেলে Present / Late হয়

## ৮.৭ Acting admin

- `SyncActingAdminRolesAction::handle($user)` (`ActingAdminObserver` created / updated / deleted-এ চালায়): active, `from_date <= today`, `to_date` null বা `>= today` রেকর্ডগুলোর level অনুযায়ী role assign / remove
- `super_admin_acting`: `ActingAdminRoleSeeder` — `edit_school_settings` ছাড়া সব permission
- `acting_admin`: `SyncActingAdminRolePermissionsAction` — `admin` role-এর permission mirror (admin role edit করার পরেও sync হওয়া উচিত — `EditRole` দেখুন)
- `User::canAccessPanel('admin')` এই দুই role-কে admin panel-এ ঢুকতে দেয়; `Gate::before` bypass পায় না
- `DeactivateExpiredActingAdminsJob` (রোজ 00:10) — `to_date < today` ও active → per-model `update(['is_active' => false])` (observer fire করতে); তারপর active ও `from_date <= today` রেকর্ড থাকা প্রতিটা user-এর জন্য `SyncActingAdminRolesAction` — ভবিষ্যৎ `from_date`-এর দায়িত্ব সেই দিন নিজে থেকে চালু হয়
- Eligible user: `ActingAdmin::activeUser()` — active teacher বা staff profile আছে

## ৮.৮ Public holiday

`WorkingDaysCalculator::resolveHolidayDates()` — single ও range; `is_recurring` হলে প্রতি বছর একই মাস-দিন। Weekend আলাদা — School Settings `weekend_days`।

## ৮.৯ টেস্ট

`LeaveApplicationTest`, `LeaveApplicationEditRestrictionTest`, `LeaveTypeAssignmentTest`, `ActingAdminTest`, `DeactivateExpiredActingAdminsJobTest`, `ActivityLogLeaveHolidayActingAdminTest`, `ActivityLogTeacherLeaveApplicationTest`, `LeaveAttendanceRecordingTest`।

## ৮.১০ জানা সমস্যা ও সতর্কতা

1. ~~HR resource-এ authorization নেই~~ — ঠিক করা হয়েছে (৮.৩); `ActingAdminResource`-এর privilege escalation-ও ঠিক করা হয়েছে।
2. ~~ভবিষ্যৎ `from_date`-এর acting admin নিজে থেকে চালু হয় না~~ — ঠিক করা হয়েছে (৮.৭, daily job)।
3. ~~Approve হলে `attendances`-এ `Leave` row তৈরি হয় না~~ — ঠিক করা হয়েছে (৮.৬, `LeaveAttendanceRecordingTest`)। Approved ছুটি পরে মুছে ফেললে আগে লেখা Leave row থেকে যায়।
4. `allowed_days_per_year` apply বা approve-এ enforce হয় না — শুধু teacher-এর MyLeave-এ দেখানো।
5. `Cancelled` status-এর জন্য কোনো UI action নেই।
6. Approve / reject-এ আবেদনকারীকে notification যায় না।
