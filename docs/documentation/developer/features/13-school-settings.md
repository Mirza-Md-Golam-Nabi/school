# ১৩. School Settings — Developer Reference

ব্যবহারকারী নির্দেশিকা: [../../user-guide/features/13-school-settings.md](../../user-guide/features/13-school-settings.md)

## সূচিপত্র

- [১৩.১ কোড ফাইল](#১৩১-কোড-ফাইল)
- [১৩.২ Storage](#১৩২-storage)
- [১৩.৩ Key তালিকা](#১৩৩-key-তালিকা)
- [১৩.৪ Grading scale](#১৩৪-grading-scale)
- [১৩.৫ Question Bank integration](#১৩৫-question-bank-integration)
- [১৩.৬ টেস্ট](#১৩৬-টেস্ট)
- [১৩.৭ জানা সমস্যা ও সতর্কতা](#১৩৭-জানা-সমস্যা-ও-সতর্কতা)

---

## ১৩.১ কোড ফাইল

| অংশ | ফাইল |
|---|---|
| Pages | `app/Filament/Pages/SchoolSettings.php`, `GradeScaleSettings.php`, `QuestionBankSettings.php` |
| Model / store | `app/Models/SchoolSetting.php` (`get()`, `schoolCode()`, `eiinLine()`), `app/Support/SchoolSettingStore.php` |
| Seeder | `DocumentSettingsSeeder` |
| Question Bank | `app/Support/QuestionBankConnection.php`, `Http/Middleware/AuthenticateQuestionBank`, `Controllers/QuestionBankClassesController`, `QuestionBankResultsController`, `Requests/SaveQuestionBankMarksRequest`, `Student/Pages/SchoolQuestions` |
| Grade | `app/Models/GradeScale.php`, migration `2026_08_25_120000_create_grade_scales_table` |

## ১৩.২ Storage

`school_settings` টেবিল — `key`, `value`, `description`। `SchoolSetting::get($key, $default)` → `SchoolSettingStore` (request-scoped, এক query-তে সব; save / delete-এ forget)। ফাইল (logo, seal, signature) `public` disk-এ path হিসেবে।

`config('app.school_name')` (`SCHOOL_NAME` env) home page ও কিছু জায়গায়; `school_name` setting document-এ।

## ১৩.৩ Key তালিকা

| Section | Key | ব্যবহার |
|---|---|---|
| General | `school_name`, `school_name_bn`, `school_code` | document; `school_code` → `StudentProfile::makeStudentIdNo()` (শুধু নতুন ID) |
| Working Days | `weekend_days` (JSON) | `WorkingDaysCalculator` |
| Academic Session | college session start month | `AcademicSession::collegeStartMonth()` (ডিফল্ট ৭) |
| Branding | `school_logo`, `school_seal`, head signatory title en / bn, `principal_signature`, `school_address`, `school_address_bn`, EIIN, `school_established_year` | সব document, home page |
| Result | `result_full_marks` | `ResultRule::resolvedFullMarks()` |
| Merged Subjects | default `pass_rule`, `component_pass_rule`, `absent_rule`, `rounding` | merge rule null হলে |
| Admit Card | `admit_card_footer_text`, QR toggle | admit card PDF |
| Marksheet | `marksheet_use_logo`, `marksheet_use_watermark`, `marksheet_watermark_text`, `marksheet_footer_text`, QR toggle | marksheet PDF |
| Fee Payment Slip | QR toggle | slip PDF |
| ID card | design / template / logo colour / fields / validity / issue date (প্রতি card type) | ID card page-এ set |
| Question Bank | url, code, secret (encrypted), connected flag | `QuestionBankConnection` |

Document setting বদলালে আগে generate করা PDF বদলায় না।

## ১৩.৪ Grading scale

[Academic Structure ১.৯](01-academic-structure.md#১৯-subject-outcome-passfailgrade) দেখুন — `min_mark` cascade lookup, `max_mark` display-only (ইচ্ছাকৃত)।

## ১৩.৫ Question Bank integration

- Settings page: url, connection code, secret → "Save and connect" handshake; secret encrypted, আর দেখানো হয় না, খালি রাখলে পুরনোটা থাকে
- Connected হলে admin ও teacher panel-এ `QuestionBankConnection::navigationItem()` (নতুন ট্যাব)
- Student panel `SchoolQuestions` — স্কুলের প্রশ্ন (exam শেষ হলে, উত্তরসহ) iframe-এ, secret দিয়ে signed short-lived link (`QUESTIONS_LINK_SECONDS = 7200`)
- Question Bank → school API (`/api/question-bank/*`, `throttle:60,1`, `auth.question-bank` signature + replay window):
  - `GET /classes`
  - `GET /classes/{class}/exams` — exam ও MCQ-ওয়ালা subject
  - `GET /exams/{exam}/subjects/{subject}/students` — student list ও আগের নম্বর
  - `POST /exams/{exam}/subjects/{subject}/results` — MCQ নম্বর `student_results`-এ (`SaveQuestionBankMarksRequest`)

## ১৩.৬ টেস্ট

`SchoolSettingsTest`, `GradeScaleSettingsTest`, `GradeScaleTest`, `QuestionBankConnectionTest`, `QuestionBankClassListApiTest`, `QuestionBankResultsApiTest`, `QuestionBankSchoolQuestionsTest`, `CollegeSessionDefaultsTest`।

## ১৩.৭ জানা সমস্যা ও সতর্কতা

1. `SchoolSettings`, `GradeScaleSettings`, `QuestionBankSettings` — কোনোটাতেই `canAccess()` নেই; `view_school_settings` / `edit_school_settings` কোথাও চেক হয় না ([User Management ১১.৪](11-user-management.md#১১৪-authorization-audit--পুরো-অ্যাপ))।
2. Branding helper text-এ "transfer certificates" উল্লেখ আছে, কিন্তু TC feature নেই।
3. `school_code` বদলালে বিদ্যমান Student ID বদলায় না — একই স্কুলে দুই prefix-এর ID থাকতে পারে।
