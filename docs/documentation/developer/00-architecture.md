# ০. আর্কিটেকচার ও ভিত্তি

## সূচিপত্র

- [০.১ Tech stack](#০১-tech-stack)
- [০.২ লোকাল সেটআপ](#০২-লোকাল-সেটআপ)
- [০.৩ Environment variable](#০৩-environment-variable)
- [০.৪ ফোল্ডার কাঠামো](#০৪-ফোল্ডার-কাঠামো)
- [০.৫ তিনটি Filament প্যানেল](#০৫-তিনটি-filament-প্যানেল)
- [০.৬ অথেন্টিকেশন](#০৬-অথেন্টিকেশন)
- [০.৭ User type, Role ও Permission](#০৭-user-type-role-ও-permission)
- [০.৮ Admin প্যানেলের Navigation ম্যাপ](#০৮-admin-প্যানেলের-navigation-ম্যাপ)
- [০.৯ Scheduler (Cron)](#০৯-scheduler-cron)
- [০.১০ Queue ও Job](#০১০-queue-ও-job)
- [০.১১ Web রাউট](#০১১-web-রাউট)
- [০.১২ API রাউট](#০১২-api-রাউট)
- [০.১৩ Observer, Listener ও Event](#০১৩-observer-listener-ও-event)
- [০.১৪ Localization](#০১৪-localization)
- [০.১৫ PWA ও Web Push](#০১৫-pwa-ও-web-push)
- [০.১৬ কোডিং নিয়ম](#০১৬-কোডিং-নিয়ম)
- [০.১৭ টেস্ট](#০১৭-টেস্ট)
- [০.১৮ জানা সমস্যা ও সতর্কতা](#০১৮-জানা-সমস্যা-ও-সতর্কতা)

---

## ০.১ Tech stack

| অংশ | প্যাকেজ / ভার্সন |
|---|---|
| ভাষা | PHP 8.3 |
| Framework | Laravel 13 |
| Admin UI | Filament 5 (Livewire 4, Alpine, Tailwind) |
| Role & Permission | spatie/laravel-permission 7 |
| Activity log | spatie/laravel-activitylog 4 |
| PDF | mpdf/mpdf 8 + mpdf/qrcode (QR কোড) |
| Excel import/export | phpoffice/phpspreadsheet 5 |
| Web push | laravel-notification-channels/webpush 12 |
| টেস্ট | Pest 4 (PHPUnit 12) |
| কোড স্টাইল | Laravel Pint |
| Queue | database driver (`QUEUE_CONNECTION=database`) |
| Timezone | `Asia/Dhaka` |

## ০.২ লোকাল সেটআপ

```bash
composer run setup          # install, .env, key, migrate, npm build
php artisan db:seed         # roles, permissions, acting-admin role, users, document settings, SMS vendors
composer run dev            # serve + queue:listen + vite একসাথে
```

`DatabaseSeeder` যে ক্রমে চলে:

1. `RoleSeeder` — `UserType` enum-এর প্রতিটা case থেকে role (`super-admin`, `admin`, `teacher`, `student`, `staff`)
2. `PermissionSeeder` — `PermissionRegistry`-র সব permission enum
3. `ActingAdminRoleSeeder` — `super_admin_acting` role (school settings edit ছাড়া সব permission) এবং `acting_admin` role (admin role-এর mirror)
4. `UserSeeder` — non-production-এ প্রতিটা user type-এর জন্য `{type}@school.com` / `password`; production-এ শুধু `super_admin@school.com`
5. `DocumentSettingsSeeder`
6. `SmsVendorSeeder`

**মনে রাখুন:** seeder যাচাই করতে `php artisan db:seed` ব্যবহার করুন, `migrate:fresh` নয়।

লোকাল environment-এ প্রতিটা প্যানেলের লগইন ফর্ম seed করা ইউজারের তথ্য দিয়ে আগে থেকে পূরণ থাকে (`Login::mount()`-এ `app()->isLocal()` চেক)।

## ০.৩ Environment variable

| Key | কাজ |
|---|---|
| `APP_NAME`, `SCHOOL_NAME` | অ্যাপ ও স্কুলের নাম (`config('app.school_name')`) |
| `APP_TIMEZONE` | `Asia/Dhaka` |
| `APP_LOCALE` | ডিফল্ট `en`; ইউজার session-এ `bn` বেছে নিতে পারে |
| `QUEUE_CONNECTION` | `database` — PDF bundle, SMS, notice publish, ইমেইল যাচাই queue-তে যায় |
| `VAPID_SUBJECT` (+ webpush key) | Web push |
| `PUSH_NOTIFICATION_RETRY_INTERVAL_MINUTES` | ডিফল্ট ১০ — অস্বীকৃত push কত মিনিট পর পর আবার পাঠানো হবে |
| `PUSH_NOTIFICATION_MAX_ATTEMPTS` | ডিফল্ট ৫ |
| `ATTENDANCE_AUTO_ENROLL` | ডিফল্ট `true` — নতুন প্রোফাইল তৈরি হলেই সব active ডিভাইসে enroll ID |
| `ATTENDANCE_REMOVAL_GRACE_HOURS` | ডিফল্ট ২৪ — স্ট্যাটাস বদলালে ডিভাইস থেকে ইউজার মুছতে কত ঘণ্টা অপেক্ষা |
| `ATTENDANCE_MAX_REMOVALS_PER_SYNC` | ডিফল্ট ৫০ |
| `ATTENDANCE_EXIT_AFTER_MINUTES` | ডিফল্ট ২৫ — check-in/out বাছাই না থাকলে কত মিনিট পরের punch "বের হওয়া" ধরা হবে |
| `CENTRAL_URL`, `CENTRAL_SCHOOL_ID`, `CENTRAL_SECRET` | Central অ্যাপের সাথে সংযোগ (student count রিপোর্ট, SMS credit/rate/vendor sync) |
| `CENTRAL_REPORT_PATH` | ডিফল্ট `/api/school-reports` |
| `CENTRAL_SIGNATURE_TOLERANCE_SECONDS` | ডিফল্ট ৩০০ — HMAC signature-এর সময় সহনশীলতা |

স্কুল-নির্দিষ্ট বেশিরভাগ সেটিং `.env`-এ নয়, `school_settings` টেবিলে থাকে (দেখুন [School Settings](features/13-school-settings.md))। এগুলো `SchoolSetting::get()` দিয়ে পড়া হয়, আর `SchoolSettingStore` (scoped singleton) এক request-এ এক query-তে সব key লোড করে রাখে।

## ০.৪ ফোল্ডার কাঠামো

| ফোল্ডার | কী থাকে |
|---|---|
| `app/Filament/Resources/` | Admin প্যানেলের Resource (প্রায় ৩৯টা)। প্রতিটার ভেতরে `Pages/`, `Schemas/`, `Tables/`, `RelationManagers/`, `Widgets/` |
| `app/Filament/Pages/` | Admin প্যানেলের custom page (উপস্থিতি, প্রমোশন, আইডি কার্ড, সেটিংস ইত্যাদি) |
| `app/Filament/Admin/Pages/` | Admin-এর Dashboard ও Auth page |
| `app/Filament/Teacher/` | Teacher প্যানেল — `Pages/`, `Resources/`, `Widgets/`, `Concerns/` (scope trait) |
| `app/Filament/Student/` | Student প্যানেল — একই কাঠামো |
| `app/Filament/Widgets/` | Admin ড্যাশবোর্ড widget |
| `app/Filament/Concerns/`, `app/Filament/Resources/Concerns/` | একাধিক Resource-এ শেয়ার করা trait |
| `app/Filament/Plugins/` | `PwaInstallPlugin` |
| `app/Actions/` | Business logic (Create*, Generate*, Build*Pdf*, Calculate*, Process* ...) |
| `app/Models/` | ৭৯টা Eloquent model |
| `app/Enums/` | Status, type ও permission enum |
| `app/Http/Controllers/` | PDF ডাউনলোড, QR যাচাই, Device/Central/Question Bank API |
| `app/Http/Requests/` | API ও push subscription-এর Form Request |
| `app/Http/Middleware/` | `SetLocale`, `EnsurePasswordIsChanged`, `AuthenticateAttendanceDevice`, `AuthenticateCentralServer`, `AuthenticateQuestionBank` |
| `app/Console/Commands/` | Scheduled ও utility command |
| `app/Jobs/` | PDF bundle, SMS, notice publish, acting admin মেয়াদ শেষ |
| `app/Notifications/` | Database + WebPush (+ SMS channel) notification |
| `app/Observers/` | `AttendanceObserver`, `ActingAdminObserver`, `DeviceEnrollmentObserver` |
| `app/Listeners/` | Login / Logout / Failed login activity log (auto-discovered) |
| `app/Support/` | Helper: সেটিং store, PDF image, QR, ID card layout, Bengali number, HMAC signature, Question Bank সংযোগ |
| `app/Services/` | `WorkingDaysCalculator` |
| `app/Traits/` | `HasUserType`, `HasAcademicSession`, `HasIdCardToken`, `MergesTwoSubjects`, `LogsRelationLabels`, অর্ডারিং trait |

## ০.৫ তিনটি Filament প্যানেল

| | Admin | Teacher | Student |
|---|---|---|---|
| Provider | `AdminPanelProvider` | `TeacherPanelProvider` | `StudentPanelProvider` |
| Path | `/admin` (default panel) | `/teacher` | `/student` |
| Login | ইমেইল + পাসওয়ার্ড | ইমেইল + পাসওয়ার্ড | Student ID + পাসওয়ার্ড |
| Registration | **চালু** (`Admin\Pages\Auth\Register`) | বন্ধ | বন্ধ |
| Password reset | ইমেইল লিংক | ইমেইল লিংক | Recovery PIN (`RequestPinPasswordReset`) |
| Email verification | হ্যাঁ | হ্যাঁ | হ্যাঁ (স্টুডেন্ট তৈরির সময় `email_verified_at` সেট হয়ে যায়) |
| `databaseTransactions()` | হ্যাঁ | না | — |
| Global search | বন্ধ | — | — |
| Extra middleware | — | — | `EnsurePasswordIsChanged` |
| Discover | `Filament/Resources`, `Filament/Pages`, `Filament/Widgets` | `Filament/Teacher/*` | `Filament/Student/*` |
| Manifest | `/manifest/admin.json` | `/manifest/teacher.json` | `/manifest/student.json` |

সব প্যানেলে common:

- `primary` রং `Color::Amber`, theme `resources/css/filament/theme.css`
- `databaseNotifications()`
- `PwaInstallPlugin`
- `BODY_END` render hook-এ `filament.shared.webpush-subscribe` (লগইন থাকলে)
- `USER_MENU_PROFILE_AFTER` render hook-এ `filament.shared.locale-switcher`
- `SetLocale` middleware
- Question Bank সংযুক্ত থাকলে `QuestionBankConnection::navigationItem()` (নতুন ট্যাবে খোলে, sort 90)
- user menu-র profile item-এর label = ইউজারের নাম

### প্যানেল অ্যাক্সেস — `User::canAccessPanel()`

```php
'admin'   => isAdmin() || isSuperAdmin() || isStaff() || hasRole('super_admin_acting') || hasRole('acting_admin'),
'teacher' => isTeacher(),
'student' => isStudent(),
```

`isAdmin()` ইত্যাদি `HasUserType` trait থেকে আসে, যা `user_type` enum দেখে। অর্থাৎ **প্যানেল অ্যাক্সেস user_type দিয়ে, আর প্যানেলের ভেতরের কাজ Spatie permission দিয়ে** নিয়ন্ত্রিত।

`UserType::panelPath()` লগইনের পর রিডাইরেক্টের জন্য — Admin, SuperAdmin ও Staff সবাই `/admin`-এ যায়।

## ০.৬ অথেন্টিকেশন

### Admin ও Teacher লগইন

`Admin\Pages\Auth\Login` ও `Teacher\Pages\Auth\Login` Filament-এর base login extend করে। `authenticate()`-এ ইমেইল দিয়ে ইউজার খুঁজে `isActive()` false হলে `"Your account is inactive. Please contact support."` validation error দেয়।

### Student লগইন — Student ID দিয়ে

- `Student\Pages\Auth\Login` ইমেইলের বদলে `student_id` ফিল্ড দেখায়।
- `findUserByStudentId()` → `StudentProfile::where('student_id_no', ...)->first()?->user`
- `getCredentialsFromFormData()` Student ID থেকে ইউজারের internal ইমেইল বের করে auth provider-এ পাঠায়। অজানা ID হলে খালি ইমেইল → লগইন ব্যর্থ।

**Student ID ফরম্যাট:** `StudentProfile::makeStudentIdNo()` = `sprintf('%03d%05d', SchoolSetting::schoolCode(), $profileId)` — ৩ ডিজিট স্কুল কোড + ৫ ডিজিট profile id। `created` event-এ `student_id_no` ফাঁকা থাকলে `saveQuietly()` দিয়ে বসানো হয়।

**Student-এর internal ইমেইল:** তৈরির সময় `pending-{uuid}@placeholder.internal`, profile insert হওয়ার পর `std%05d@school.com` (profile id)। এই ইমেইল শুধু unique key, শিক্ষার্থী এটা দেখে না।

### প্রথম লগইনে পাসওয়ার্ড বদল বাধ্যতামূলক

- `CreateStudentProfileAction` ইউজার তৈরি করে `password = 'password'`, `must_change_password = true`।
- Admin "Reset password" করলে (`StudentProfiles\Concerns\HasResetPasswordAction`) আবার `must_change_password = true`।
- `EnsurePasswordIsChanged` middleware (শুধু Student প্যানেলে) `must_change_password` true থাকলে profile page ছাড়া সব রিকোয়েস্ট profile page-এ redirect করে।
- `Student\Pages\Auth\EditProfile` — password ও ৪ ডিজিটের PIN `must_change_password` থাকলে required; পাসওয়ার্ড দিলে `must_change_password = false`। নাম ও Student ID disabled + `dehydrated(false)`।

### Student পাসওয়ার্ড রিসেট — PIN

`RequestPinPasswordReset` (Student প্যানেলের `passwordReset(requestAction: ...)`):

- ফিল্ড: Student ID, PIN (`digits:4`), নতুন পাসওয়ার্ড (`Password::default()`), confirmation
- Rate limit key `student-pin-reset:{sha1(student_id)}` — ৫ বার ভুলে ৯০০ সেকেন্ড (১৫ মিনিট) লক
- `Hash::check($pin, $user->pin)` — `pin` কলাম `hashed` cast, `#[Hidden]`

### User মডেল

`#[Fillable(['name','phone','email','user_type','avatar','is_super_admin','is_active','password','email_verified_at','must_change_password','pin'])]`

- `MustVerifyEmail` — `sendEmailVerificationNotification()` queued `QueuedVerifyEmail` পাঠায়
- `HasAvatar` — `public` disk-এ `avatar` থাকলে URL
- `HasPushSubscriptions` (web push), `HasRoles` (Spatie), `Notifiable`
- Relation: `studentProfile`, `teacherProfile`, `staffProfile` (HasOne), `noticeReads`, `noticeTargets` (morph)

### Logout

`LogoutResponseContract` → `App\Http\Responses\LogoutResponse` — তিন প্যানেল থেকেই লগআউটের পর প্যানেলের লগইন পেজ নয়, public হোম পেজে (`route('home')`) পাঠায় (`PanelLogoutRedirectTest`)।

## ০.৭ User type, Role ও Permission

### UserType enum

| Case | value | Role নাম | প্যানেল |
|---|---|---|---|
| `SuperAdmin` | `super_admin` | `super-admin` (হাইফেন) | `/admin` |
| `Admin` | `admin` | `admin` | `/admin` |
| `Teacher` | `teacher` | `teacher` | `/teacher` |
| `Student` | `student` | `student` | `/student` |
| `Staff` | `staff` | `staff` | `/admin` |

অতিরিক্ত role: `super_admin_acting` ও `acting_admin` (দেখুন [HR & Staff](features/08-hr-and-staff.md))।

### Super Admin bypass

```php
// AppServiceProvider::boot() — কখনো remove করবেন না
Gate::before(fn ($user, $_ability) => $user->hasRole('super-admin') ? true : null);
```

Acting admin-রা ইচ্ছাকৃতভাবে bypass পায় না — তাদের permission আসে role-এর synced permission থেকে।

### Permission তালিকা

সব permission `app/Enums/Permissions/*.php` enum-এ, আর `PermissionRegistry` সেগুলো গ্রুপে সাজায় (Roles & Permissions ফর্মে এই গ্রুপই দেখায়)।

| গ্রুপ | Enum | Permission |
|---|---|---|
| User Management | `UserPermission` | `view/create/edit/delete_users`, `view/create/edit/delete_roles`, `assign_roles`, `assign_permissions` |
| Profile Management | `ProfilePermission` | student / teacher / staff profile প্রতিটার `view, create, edit, delete, restore, force_delete` |
| Academic Structure | `AcademicPermission` | `view/create/edit/delete/force_delete/restore_academic`, `send_exam_notifications` |
| Attendance | `AttendancePermission` | `mark_attendance`, `view_attendance`, `manage_attendance_settings`, `manage_attendance_devices` |
| Fee & Finance | `FeePermission` | fee types, fee structures, fee discounts, student fee invoices, fee payments, late fee rules, school accounts, account transactions, transaction categories, fund transactions — প্রতিটার view/create/edit/delete; এবং `mark_fee_default`, `recover_fee_default` |
| Salary Management | `SalaryPermission` | salary components, structures, invoices, payments, bulk payments — প্রতিটার view/create/edit/delete |
| Communication | `CommunicationPermission` | `view/create/edit/delete_notices` |
| SMS | `SmsPermission` | `manage_sms_settings`, `view_sms_logs` |
| Document Management | `DocumentPermission` | admit cards ও marksheets — প্রতিটার view/create/edit/delete |
| HR & Staff Management | `HrPermission` | leave types (CRUD), leave applications (`view`, `create`, `approve`, `reject`), public holidays (CRUD), acting admins (CRUD), `view_school_settings`, `edit_school_settings` |
| Activity Log | `ActivityLogPermission` | `view_activity_log` |

**গুরুত্বপূর্ণ:** `RoleSeeder` role-গুলোকে কোনো permission দেয় না। `admin`, `staff` ইত্যাদির permission Super Admin নিজে **User Management → Roles & Permissions** থেকে ঠিক করেন। নতুন ইনস্টলেশনে admin কিছুই দেখবে না যতক্ষণ না permission দেওয়া হয়।

Permission বদলানোর পর: `php artisan permission:cache-reset`।

### Filament 5 authorization প্যাটার্ন

Resource-গুলোতে `canCreate()`/`canEdit()` নয়, `get*AuthorizationResponse()` override করা হয় — না হলে action বাটন লুকায় না। বিস্তারিত: [User Management](features/11-user-management.md)।

## ০.৮ Admin প্যানেলের Navigation ম্যাপ

গ্রুপের ক্রম `AdminPanelProvider::navigationGroups()` থেকে। "লুকানো" মানে `shouldRegisterNavigation = false` — অন্য পেজের বাটন বা লিংক থেকে খোলে।

| গ্রুপ | Sort | Resource / Page | মেনু লেবেল |
|---|---|---|---|
| Academic Structure | 1 | `SubjectResource` | Subjects |
| | 2 | `GroupResource` | Groups |
| | 3 | `ClassesResource` | Classes |
| | 4 | `ExamTypeResource` | Exam Types |
| | 5 | `ExamResource` | Exams |
| | 6 | `ResultRuleResource` | Result Rules |
| | লুকানো | `EnterStudentMarks` | — |
| Attendance | 1 | `StudentAttendance` | Student Attendance |
| | 2 | `TeacherAttendance` | Teacher Attendance |
| | 3 | `StaffAttendance` | Staff Attendance |
| | 4 | `StudentAttendanceRanking` | Attendance Ranking |
| | 5 | `ExamAttendanceTotals` | Attendance Totals |
| | 60 | `LateArrivalsReport` | Late Arrivals |
| | 90 | `AttendanceDeviceResource` | Attendance Devices |
| | 99 | `ManageAttendanceSettings` | Settings |
| | লুকানো | `MarkStudentAttendance`, `StudentAttendanceDetail`, `StudentAttendanceHistory`, `TeacherAttendanceDetail`, `TeacherAttendanceHistory`, `StaffAttendanceHistory`, `ClassAttendanceRanking`, `AttendanceModificationDetails` | — |
| User Profile | 1 | `StudentProfileResource` | Students |
| | 2 | `TeacherProfileResource` | Teachers |
| | 3 | `StaffProfileResource` | Staff |
| | 4 | `PromoteStudents` | Promote |
| | লুকানো | `PromoteStudentsForClass`, `BulkPromoteStudentsForClass` | — |
| Fee & Finance | 1 | `FeeTypeResource` | Fee Types |
| | 2 | `FeeDiscountResource` | Fee Discounts |
| | 3 | `StudentFeeDiscountResource` | Student Discounts |
| | 4 | `SchoolAccountResource` | School Accounts |
| | 5 | `FeeStructureResource` | Fee Structures |
| | 6 | `StudentFeeInvoiceResource` | Fee Invoices |
| | 7 | `FeePaymentResource` | Fee Payments |
| | 8 (লুকানো) | `LateFeeRuleResource` | — |
| Communication | — | `NoticeResource` | Notices |
| SMS | 1 | `SmsSettings` | SMS Settings |
| | 2 | `SmsVendorResource` | SMS Vendors |
| | 3 | `SmsRechargeRequestResource` | Recharge Requests |
| | 4 | `SmsBalanceTransactionResource` | Balance History |
| | 5 | `SmsLogResource` | SMS Logs |
| Document Management | — | `AdmitCardResource`, `MarksheetResource`, `SeatPlan`, `StudentIdCards`, `TeacherIdCards`, `StaffIdCards`, `StudentAttendanceReport` | Admit Cards, Marksheets, Seat Plan, Student / Teacher / Staff ID Card, Attendance Report |
| HR & Staff Management | 1 | `LeaveTypeResource` | Leave Types |
| | 2 | `LeaveApplicationResource` | Leave Applications |
| | 3 | `ActingAdminResource` | Acting Admins |
| | 4 | `PublicHolidayResource` | Public Holidays |
| Salary Management | 1 | `SalaryComponentResource` | Salary Components |
| | 2 | `SalaryStructureResource` | Salary Structures |
| | 3 | `SalaryInvoiceResource` | Salary Invoices |
| | 4 | `SalaryPaymentResource` | Salary Payments |
| | 5 | `SalaryBulkPaymentResource` | Bulk Payments |
| Fund Management | 1 | `TransactionCategoryResource` | Transaction Categories |
| | 2 | `FundTransactionResource` | Fund Transactions |
| | 3 | `AccountTransactionResource` | Account Ledger |
| User Management | 1 | `RoleResource` | Roles & Permissions |
| Activity Log | — | `ActivityLogResource` | Activity Log |
| School Settings | — | `SchoolSettings`, `GradeScaleSettings`, `QuestionBankSettings` | School Settings, Grading Scale, Question Bank |
| (গ্রুপ ছাড়া) | 90 | Question Bank লিংক | Question Bank (সংযুক্ত থাকলে) |

## ০.৯ Scheduler (Cron)

`routes/console.php`। প্রোডাকশনে `* * * * * php artisan schedule:run` cron লাগবে।

| কখন | Command / Job | কাজ |
|---|---|---|
| প্রতি মাসের ১ তারিখ সকাল ৭টা | `GenerateMonthlyFeeInvoices` | মাসিক ফি ইনভয়েস তৈরি |
| মাসের শেষ দিন রাত ১১টা | `GenerateMonthlySalaryInvoices` | চলতি মাসের বেতন ইনভয়েস (ডুপ্লিকেট skip) |
| প্রতি ঘণ্টা (`withoutOverlapping`) | `MarkAbsentAttendance` | স্কুল দিন শেষ হলে যারা punch করেনি তাদের absent (পুনরাবৃত্তি নিরাপদ) |
| প্রতিদিন সকাল ৮টা | `CheckLeaveExcess` | absent কর্মীদের ছুটি সীমা অতিক্রম log |
| প্রতিদিন রাত ১২:১০ | `DeactivateExpiredActingAdminsJob` | মেয়াদ শেষ acting admin নিষ্ক্রিয় |
| প্রতি রবিবার রাত ১টা | `activitylog:clean` | ৭৩০ দিনের পুরনো activity log মুছে ফেলা |
| প্রতি N মিনিট (`push_notifications.retry_interval_minutes`) | `ResendUnacknowledgedPushNotifications` | ডিভাইস স্বীকার করেনি এমন push আবার পাঠানো |
| প্রতিদিন রাত ২টা | `PrunePushNotificationDeliveries` | ১ সপ্তাহের পুরনো delivery রেকর্ড মোছা |
| প্রতি রবিবার রাত ২:৩০ | `PrunePdfThumbnails` | ৩০ দিন অব্যবহৃত PDF thumbnail মোছা |
| প্রতি মাসের ১৫ তারিখ রাত ১২টা | `SendCentralStudentReport` | Central অ্যাপে মোট শিক্ষার্থী সংখ্যা পাঠানো |

অন্যান্য artisan command (manual): `AttachSubject`, `CreateClass`, `CreateSubject`, `CreditSmsBalance`।

## ০.১০ Queue ও Job

`QUEUE_CONNECTION=database`। প্রোডাকশনে `php artisan queue:work` চালু রাখতে হবে।

| Job | কাজ |
|---|---|
| `GenerateAdmitCardPdfJob`, `GenerateAdmitCardBundlePdfJob` | অ্যাডমিট কার্ড PDF (একক ও bundle) |
| `GenerateMarksheetPdfJob`, `GenerateMarksheetBundlePdfJob` | মার্কশিট PDF |
| `PublishNoticeJob` | নোটিশ প্রকাশ ও target ইউজারদের notification |
| `SendSmsJob` | SMS পাঠানো |
| `DeactivateExpiredActingAdminsJob` | Scheduler থেকে |

Notification (database + webpush): `ExamMarksNotification`, `ExamResultPublishedNotification`, `FeePaymentReceivedNotification`, `NoticeCreatedNotification`, `SalaryInvoiceGeneratedNotification`, `SalaryPaymentReceivedNotification`, `StudentAttendanceMarkedNotification`, `StudentDevicePunchNotification`, `StudentFeeInvoiceGeneratedNotification`, `StudentFeeInvoiceUpdatedNotification`, `QueuedVerifyEmail`।

## ০.১১ Web রাউট

`routes/web.php`। PDF রাউটগুলো `auth` middleware-এর পেছনে; যাচাই রাউটগুলো public ও `throttle:30,1`।

### Public

| রাউট | নাম | কাজ |
|---|---|---|
| `GET /` | `home` | হোম পেজ (লোগো, নাম, ঠিকানা, EIIN, তিন প্যানেলের কার্ড) |
| `GET /{panel}/install` | `install-guide` | PWA ইনস্টল গাইড (admin / student / teacher) |
| `GET /verify` | `verify` | হাতে কোড লিখে যাচাই |
| `GET /verify/admit-card/{token}` | `admit-cards.verify` | অ্যাডমিট কার্ড QR |
| `GET /verify/id-card/{token}` | `id-cards.verify` | আইডি কার্ড QR |
| `GET /verify/marksheet/{code}` | `marksheets.verify` | মার্কশিট QR |
| `GET /verify/receipt/{code}` | `fee-receipts.verify` | ফি রসিদ QR |
| `GET /r/{code}` | `fee-receipts.short` | SMS-এর ছোট রসিদ লিংক → রিডাইরেক্ট |
| `GET /email/verify/{id}/{hash}` | `verification.verify` | ইমেইল যাচাই (signed) |
| `GET /locale/{locale}` | `locale.switch` | ভাষা পরিবর্তন |

### Auth-protected PDF

| রাউট | কাজ |
|---|---|
| `/admit-cards/sample/{design}`, `/admit-cards/{admitCard}/view` | অ্যাডমিট কার্ড নমুনা ও একক |
| `/marksheets/{marksheet}/view` | একক মার্কশিট |
| `/classes/{class}/marksheets/{exam}/download` | ক্লাসের সব মার্কশিট |
| `/classes/{class}/admit-cards/{exam}/download` | ক্লাসের সব অ্যাডমিট কার্ড |
| `/classes/{class}/seat-plan/download` | সিট প্ল্যান |
| `/classes/{class}/student-id-cards/{view,download}` | শিক্ষার্থী আইডি কার্ড |
| `/classes/{class}/student-list/download` | শিক্ষার্থী তালিকা |
| `/subject-list/download`, `/teacher-list/download` | বিষয় ও শিক্ষক তালিকা |
| `/teacher-id-cards/{view,download}`, `/teacher-id-cards/{teacher}/view` | শিক্ষক আইডি কার্ড |
| `/staff-id-cards/{view,download}`, `/staff-id-cards/{staff}/view` | স্টাফ আইডি কার্ড |
| `/classes/{class}/attendance-report/{year}/{month}/download` | ক্লাসের মাসিক উপস্থিতি রিপোর্ট |
| `/attendance-report/employees/{year}/{month}/download` | কর্মীদের মাসিক উপস্থিতি |
| `/attendance-report/teachers/{year}/{month}/download` | শিক্ষক উপস্থিতি শিট |
| `/exams/{exam}/schedule/download` | পরীক্ষার রুটিন |
| `/exams/{exam}/tabulation-sheet/download` | ট্যাবুলেশন শিট |
| `/exams/{exam}/result-report/download` | রেজাল্ট রিপোর্ট |
| `/fee-payments/{batchId}/slip` | ফি পেমেন্ট স্লিপ |

### Controller নিজেই auth চেক করে (named `login` route না থাকায়)

| রাউট | কাজ |
|---|---|
| `GET /fund-transactions/{fundTransaction}/attachment` | ফান্ড লেনদেনের সংযুক্তি (401 অথবা permission চেক) |
| `POST/DELETE /push-subscriptions` | Web push subscription (Form Request-এর `authorize()` 403) |
| `POST /push-notification-deliveries/acknowledge` | push পৌঁছানোর স্বীকৃতি (`throttle:60,1`) |

**সতর্কতা:** এই অ্যাপে generic `login` নামের রাউট নেই। নতুন রাউটে `auth` middleware দিলে guest redirect `RouteNotFoundException` দেবে — অথবা রাউটটি PDF রাউটের মতো logged-in ব্যবহারের জন্য হতে হবে।

## ০.১২ API রাউট

`routes/api.php`। সবগুলো HMAC signature দিয়ে সুরক্ষিত (`App\Support\HmacSignature`, `CentralSignature`)।

| Prefix | Middleware | রাউট | কাজ |
|---|---|---|---|
| `/api/device` | `throttle:120,1`, `auth.device` | `POST /punches`, `GET /sync-plan`, `POST /sync-report` | ZKTeco bridge থেকে punch ও sync — [Attendance](features/02-attendance.md) |
| `/api/central` | `throttle:30,1`, `auth.central` | `GET /student-report` | Central অ্যাপ শিক্ষার্থী সংখ্যা টানে |
| `/api/central` | `throttle:30,1` (Form Request signature যাচাই) | `POST /sms-credit`, `/sms-rate`, `/sms-response-codes`, `/sms-vendors` | SMS ব্যালেন্স রিচার্জ, রেট, response code, vendor sync — [SMS](features/06-sms.md) |
| `/api/question-bank` | `throttle:60,1`, `auth.question-bank` | `GET /classes`, `GET /classes/{class}/exams`, `GET /exams/{exam}/subjects/{subject}/students`, `POST .../results` | Question Bank অ্যাপের সাথে মার্কস আদান-প্রদান — [School Settings](features/13-school-settings.md) |

API ও JSON রিকোয়েস্টের exception সবসময় JSON হিসেবে রেন্ডার হয় (`bootstrap/app.php`)।

## ০.১৩ Observer, Listener ও Event

| কোথায় | কী |
|---|---|
| `Attendance::observe(AttendanceObserver)` | উপস্থিতি তৈরি বা আপডেট হলে `leave_attendance_logs` sync (ছুটিতে থাকা কর্মীর উপস্থিতির হিসাব) |
| `ActingAdmin::observe(ActingAdminObserver)` | acting admin তৈরি/আপডেট/মুছে ফেলায় ইউজারের `super_admin_acting` / `acting_admin` role sync |
| `StudentProfile`, `TeacherProfile`, `StaffProfile` → `DeviceEnrollmentObserver` | নতুন প্রোফাইলে ডিভাইস enroll ID, স্ট্যাটাস বদলে ডিভাইস থেকে সরানোর শিডিউল |
| `ActivityLogLabelCache::SOURCE_MODELS`-এর `updated/deleted/restored` | activity log-এর label cache flush |
| `app/Listeners/LogSuccessfulLogin`, `LogSuccessfulLogout`, `LogFailedLogin` | auto-discovered — `AppServiceProvider`-এ আবার register করবেন না, না হলে দুবার log হবে |

`Model::preventLazyLoading(! isProduction())` — development ও টেস্টে N+1 lazy load exception দেয়।

## ০.১৪ Localization

- `config('app.available_locales')` = `['en' => 'English', 'bn' => 'বাংলা']`
- `GET /locale/{locale}` → `LocaleController::switch` session-এ `locale` রাখে
- `SetLocale` middleware প্রতিটা প্যানেলে session থেকে `App::setLocale()`
- অনুবাদ শুধু `lang/bn.json`-এ (~১৮৯ key)। বেশিরভাগ label সরাসরি ইংরেজি string, তাই অনুবাদ আংশিক।
- PDF ডকুমেন্টের ভাষা আলাদা — `App\Support\DocumentLocale` ও `BengaliNumber`

## ০.১৫ PWA ও Web Push

- `PwaInstallPlugin` প্রতিটা প্যানেলে install banner, topbar button ও login-page লিংক যোগ করে (`resources/views/filament/shared/pwa-install-*.blade.php`)
- Manifest: `/manifest/{admin,teacher,student}.json`
- `InstallGuideController` → `resources/views/install-guide.blade.php` — Android, iPhone, কম্পিউটার, Facebook in-app ব্রাউজারের নির্দেশনা
- PWA install-এর জন্য HTTPS বাধ্যতামূলক (HTTP → HTTPS redirect আছে)
- Web push: `filament.shared.webpush-subscribe` view → `POST /push-subscriptions`
- প্রতিটা push-এর delivery রেকর্ড থাকে; ডিভাইস `acknowledge` না করলে retry হয় (`max_attempts` পর্যন্ত)

## ০.১৬ কোডিং নিয়ম

- DB facade নয়, Eloquent
- Controller পাতলা, business logic `app/Actions/`-এ
- Validation Form Request-এ
- প্রতিটা feature-এ Pest টেস্ট
- `php artisan make:*` দিয়ে ফাইল তৈরি
- PHP ফাইল বদলালে `vendor/bin/pint --dirty --format agent`
- Migration-এ enum value-র বাংলা মন্তব্য
- Foreign key: `table_name_id`; polymorphic: `*_type` + `*_id`
- সব UI responsive, মোবাইলে `text-xs`
- Full-width widget: `columnSpan(['default' => 'full'])` — string `'full'` মোবাইলে ভাঙে
- Filament 5: `Forms\Placeholder` deprecated → `Infolists\TextEntry->state()`
- Header/footer TableWidget দ্রুত রেন্ডার করতে `$isLazy = false`

## ০.১৭ টেস্ট

- `tests/Feature/`-এ ২৩০টির বেশি টেস্ট ফাইল
- চালানো: `php artisan test --compact` অথবা `--filter=...`
- Filament টেস্টে `actingAs()` আগে; edit page-এ `call('save')`
- টেবিল action modal-এর ভেতরের লেখা `assertSee` দিয়ে দেখা যায় না → `assertSchemaComponentVisible` / `assertSchemaStateSet`
- বাংলা string অন্য ফাইল থেকে হুবহু কপি করুন — নতুন করে টাইপ করলে Unicode normalization-এর কারণে `assertSee` চুপচাপ ব্যর্থ হতে পারে

## ০.১৮ জানা সমস্যা ও সতর্কতা

1. **Admin প্যানেলে public registration চালু।** `AdminPanelProvider`-এ `->registration(Register::class)` আছে। `Admin\Pages\Auth\Register::handleRegistration()` যে কেউ রেজিস্টার করলে তাকে `UserType::Admin` ও `admin` role দেয়, আর `phone` hardcoded `'01812345678'`। প্রোডাকশনে এটা নিরাপত্তা ঝুঁকি — admin role-এ permission থাকলে অচেনা ব্যক্তি সেগুলো পেয়ে যাবে। Registration বন্ধ রাখা বা invite-ভিত্তিক করার কথা বিবেচনা করুন।
2. `Register`-এ `'status' => true` দেওয়া আছে, কিন্তু `User` মডেলে `status` fillable নয় (কলাম `is_active`) — ফলে মানটা উপেক্ষিত হয়।
3. `Teacher\Pages\Auth\Register` ও `Student\Pages\Auth\Register` ক্লাস আছে, কিন্তু প্যানেলে `->registration()` চালু নয় — অব্যবহৃত।
4. প্রোডাকশন seeder Super Admin-কে `password` পাসওয়ার্ড দেয় — প্রথম লগইনের পরেই বদলাতে হবে।
5. Payment gateway (bKash ইত্যাদি) এখনো নেই — ইচ্ছাকৃতভাবে স্থগিত।
