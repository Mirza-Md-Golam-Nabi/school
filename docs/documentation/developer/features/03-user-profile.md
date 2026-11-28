# ৩. User Profile ও Promotion — Developer Reference

ব্যবহারকারী নির্দেশিকা: [../../user-guide/features/03-user-profile.md](../../user-guide/features/03-user-profile.md)

## সূচিপত্র

- [৩.১ কোড ফাইল](#৩১-কোড-ফাইল)
- [৩.২ Database টেবিল](#৩২-database-টেবিল)
- [৩.৩ Permission](#৩৩-permission)
- [৩.৪ Student তৈরি ও user account](#৩৪-student-তৈরি-ও-user-account)
- [৩.৫ Teacher / Staff তৈরি](#৩৫-teacher--staff-তৈরি)
- [৩.৬ Spreadsheet import](#৩৬-spreadsheet-import)
- [৩.৭ Optional subject](#৩৭-optional-subject)
- [৩.৮ Status ও তার প্রভাব](#৩৮-status-ও-তার-প্রভাব)
- [৩.৯ Promotion](#৩৯-promotion)
- [৩.১০ PDF](#৩১০-pdf)
- [৩.১১ টেস্ট](#৩১১-টেস্ট)
- [৩.১২ জানা সমস্যা ও সতর্কতা](#৩১২-জানা-সমস্যা-ও-সতর্কতা)

---

## ৩.১ কোড ফাইল

| অংশ | ফাইল |
|---|---|
| Student resource | `app/Filament/Resources/StudentProfiles/` — `StudentProfileResource`, `Schemas/StudentProfileForm`, `Schemas/StudentProfileInfolist`, `Tables/StudentProfilesTable`, `Pages/{ListStudentProfiles (class cards), StudentsByClass (?classId=), CreateStudentProfile, EditStudentProfile, ViewStudentProfile}`, `Widgets/GroupStudentCountsWidget` |
| Student concerns | `Concerns/HasStudentImportActions`, `HasStudentCredentialsModal`, `HasResetPasswordAction` |
| Teacher resource | `app/Filament/Resources/TeacherProfiles/` — `TeacherProfileForm`, `TeacherProfilesTable`, `ListTeacherProfiles` (import, PDF list), Create/Edit |
| Staff resource | `app/Filament/Resources/StaffProfiles/` — একই কাঠামো |
| Shared | `app/Filament/Resources/Concerns/ResponsiveText` (address field প্রতিটা form-এ inline; `same_address` toggle) |
| Promotion pages | `app/Filament/Pages/PromoteStudents` (landing, `?year=`), `PromoteStudentsForClass` (`?classId=&year=`, row action + fee collection), `BulkPromoteStudentsForClass` |
| Actions | `CreateStudentProfileAction`, `UpdateStudentProfileAction`, `CreateTeacherProfileAction`, `CreateStaffProfileAction`, `ImportStudentsFromSpreadsheetAction`, `ImportTeachersFromSpreadsheetAction`, `BuildStudentImportTemplateAction`, `BuildTeacherImportTemplateAction`, `PromoteStudentsAction`, `CollectPromotionFeesAction`, `ResolveDefaultPromotionOptionalSubjects`, `FindDuplicateRollsAction`, `BuildStudentSubjectListAction`, `BuildStudentFeeSummaryAction`, `BuildClassStudentListPdfAction`, `BuildTeacherListPdfAction` |
| Controllers | `ClassStudentListPdfController`, `TeacherListPdfController` |
| Models | `StudentProfile`, `TeacherProfile`, `StaffProfile`, `Address`, `StudentOptionalSubject`, `StudentClassHistory` |
| Enums | `StudentStatus`, `EmploymentStatus`, `PromotionStatus`, `Gender`, `BloodGroup`, `Religion`, `AddressType`, `StudentListColumn`, `StudentListNameLanguage`, `TeacherListColumn` |
| Traits | `HasUserType`, `HasIdCardToken`, `OrdersByUserName`, `OrdersByStudentRoll` |

## ৩.২ Database টেবিল

| টেবিল | নোট |
|---|---|
| `users` | `user_type`, `is_active`, `must_change_password`, `pin` (hashed) |
| `student_profiles` | `user_id`, `student_id_no` (৮ ডিজিট), `name_bn`, `photo`, `roll_no`, `registration_no`, `session_year`, `current_class_id`, `current_section_id`, `current_group_id`, `admission_date`, `status`, ব্যক্তিগত তথ্য, `birth_certificate_no` (unique), বাবা/মা/অভিভাবকের তথ্য ও ছবি, `guardian_phone`, `id_card_token`, soft delete |
| `teacher_profiles` | `user_id`, `phone`, `designation`, `department`, `qualification`, `joining_date`, `status`, `default_school_account_id`, `signature`, `id_card_photo_position`, `id_card_token`, ব্যক্তিগত তথ্য, soft delete |
| `staff_profiles` | `user_id`, `designation`, `joining_date`, `status`, `default_school_account_id`, `id_card_token`, ব্যক্তিগত তথ্য, soft delete |
| `addresses` | polymorphic `addressable` (`AddressType` present / permanent) |
| `student_optional_subjects` | `student_id`, `class_id`, `subject_id`, `role` — class অনুযায়ী, promotion-এ পুরনো row মোছে না |
| `student_class_history` | promotion-এর আগের অবস্থা: `class_id`, `section`, `group`, `roll_no`, `session_year`, `status` (`PromotionStatus`), remarks |

## ৩.৩ Permission

`ProfilePermission` — student / teacher / staff প্রতিটার `view`, `create`, `edit`, `delete`, `restore`, `force_delete`। তিনটা resource `HasEntityPermissions` ব্যবহার করে (permission নাম = `{action}_{table}`, যেমন `edit_student_profiles`)। `RolePermissionEnforcementTest` restore / force delete যাচাই করে।

**ঠিক করা হয়েছে:** `PromoteStudents`, `PromoteStudentsForClass`, `BulkPromoteStudentsForClass` এখন `RequiresPermission` trait দিয়ে নতুন `promote_students` permission চায় (`ProfilePermission::PROMOTE_STUDENTS`)। Production-এ `php artisan db:seed --class=PermissionSeeder` ও `permission:cache-reset` চালিয়ে role-এ permission দিতে হবে।

## ৩.৪ Student তৈরি ও user account

`CreateStudentProfileAction::handle($data)` (transaction):

1. `birth_certificate_no` থাকলে এবং মিললে → `replaceExistingProfile()` — নতুন user তৈরি হয় না; profile আপডেট, password `password`, `must_change_password = true`
2. না হলে `User::create` — placeholder email `pending-{uuid}@placeholder.internal`, password `password`, `user_type = student`, `is_active`, `email_verified_at = now()`, `must_change_password = true`; role `student`; `Registered` event
3. `studentProfile()->create()` → `StudentProfile::created` event `student_id_no` বসায়
4. email → `std%05d@school.com` (profile id)
5. address ও optional subject save
6. `generated_student_id` / `generated_password` attribute সেট — Create page credentials modal দেখায় (Create → class list-এ session flash; Create & create another → একই পাতায় `mountAction`)

Form-এ `birth_certificate_no` live: create-এ মিললে disabled `student_id_no` field-এ পুরনো ID দেখায়। Edit-এ অন্য profile-এর সাথে duplicate হলে `Rule::unique` error; create-এ unique rule নেই (ইচ্ছাকৃত)।

Photo: `public` disk, `student-profiles/photos`, `visibility('public')`, ৬০০×৬০০ contain resize। Photo না থাকলে PDF-এ gender অনুযায়ী placeholder।

`HasResetPasswordAction`: password `password`, PIN null, `must_change_password = true`।

## ৩.৫ Teacher / Staff তৈরি

`CreateTeacherProfileAction` / `CreateStaffProfileAction`:

- email দিয়ে বিদ্যমান user খোঁজে — থাকলে নতুন user নয়, শুধু profile (password দেওয়া থাকলেই বদলায়), role assign
- না থাকলে user তৈরি (`password` form থেকে, ডিফল্ট `password`), role `teacher` / `staff`
- Form-এ hidden `user_found`, `has_profile` (saved false) — helper text-এর জন্য
- Teacher signature: `public` disk, `teacher-profiles/signatures`, ≤১ MB

Teacher বনাম Staff form পার্থক্য: staff-এ `phone`, `department`, `qualification`, `signature` নেই।

## ৩.৬ Spreadsheet import

| Import | Action | `MAX_ROWS` | নোট |
|---|---|---|---|
| Students | `ImportStudentsFromSpreadsheetAction` | ১০০০ | ক্লাস পাতা থেকে হলে `class` কলাম ঐচ্ছিক; `birth_certificate_no` মিললে update; duplicate roll → warning (`FindDuplicateRollsAction`), block নয়; password `password` |
| Teachers | `ImportTeachersFromSpreadsheetAction` | ৫০০ | email unique (বিদ্যমান email চলবে না); `DEFAULT_PASSWORD = 'password'` |
| Subjects | `ImportSubjectsFromSpreadsheetAction` | ৩০০ | [Academic](01-academic-structure.md) দেখুন |

সব import all-or-nothing: যেকোনো row-এ error হলে কিছুই save হয় না, প্রথম ১৫টা error notification-এ। Template-এ `Instructions` শিট, boolean কলামে yes/no dropdown validation, text কলাম (phone, birth cert) Text ফরম্যাটে। Staff import নেই।

বড় import-এর আগে device auto-enroll বন্ধ রাখার কথা বিবেচনা করুন (`ATTENDANCE_AUTO_ENROLL`)।

## ৩.৭ Optional subject

- Form-এ `main_optional_subject_id` ও `extra_optional_subject_id` শুধু তখনই দেখায় যখন class + group-এ optional subject আছে
- Main: শুধু নির্বাচিত group-এর নিজস্ব optional; Extra: group-এর optional + "All Groups" optional; `Rule::notIn(main)`
- `student_optional_subjects`-এ class অনুযায়ী সংরক্ষিত — promotion নতুন class-এর জন্য নতুন row, পুরনো ইতিহাস থাকে
- Paper-এর ক্ষেত্রে student শুধু parent বেছে নেয়; paper role inherit করে

## ৩.৮ Status ও তার প্রভাব

| Status | Device enrollment (`ApplyProfileStatusToEnrollmentsAction`) | অন্যান্য |
|---|---|---|
| `active` | removal বাতিল / restore | সব তালিকা |
| `graduated`, `transferred` | auto queue, ২৪ ঘণ্টা grace | marks, attendance, invoice তালিকা থেকে বাদ |
| `dropped`, `inactive` | admin approval | একই |
| Employee `resigned`, `terminated`, `retired` | admin approval | attendance ও salary তালিকা থেকে বাদ |

`StudentProfile::scopeActive()`, `scopeFormer()`, `inCurrentSession()`।

## ৩.৯ Promotion

`PromoteStudentsAction::handle()` (transaction), প্রতি student:

1. `StudentClassHistory::create` — আগের class, section, group, roll, session, `PromotionStatus`, remarks
2. Promoted / Repeated → `current_class_id`, section, group, `roll_no` = নতুন, `session_year + 1`, status active; নতুন class-এর optional subject save
3. Graduated / Transferred / Dropped → `StudentStatus` সেই অনুযায়ী (status observer → device enrollment)

Default মান (`PromoteStudentsForClass`, `BulkPromoteStudentsForClass`):

- Target class: Promoted → পরের active class (`order` অনুযায়ী); কোনো পরের class না থাকলে (terminal class) null — graduating, নিজের class-এ fallback করে না
- Group: target class-এ একই group থাকলে সেটিই
- Optional: `ResolveDefaultPromotionOptionalSubjects`
- Roll: `Classes.promotion_roll_policy` — `merit` → এই class-এর Main exam-এ সেই session-এর class rank (না থাকলে খালি; পুরনো roll fallback নয়); `keep_current` → বর্তমান roll
- Validation: Promoted/Repeated-এ target class ও roll বাধ্যতামূলক; main ≠ extra optional
- Duplicate roll (একই class + section + session) → warning notification, block নয়
- Fee default বকেয়া → confirmation-এ সতর্কবার্তা (`FeeDefaultActions::defaultedDuesWarning`), block নয়

Row action-এ ঐচ্ছিক fee collection (`CollectPromotionFeesAction`): target class ও **পরের session year**-এর fee structure থেকে বাছাই, monthly-র জন্য মাস, total payable (discount ও আগের পেমেন্ট বাদ), কম পেমেন্টে বাকিটা due, ০ হলে শুধু invoice; সফল হলে slip ডাউনলোড বাটন। Target class বদলালে নির্ভরশীল সব field (fee সহ) reset।

Landing page year options: চলতি ও আগের বছর; ডিফল্ট আগের বছর। `getNavigationItemActiveRoutePattern()` sub-page-এও "Promote" মেনু highlight রাখে।

## ৩.১০ PDF

| PDF | Route | Option |
|---|---|---|
| Student list | `student-list.class.download` | `StudentListColumn` কলাম, `StudentListNameLanguage` (en / bn / both), orientation |
| Teacher list | `teacher-list.download` | `TeacherListColumn` কলাম, orientation |

## ৩.১১ টেস্ট

`StudentIdLoginTest`, `StudentPasswordRecoveryTest`, `StudentEditProfileTest`, `StudentMyProfileTest`, `StudentBanglaNameTest`, `StudentOfficialPhotoTest`, `StudentOptionalSubjectSelectionTest`, `StudentProfileCreateClassPrefillTest`, `StudentProfilePromoteRowActionTest`, `StudentProfilesTableClassGroupFilterTest`, `StudentSpreadsheetImportTest`, `StudentStatusInactiveTest`, `StudentsByClassCurrentSessionOnlyTest`, `ListStudentProfilesCurrentSessionCountTest`, `ViewStudentProfileTest`, `ViewStudentProfileSubjectsTabTest`, `GroupStudentCountsWidgetTest`, `TeacherSpreadsheetImportTest`, `TeacherProfilePhoneAndSelfEditTest`, `TeacherProfilesTableSortTest`, `TeacherSignatureUploadTest`, `TeacherMyProfileTest`, `PromoteStudentsActionTest`, `PromoteStudentsForClassTest`, `BulkPromoteStudentsForClassTest`, `PromoteStudentsLandingPageTest`, `PromoteStudentsFeeCollectionTest`, `PromotionRollPolicyTest`, `ActivityLogProfilesTest`, `BuildClassStudentListPdfActionTest`, `BuildTeacherListPdfActionTest`।

## ৩.১২ জানা সমস্যা ও সতর্কতা

1. ~~Promotion page-গুলোতে authorization নেই~~ — ঠিক করা হয়েছে (৩.৩)।
2. নতুন student, import ও password reset — সবগুলোতে একই পরিচিত পাসওয়ার্ড `password`। প্রথম লগইনে বদল বাধ্যতামূলক হলেও, Student ID অনুমানযোগ্য (school code + ক্রমিক) হওয়ায় শিক্ষার্থী প্রথম লগইনের আগে অন্য কেউ ঢুকে PIN বসিয়ে দিতে পারে। প্রাথমিক পাসওয়ার্ড random করার কথা বিবেচনা করুন।
3. Teacher ও staff-এর ডিফল্ট পাসওয়ার্ডও `password`, এবং তাদের প্রথম লগইনে বদল বাধ্যতামূলক নয় (`EnsurePasswordIsChanged` শুধু Student প্যানেলে)।
4. Staff-এর spreadsheet import নেই।
