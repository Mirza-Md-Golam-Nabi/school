# ১১. User Management ও Authorization — Developer Reference

ব্যবহারকারী নির্দেশিকা: [../../user-guide/features/11-user-management.md](../../user-guide/features/11-user-management.md)

## সূচিপত্র

- [১১.১ কোড ফাইল](#১১১-কোড-ফাইল)
- [১১.২ Role resource](#১১২-role-resource)
- [১১.৩ Authorization প্যাটার্ন](#১১৩-authorization-প্যাটার্ন)
- [১১.৪ Authorization audit — পুরো অ্যাপ](#১১৪-authorization-audit--পুরো-অ্যাপ)
- [১১.৫ নতুন permission যোগ করার নিয়ম](#১১৫-নতুন-permission-যোগ-করার-নিয়ম)
- [১১.৬ টেস্ট](#১১৬-টেস্ট)

---

## ১১.১ কোড ফাইল

| অংশ | ফাইল |
|---|---|
| Resource | `app/Filament/Resources/Roles/` — `RoleResource`, `Schemas/*` (`PermissionRegistry` অনুযায়ী group Section + CheckboxList), `Tables/*`, `Pages/CreateRole`, `EditRole` |
| Registry | `app/Enums/PermissionRegistry.php`, `app/Enums/Permissions/*.php` |
| Traits | `app/Traits/Permissions/HasEntityPermissions.php`, `HasAcademicPermissions.php` |
| Concerns | `HasAttendancePagePermission`, `HasSmsPermission` |
| Models | `App\Models\Role`, `App\Models\Permission` (Spatie extend), `User` (`HasRoles`) |
| Seeders | `RoleSeeder`, `PermissionSeeder`, `ActingAdminRoleSeeder` |
| Action | `SyncActingAdminRolePermissionsAction` |

## ১১.২ Role resource

- Form: `name` (unique) + প্রতিটা registry group-এর জন্য `permissions_{snake_group}` CheckboxList
- `EditRole::mutateFormDataBeforeFill()` existing permission group-এ ভাগ করে; `mutateFormDataBeforeSave()` `permissions_*` key সরায়; `afterSave()` সব merge করে `syncPermissions()` + `logPermissionChanges($before, $after)` (activity log)
- `admin` role save হলে `SyncActingAdminRolePermissionsAction` — `acting_admin` mirror
- Table: `permissions_count`, `users_count`; `super-admin` role delete → `abort_if(403)`
- কোনো ইউজার CRUD resource নেই (`UserPermission`-এর `*_users`, `assign_roles`, `assign_permissions` কোথাও ব্যবহৃত নয়) — role user_type থেকে assign হয়

## ১১.৩ Authorization প্যাটার্ন

| প্যাটার্ন | কোথায় | নোট |
|---|---|---|
| `HasEntityPermissions` | বেশিরভাগ resource | `{view,create,edit,delete,restore,force_delete}_{table}`; Filament `canViewAny()` ইত্যাদি override |
| `HasAcademicPermissions` | শুধু তৈরি, কোথাও সক্রিয় নয় | `GroupResource`-এ comment করা |
| `HasAttendancePagePermission` | attendance page | `canAccess()` |
| `HasSmsPermission` | SMS page / resource | `canAccess()` |
| `get*AuthorizationResponse()` | `SmsVendorResource` | Filament 5-এ action লুকানোর সঠিক উপায় |
| `Gate::before` | `AppServiceProvider` | `super-admin` সব bypass |

Filament 5 নোট: `canCreate()` / `canEdit()` override করলে create/edit action সবসময় লুকায় না — `get{Create,Edit,Delete,...}AuthorizationResponse()` override করুন।

Model Policy একটাও নেই। Policy না থাকলে এবং resource-এ override না থাকলে Filament **সব কিছু allow** করে।

## ১১.৪ Authorization audit — পুরো অ্যাপ

নিচের তালিকা permission ছাড়া একজন `staff` ইউজার দিয়ে যাচাই করা (`canViewAny()` / `canCreate()` = `true`) অথবা code-এ `canAccess()` না থাকা থেকে।

**গুরুত্বপূর্ণ (privilege escalation):**

| Class | ঝুঁকি |
|---|---|
| `RoleResource` | যেকোনো admin-panel ইউজার নিজের role (`staff`)-এ সব permission দিতে পারে |
| `ActingAdminResource` | নিজেকে Acting Super Admin বানাতে পারে |
| `Pages\SchoolSettings` | `view/edit_school_settings` কোথাও চেক হয় না; acting super admin-এর জন্য বাদ দেওয়াটাও তাই অকার্যকর |

**ডেটা পরিবর্তন:**

`SubjectResource`, `GroupResource`, `ClassesResource`, `ExamTypeResource`, `ExamResource`, `ResultRuleResource`, `LeaveTypeResource`, `LeaveApplicationResource` (approve/reject action সহ), `PublicHolidayResource`, `StudentFeeDiscountResource`, `AttendanceDeviceResource`; Pages: `EnterStudentMarks` (admin), `GradeScaleSettings`, `QuestionBankSettings`, `PromoteStudents`, `PromoteStudentsForClass`, `BulkPromoteStudentsForClass`।

**ডেটা প্রকাশ:**

Pages: `SeatPlan`, `StudentIdCards`, `TeacherIdCards`, `StaffIdCards`, `StudentAttendanceReport`। এবং web.php-র সব PDF route শুধু `auth` — student বা teacher-ও URL দিয়ে অন্য class-এর student list, ID card, attendance report, marksheet, tabulation sheet ডাউনলোড করতে পারে (controller-এ কোনো check নেই, যেমন `ClassStudentListPdfController`)।

**Registration:** admin panel-এ public registration চালু, নতুন ইউজার `admin` role পায় ([architecture](../00-architecture.md#০১৮-জানা-সমস্যা-ও-সতর্কতা))।

প্রস্তাবিত সমাধান ক্রম: registration বন্ধ → Role/ActingAdmin/SchoolSettings গেট → PDF controller-এ panel + permission + ownership চেক → বাকি resource-এ `HasEntityPermissions` বা academic permission → Pest টেস্ট (`RolePermissionEnforcementTest` বাড়ানো)।

## ১১.৫ নতুন permission যোগ করার নিয়ম

1. `app/Enums/Permissions/{Module}Permission.php`-এ case যোগ
2. নতুন module হলে `PermissionRegistry`-তে group যোগ (Role form-এ নিজে থেকে দেখাবে)
3. Resource / Page-এ গেট (`HasEntityPermissions` নাম-convention মেলালে নিজে থেকে কাজ করে)
4. `php artisan db:seed --class=PermissionSeeder` (firstOrCreate) → `php artisan db:seed --class=ActingAdminRoleSeeder` → `php artisan permission:cache-reset`
5. `RolePermissionEnforcementTest`-এ টেস্ট — "registers every new permission module in the PermissionRegistry" টেস্টও আছে

## ১১.৬ টেস্ট

`RolePermissionEnforcementTest`, `ActivityLogRoleTest`, `ActingAdminTest`।
