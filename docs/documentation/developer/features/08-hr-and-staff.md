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

`HrPermission`: leave types CRUD; leave applications `view`, `create`, `approve`, `reject`; public holidays CRUD; acting admins CRUD; `view_school_settings`, `edit_school_settings`।

**সতর্কতা:** `LeaveTypeResource`, `LeaveApplicationResource`, `ActingAdminResource`, `PublicHolidayResource` — চারটাতেই কোনো authorization override নেই (permission ছাড়া staff-এর জন্য `canViewAny()` / `canCreate()` `true`)। বিশেষ করে `ActingAdminResource`: **যেকোনো admin-panel ইউজার নিজেকে Acting Super Admin বানাতে পারে** — privilege escalation। approve/reject action-এ permission চেক আছে কিনা আলাদা করে যাচাই করুন; resource-স্তরে নেই।

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
- Device processing `AttendanceStatus::Leave` row overwrite করে না

## ৮.৭ Acting admin

- `ActingAdminObserver::syncActingRoles($user)` — created / updated / deleted-এ: active, `from_date <= today`, `to_date` null বা `>= today` রেকর্ডগুলোর level অনুযায়ী role assign / remove
- `super_admin_acting`: `ActingAdminRoleSeeder` — `edit_school_settings` ছাড়া সব permission
- `acting_admin`: `SyncActingAdminRolePermissionsAction` — `admin` role-এর permission mirror (admin role edit করার পরেও sync হওয়া উচিত — `EditRole` দেখুন)
- `User::canAccessPanel('admin')` এই দুই role-কে admin panel-এ ঢুকতে দেয়; `Gate::before` bypass পায় না
- `DeactivateExpiredActingAdminsJob` — `to_date < today` ও active → per-model `update(['is_active' => false])` (observer fire করতে)
- Eligible user: `ActingAdmin::activeUser()` — active teacher বা staff profile আছে

## ৮.৮ Public holiday

`WorkingDaysCalculator::resolveHolidayDates()` — single ও range; `is_recurring` হলে প্রতি বছর একই মাস-দিন। Weekend আলাদা — School Settings `weekend_days`।

## ৮.৯ টেস্ট

`LeaveApplicationTest`, `LeaveApplicationEditRestrictionTest`, `LeaveTypeAssignmentTest`, `ActingAdminTest`, `DeactivateExpiredActingAdminsJobTest`, `ActivityLogLeaveHolidayActingAdminTest`, `ActivityLogTeacherLeaveApplicationTest`।

## ৮.১০ জানা সমস্যা ও সতর্কতা

1. চারটা HR resource-এ authorization নেই; `ActingAdminResource` দিয়ে privilege escalation সম্ভব (৮.৩)।
2. **ভবিষ্যৎ `from_date`-এর acting admin কখনো নিজে থেকে চালু হয় না।** Role শুধু model event-এ sync হয়, আর daily job শুধু মেয়াদ শেষ হলে বন্ধ করে। Daily job-এ `from_date = today` রেকর্ডগুলোর জন্যও `syncActingRoles` চালানো দরকার।
3. Approve হলে `attendances`-এ `AttendanceStatus::Leave` row তৈরি হয় না — শুধু `leave_attendance_logs`। ফলে attendance পেজে ছুটির দিন "Not marked" বা হাতে দেওয়া Absent দেখাতে পারে; `Leave` status কোড কোথাও লেখে না।
4. `allowed_days_per_year` apply বা approve-এ enforce হয় না — শুধু teacher-এর MyLeave-এ দেখানো।
5. `Cancelled` status-এর জন্য কোনো UI action নেই।
6. Approve / reject-এ আবেদনকারীকে notification যায় না।
