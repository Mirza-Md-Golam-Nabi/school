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
| `HasEntityPermissions` | বেশিরভাগ resource | `{view,create,edit,delete,restore,force_delete}_{table}`; সব `get*AuthorizationResponse()` (viewAny, view, create, edit, delete, deleteAny, restore(Any), forceDelete(Any)) override করে, আর `can*()` সেগুলো থেকেই পড়ে — তাই Edit/Delete/bulk Delete বাটন permission না থাকলে লুকায় (`EntityPermissionActionsTest`)। নিজস্ব নিয়মের জন্য resource-এ `entityPermissionResponse($action)` ব্যবহার করুন (যেমন `RoleResource`) |
| `HasAcademicPermissions` | Academic Structure-এর ৬টা resource | `HasEntityPermissions` ব্যবহার করে, শুধু permission নাম `{action}_academic` |
| `HasAttendancePagePermission` | attendance page | `canAccess()` |
| `HasSmsPermission` | SMS page / resource | `canAccess()` |
| `get*AuthorizationResponse()` | `SmsVendorResource` | Filament 5-এ action লুকানোর সঠিক উপায় |
| `Gate::before` | `AppServiceProvider` | `super-admin` সব bypass |

Filament 5 নোট: action ও page `get{Create,Edit,Delete,...}AuthorizationResponse()` দিয়ে authorize হয়; শুধু `canCreate()` / `canEdit()` override করলে বাটন লুকায় না। আগে `HasEntityPermissions` শুধু `can*()` override করত, ফলে শুধু `view_*` থাকা ইউজারও Edit বাটন দেখত ও bulk Delete চালাতে পারত — এখন ঠিক করা হয়েছে।

Model Policy একটাও নেই। Policy না থাকলে এবং resource-এ override না থাকলে Filament **সব কিছু allow** করে।

## ১১.৪ Authorization audit — পুরো অ্যাপ

নিচের তালিকা permission ছাড়া একজন `staff` ইউজার দিয়ে যাচাই করা (`canViewAny()` / `canCreate()` = `true`) অথবা code-এ `canAccess()` না থাকা থেকে।

**গুরুত্বপূর্ণ (privilege escalation) — ঠিক করা হয়েছে:**

| Class | আগের ঝুঁকি | এখন |
|---|---|---|
| `RoleResource` | যেকোনো admin-panel ইউজার নিজের role (`staff`)-এ সব permission দিতে পারত | প্রতিটা কাজে `*_roles` permission; Super Admin role ও নিজের role শুধু Super Admin বদলাতে পারে |
| `ActingAdminResource` | নিজেকে Acting Super Admin বানাতে পারত | প্রতিটা কাজে `*_acting_admins`; Acting Super Admin level শুধু Super Admin দেয়; নিজেকে বা নিজের assignment বদলানো যায় না |
| `SchoolSettings`, `GradeScaleSettings`, `QuestionBankSettings` | `view/edit_school_settings` কোথাও চেক হতো না | `HasSchoolSettingsPermission` — view ছাড়া 403, edit ছাড়া read-only ও save 403 |

**ডেটা পরিবর্তন:** ঠিক করা হয়েছে — Academic resource, admin marks entry, HR resource, `StudentFeeDiscountResource`, `AttendanceDeviceResource` ও তিনটা Promotion page এখন permission চায়।

**ডেটা প্রকাশ:** ঠিক করা হয়েছে — `SeatPlan`, `StudentIdCards`, `TeacherIdCards`, `StaffIdCards`, `StudentAttendanceReport` page ও web.php-র সব PDF route এখন সংশ্লিষ্ট permission চায় ([Document Management ৭.৩](07-document-management.md))।

**Registration:** ঠিক করা হয়েছে — admin panel-এ public registration বন্ধ, `/admin/register` 404 ([architecture](../00-architecture.md#০১৮-জানা-সমস্যা-ও-সতর্কতা))।

বাকি কাজ: Admin dashboard widget-এর `canView()` (panels ১৪.৬), teacher-এর fee slip শুধু নিজের class-এ সীমিত করা।

## ১১.৫ নতুন permission যোগ করার নিয়ম

1. `app/Enums/Permissions/{Module}Permission.php`-এ case যোগ
2. নতুন module হলে `PermissionRegistry`-তে group যোগ (Role form-এ নিজে থেকে দেখাবে)
3. Resource / Page-এ গেট (`HasEntityPermissions` নাম-convention মেলালে নিজে থেকে কাজ করে)
4. `php artisan db:seed --class=PermissionSeeder` (firstOrCreate) → `php artisan db:seed --class=ActingAdminRoleSeeder` → `php artisan permission:cache-reset`
5. `RolePermissionEnforcementTest`-এ টেস্ট — "registers every new permission module in the PermissionRegistry" টেস্টও আছে

## ১১.৬ টেস্ট

`RolePermissionEnforcementTest`, `AdminAccessControlTest`, `EntityPermissionActionsTest`, `AcademicPermissionTest`, `HrPermissionTest`, `RemainingPagePermissionTest`, `PdfRouteAuthorizationTest`, `ActivityLogRoleTest`, `ActingAdminTest`।
