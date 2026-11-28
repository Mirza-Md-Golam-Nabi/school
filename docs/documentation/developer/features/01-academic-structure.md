# ১. Academic Structure — Developer Reference

ব্যবহারকারী নির্দেশিকা: [../../user-guide/features/01-academic-structure.md](../../user-guide/features/01-academic-structure.md)

## সূচিপত্র

- [১.১ কোড ফাইল](#১১-কোড-ফাইল)
- [১.২ Database টেবিল](#১২-database-টেবিল)
- [১.৩ Permission ও Authorization](#১৩-permission-ও-authorization)
- [১.৪ Subject ও Paper](#১৪-subject-ও-paper)
- [১.৫ Class syllabus ও eligible students](#১৫-class-syllabus-ও-eligible-students)
- [১.৬ Academic session](#১৬-academic-session)
- [১.৭ Exam lifecycle](#১৭-exam-lifecycle)
- [১.৮ Marks entry ও StudentResult](#১৮-marks-entry-ও-studentresult)
- [১.৯ Subject outcome (pass/fail/grade)](#১৯-subject-outcome-passfailgrade)
- [১.১০ Subject merge](#১১০-subject-merge)
- [১.১১ Weighted result (Result Rule)](#১১১-weighted-result-result-rule)
- [১.১২ Ranking ও GPA](#১১২-ranking-ও-gpa)
- [১.১৩ Exam notification (marks / result)](#১১৩-exam-notification-marks--result)
- [১.১৪ PDF](#১১৪-pdf)
- [১.১৫ Activity log](#১১৫-activity-log)
- [১.১৬ Artisan command](#১১৬-artisan-command)
- [১.১৭ টেস্ট](#১১৭-টেস্ট)
- [১.১৮ জানা সমস্যা ও সতর্কতা](#১১৮-জানা-সমস্যা-ও-সতর্কতা)

---

## ১.১ কোড ফাইল

| অংশ | ফাইল |
|---|---|
| Subjects | `app/Filament/Resources/Subjects/` — `SubjectResource`, `Schemas/SubjectForm`, `Tables/SubjectsTable`, `Pages/ListSubjects` (Excel import, PDF list) |
| Groups | `app/Filament/Resources/Groups/GroupResource.php` (form + table inline), `Pages/ManageGroups` (simple/modal resource) |
| Classes | `app/Filament/Resources/Classes/` — `ClassesForm`, `ClassesTable`, RelationManagers: `Sections`, `Groups`, `Subjects`, `SubjectMerges`, `TeacherSubjects` |
| Exam Types | `app/Filament/Resources/ExamTypes/` — create/update via `CreateExamTypeAction` / `UpdateExamTypeAction` (config `exam_type_configs`-এ) |
| Exams | `app/Filament/Resources/Exams/` — `ListExams` (class card view `filament.resources.exams.pages.list-exams`), `ExamsByClass` (`?classId=`), `CreateExam`, `EditExam`, `Schemas/ExamForm`, `Tables/ExamsTable`, `Actions/ExamNotificationActions` |
| Exam RelationManagers | `SubjectConfigs`, `Schedule`, `SubjectMerges`, `MeritRankings`, `TabulationSheet`, `ExamReport` |
| Result Rules | `app/Filament/Resources/ResultRules/` — `Schemas/ResultRuleForm` (Repeater `components`) |
| Marks entry | `app/Filament/Pages/EnterStudentMarks.php` (admin), `app/Filament/Teacher/Pages/EnterStudentMarks.php` (teacher), view `resources/views/filament/shared/enter-student-marks.blade.php` |
| Grade scale | `app/Filament/Pages/GradeScaleSettings.php`, `app/Models/GradeScale.php` |
| Shared form | `app/Filament/Resources/Concerns/SubjectMergeFields.php` |
| Actions | `CreateExamAction`, `CreateExamTypeAction`, `UpdateExamTypeAction`, `CopySubjectMergesToExamAction`, `SyncClassSubjectMergesAction`, `ResolveEligibleStudentsForSubject`, `ResolveSubjectOutcome`, `ResolveMergedSubjectOutcome`, `ResolveIsExtraOptionalSubject`, `CalculateWeightedResults`, `CalculateExamRankings`, `BuildStudentMarksDetail`, `BuildExamTabulationSheetDetail`, `BuildExamResultReport`, `ImportSubjectsFromSpreadsheetAction`, `BuildSubjectImportTemplateAction`, `BuildSubjectListPdfAction`, `BuildExamSchedulePdfAction`, `BuildExamTabulationSheetPdfAction`, `BuildExamResultReportPdfAction`, `app/Actions/ExamNotifications/*` |
| Support | `app/Support/AcademicSession.php`, `ClassDefinitions`, `SubjectDefinitions`, `ClassGroupSubjectDefinitions` (CLI seeding) |
| Traits | `HasAcademicSession`, `MergesTwoSubjects`, `OrdersBySubject`, `OrdersByStudentRoll` |
| Controllers | `SubjectListPdfController`, `ExamSchedulePdfController`, `ExamTabulationSheetPdfController`, `ExamResultReportPdfController` |
| Enums | `ClassLevel`, `SubjectType`, `OptionalSubjectRole`, `ExamConfigType`, `CountMethod`, `MergedPassRule`, `MergedComponentPassRule`, `MergedAbsentRule`, `ResultRounding`, `ExamNotificationChannel`, `ExamNotificationKind`, `PromotionRollPolicy`, `SubjectListColumn` |

## ১.২ Database টেবিল

| টেবিল | মূল কলাম / নোট |
|---|---|
| `subjects` | `name`, `name_bn`, `code` (unique, uppercase), `parent_subject_id` (paper → main), `has_written`, `has_mcq`, `has_practical`, `is_active`, `sort_order`, soft delete |
| `groups` | `name`, `name_bn`, `is_active`, soft delete |
| `classes` | `name`, `name_bn`, `level` (`ClassLevel`), `order`, `class_teacher_id`, `has_section`, `has_group`, `promotion_roll_policy`, `is_active`, soft delete |
| `sections` | `class_id`, `name`, soft delete |
| `class_groups` | class ↔ group pivot |
| `class_group_subject` | class syllabus — `class_id`, `subject_id`, `group_id` (null = all groups), `subject_type` (`compulsory` / `optional`) |
| `student_optional_subjects` | student-এর বাছাই করা optional subject ও role (`main_optional` / `extra_optional`), class অনুযায়ী |
| `teacher_subjects` | `teacher_id`, `subject_id`, `class_id`, `section_id`, `session_year` |
| `exam_types` + `exam_type_configs` | নাম; config-এ `type` (`main`/`general`), `count_method` (`all`/`best_n`), `best_n_count` |
| `exams` | `exam_type_id`, `class_id`, `session_year`, `start_date`, `end_date`, `is_published`, `subject_marks_channels`, `result_channels`, `notify_result_on_publish`, `has_custom_subject_merges`, result rule snapshot, soft delete |
| `exam_subject_configs` | `exam_id`, `subject_id`, `subject_name`, `subject_name_bn`, `mcq_total`, `written_total`, `practical_total`, `check_*_pass`, `*_pass_mark`, `total_marks`, `pass_mark`, `contributes_to_target`, soft delete |
| `exam_schedules` | `exam_id`, `subject_id` (exam-এ unique), `exam_date` |
| `subject_merges` | class default merge (first/second subject, rule override nullable) |
| `exam_subject_merges` | exam-এর merge — rule fully resolved; auto paper merge চিহ্নিত |
| `student_results` | `exam_id`, `subject_id`, `student_id`, `class_id`, `mcq_marks`, `written_marks`, `practical_marks`, `total_marks`, `is_absent`, `is_theory_absent`, `is_practical_absent`, `final_marks`, `final_full_marks`, `result_breakdown` |
| `student_merit_rankings` | `exam_id`, `student_id`, `section_id`, `total_marks`, `gpa`, `grade`, `class_rank`, `section_rank` |
| `result_rules`, `result_rule_components`, `result_rule_classes` | rule, component (`exam_type_id`, `weight_percent`, `must_pass`, `sort_order`), class pivot |
| `grade_scales` | `letter_grade`, `min_mark`, `max_mark`, `grade_point`, `color` |
| `exam_notification_batches` | প্রতিটা Send Marks / Send Result batch-এর রেকর্ড |
| `student_class_history` | promotion / academic history |

## ১.৩ Permission ও Authorization

Enum: `AcademicPermission` — `view_academic`, `create_academic`, `edit_academic`, `delete_academic`, `force_delete_academic`, `restore_academic`, `send_exam_notifications`। Trait: `app/Traits/Permissions/HasAcademicPermissions.php`।

**সতর্কতা — বর্তমান অবস্থা:** Academic Structure-এর কোনো Resource বা Page আসলে এই permission চেক করে না।

| Class | অবস্থা |
|---|---|
| `GroupResource` | `// use HasAcademicPermissions;` — comment করা |
| `SubjectResource`, `ClassesResource`, `ExamTypeResource`, `ExamResource`, `ResultRuleResource` | কোনো `can*()` / `get*AuthorizationResponse()` override নেই, কোনো Policy নেই |
| `Pages\EnterStudentMarks` (admin) | `canAccess()` নেই — শুধু query param চেক করে |
| `Pages\GradeScaleSettings` | `canAccess()` নেই |

ফলাফল: Admin প্যানেলে ঢুকতে পারে এমন যেকোনো ইউজার (staff সহ, কোনো permission ছাড়াই) বিষয়, ক্লাস, পরীক্ষা, Result Rule, গ্রেডিং স্কেল তৈরি, সম্পাদনা ও মুছতে পারে, আর যেকোনো মার্কস বদলাতে পারে। (যাচাই: permission ছাড়া একজন staff ইউজারের জন্য `SubjectResource::canViewAny()` ও `canCreate()` দুটোই `true`।)

ব্যতিক্রম: `ExamNotificationActions::canSend()` ঠিকভাবে `send_exam_notifications` এবং admin panel চেক করে।

সমাধানের দিক: Filament 5-এ `get{ViewAny,Create,Edit,Delete,...}AuthorizationResponse()` override করুন (শুধু `canCreate()` নয় — memory note দেখুন) অথবা Model Policy যোগ করুন; custom page-এ `canAccess()`।

Teacher প্যানেলে marks entry ঠিকভাবে সীমিত — `Teacher\Pages\EnterStudentMarks::teacherCanEnterMarks()` → `TeacherProfile::isAssignedToTeach($classId, $subjectId, $exam->session_year)`, না হলে 403। Teacher প্যানেলের SubjectConfigs table-এ Create/Delete bulk action `isAdminPanel()` দিয়ে লুকানো।

## ১.৪ Subject ও Paper

- `parent_subject_id` দিয়ে paper → main subject। Main subject-এর paper থাকলে তাকে অন্য subject-এর paper বানানো যায় না (form-এ disabled)।
- Class syllabus-এ শুধু main subject থাকলেই paper-গুলো exam-এ ব্যবহারযোগ্য (`Subject::taughtInClass()`)।
- Paper student-এর optional selection ও compulsory/optional type parent থেকে inherit করে।
- একই exam-এ parent ও তার paper একসাথে নয় — `SubjectConfigsRelationManager::paperConflict()`।
- Subject list reorderable (`sort_order`) — marksheet ও result-এর বিষয়ক্রম।
- Import: `ImportSubjectsFromSpreadsheetAction::COLUMNS`, `MAX_ROWS = 300`, all-or-nothing; ফাইল ৫ MB; নতুন subject বিদ্যমানগুলোর পরে `sort_order` পায়।

## ১.৫ Class syllabus ও eligible students

`ResolveEligibleStudentsForSubject::execute($classId, $subjectId)` — marks sheet-এ কোন student আসবে:

- class-এর active student
- subject নির্দিষ্ট group-এর হলে শুধু সেই group-এর student
- optional subject হলে শুধু যারা `student_optional_subjects`-এ বেছে নিয়েছে

Optional-এর role (`main_optional` / `extra_optional`) GPA হিসাবে আলাদা আচরণ পায় (১.১২)। `ResolveIsExtraOptionalSubject` paper-এর ক্ষেত্রে parent-এর role দেখে।

## ১.৬ Academic session

`App\Support\AcademicSession` — একমাত্র উৎস:

- `session_year` = session যে বছরে **শুরু** হয়
- School class: জানুয়ারি–ডিসেম্বর, label `2026`
- College class (`ClassLevel::College`): School Settings-এর college start month (ডিফল্ট জুলাই) থেকে, label `2026-27`
- `currentYearFor($class)`, `yearForDate()`, `periodFor()`, `label()`, `optionsFor()`
- `Classes::currentSessionYear()`, `sessionLabel()`

`ExamForm::mixesDifferentCurrentSessions()` — জানুয়ারি–জুনে college ও school-এর current session আলাদা থাকায় একসাথে multi-class exam তৈরি আটকায়।

## ১.৭ Exam lifecycle

1. **Create** (`CreateExam::handleRecordCreation`): একাধিক class বাছলে প্রতিটার জন্য `CreateExamAction` → `Exam::create` → `CopySubjectMergesToExamAction` (class default merge কপি) → published হলে `notifyExamResultPublished()`।
2. **Subject configs** যোগ — দুটো paper এক exam-এ এলে auto paper merge।
3. **Schedule** — `exam_date` exam-এর `start_date..end_date`-এর মধ্যে; `subject_id` exam-এ unique।
4. **Marks entry**
5. **Calculate Rankings** (`EditExam` header action → `CalculateExamRankings::execute`)
6. **Publish** (`EditExam::handleRecordUpdate`): শুধু `false → true` হলে notify। class বদলালে ও unpublished থাকলে merge আবার কপি।
7. **Send Marks / Send Result** — যেকোনো সময়, permission লাগে।

`EditExam` header actions: `calculateRankings`, `ExamNotificationActions::sendResult()`, `attendanceTotals` (→ `ExamAttendanceTotals?examId=`), Delete/ForceDelete/Restore।

Notification settings (`exams` কলাম): `subject_marks_channels` (null = push only), `result_channels` (null = push + SMS), `notify_result_on_publish` (default true)। `ExamForm::channelsField()` null হলে default দিয়ে hydrate করে।

## ১.৮ Marks entry ও StudentResult

`EnterStudentMarks`:

- URL param: `examId`, `subjectId`, `classId` (তিনটাই লাগবে, না হলে 404)
- `marksAbsencePerPart()` — subject-এ practical **এবং** (MCQ বা written) থাকলে absence অংশভিত্তিক (theory / practical)
- Validation: প্রতি অংশ `nullable|numeric|min:0|max:{config total}`
- Save: একটা query-তে existing row লোড, তারপর fill + save (updateOrCreate-এর N query এড়াতে)

`StudentResult::booted()` saving hook:

- `is_absent` → দুই অংশই absent
- দুই অংশ absent → `is_absent = true` (যদি না `is_absent` নিজে বন্ধ করা হয়)
- theory absent → `mcq_marks`, `written_marks` null; practical absent → `practical_marks` null
- `total_marks` = তিন অংশের যোগ (২ দশমিক)

`StudentResult::markEntryAttributes()` form state → attributes রূপান্তর (view ও save দুই জায়গায় একই)।

`effective_marks` — weighted result থাকলে `final_marks`, না হলে `total_marks`।

## ১.৯ Subject outcome (pass/fail/grade)

`ResolveSubjectOutcome::execute($result, $config, $gradeScales)` → `{percentage, grade, is_fail}` — ranking ও marksheet দুটোই এটা ব্যবহার করে, তাই কখনো অমিল হয় না।

- Weighted result: percentage F হলে অথবা `must_pass` component-এ pass না হলে fail
- নিজের exam-এর result: absent (যেকোনো অংশে), F grade, overall `pass_mark` না পেলে, অথবা `check_*_pass` চালু অংশে pass mark না পেলে fail
- Fail হলে grade সবসময় F

`GradeScale::fromMarks()` — **শুধু `min_mark` দেখে, বড় থেকে ছোট cascade** (`marks >= min_mark`, প্রথম match)। `max_mark` শুধু দেখানোর জন্য। এটা ইচ্ছাকৃত — 79.5-এর মতো ফাঁকে পড়া নম্বর যেন grade হারায় না। `max_mark`-কে lookup-এ আনা বা gap validation যোগ করার আগে product owner-এর সাথে কথা বলুন। `fromGpa()` একইভাবে `grade_point` desc-এ।

ডিফল্ট scale migration `2026_08_25_120000_create_grade_scales_table`-এ seed হয়: A+ ৮০/৫.০, A ৭০/৪.০, A- ৬০/৩.৫, B ৫০/৩.০, C ৪০/২.০, D ৩৩/১.০, F ০/০।

`GradeScaleSettings` save: অন্তত একটা row লাগবে; `min_mark > max_mark` হলে আটকায়।

## ১.১০ Subject merge

- Class default: `subject_merges` (`Classes → SubjectMergesRelationManager`); rule কলাম nullable = School Settings default
- Exam copy: `exam_subject_merges`, rule fully resolved; `CopySubjectMergesToExamAction` exam তৈরির সময়; `SyncClassSubjectMergesAction` class default বদলালে যেসব exam `has_custom_subject_merges = false` ও unpublished সেগুলোতে sync
- Exam-এ নিজে বদলালে `has_custom_subject_merges = true`; "Apply Class Default" action আবার কপি করে
- সর্বোচ্চ ২টা subject এক merge-এ (`MergesTwoSubjects`)
- Auto paper merge: একই parent-এর দুটো paper exam-এ এলে parent-এর নামে তৈরি; একটা paper সরালে মুছে যায়; admin মুছে দিলে আর ফেরে না (শুধু Apply Class Default-এ ফেরে)
- Student-এর group যদি দুটো paper-ই না নেয় (syllabus অনুযায়ী, result অনুযায়ী নয়), merge খাটে না — paper আলাদা গোনা হয়

Math (`ResolveMergedSubjectOutcome` — একমাত্র জায়গা):

```
merged % = (p1 + p2) / (full1 + full2) × 100  → rounding rule
grade    = rounded % থেকে
merit total-এ merged (converted) marks যায়, raw sum নয়
GPA-তে merge একটা subject
```

Rules: `MergedPassRule` (combined / each_paper), `MergedComponentPassRule` (combined / each_paper — শুধু combined pass-এ), `MergedAbsentRule` (fail / count_as_zero), `ResultRounding` (decimal / nearest / up / down)।

## ১.১১ Weighted result (Result Rule)

`CalculateWeightedResults` (`CalculateExamRankings`-এর ভেতর থেকে চলে):

- Exam-এর type যদি class-এর ResultRule-এর `target_exam_type_id` হয়
- প্রতি student × subject: প্রতিটা component exam type-এ student-এর percentage; একই type-এর একাধিক exam → `count_method` (all = গড়, best_n = সেরা N-এর গড়); absent = ০
- যে component-এ subject-এর exam-ই হয়নি বা `contributes_to_target = false`, তার weight বাকিদের মধ্যে আনুপাতিক ভাগ: `effectiveWeight = weight / heldWeight × 100`
- Weighted sum rule-এর full marks-এ, শুধু শেষে round → `student_results.final_marks`, প্রতিটা component `result_breakdown`-এ
- প্রতিবার শুরু থেকে হিসাব — পুরনো weighted result থাকে না
- Published হলে exam-এ রাখা rule snapshot দিয়ে হিসাব, পরে rule বদলালেও না বদলায়

Validation (`ResultRule::componentsProblem()`): duplicate exam type নয়, মোট ঠিক ১০০%, target type অবশ্যই component। `classesTakenByOtherRules()` — এক class এক rule। `resolvedFullMarks()` → `full_marks` অথবা School Settings `result_full_marks` (ডিফল্ট ১০০)।

## ১.১২ Ranking ও GPA

`CalculateExamRankings::execute(Exam)`:

1. Weighted result হিসাব
2. `summarizeStudents()` — প্রতি student `total_marks`, `gpa`, `is_overall_fail`, `is_fully_absent`, `failed_subject_count`, `failed_subject_keys` (`subject:{id}` / `merge:{id}`)। Exam result report-ও এটা ব্যবহার করে।
3. Ranking save + activity log `merit_ranking`

GPA:

```
GPA = min(5.00, (Σ GP of compulsory + main_optional units + additionalBonus) / count(compulsory + main_optional units))
additionalBonus = max(0, GP_extra − 2.0)          // ADDITIONAL_SUBJECT_GRADE_POINT_DEDUCTION
extra total     = max(0, marks − full × 40%)      // ADDITIONAL_SUBJECT_MARKS_DEDUCTION_PERCENTAGE
compulsory/main_optional fail বা absent → is_overall_fail, GPA = 0
extra optional fail → কোনো প্রভাব নেই
```

Rank (`calculateTieredRanks`):

- Tier 1: overall pass → GPA desc, তারপর total_marks desc
- Tier 2: overall fail → একই, tier 1-এর পরে offset
- RANK() style ties (১, ২, ২, ৪) — GPA ও total দুটোই সমান হলে
- সব subject-এ absent → rank নেই
- Section rank একই নিয়মে section-এর ভেতরে

## ১.১৩ Exam notification (marks / result)

`app/Filament/Resources/Exams/Actions/ExamNotificationActions.php` + `app/Actions/ExamNotifications/*`:

- `canSend()` = admin panel **এবং** `send_exam_notifications`
- `sendSubjectMarks()` (row), `sendSeveralSubjectMarks()` (header, subject checklist → প্রতি student এক মেসেজ), `sendResult()` (EditExam header; ranking লাগবে)
- Preview: recipients, push, SMS count / parts / cost বনাম balance, ফোন নম্বর নেই এমন, মার্কস নেই এমন, শেষ পাঠানোর সময়, নমুনা
- SMS অভিভাবকের নম্বরে (`BuildsStudentSms::studentPhone` — নিজের নম্বরে fallback); `SmsSetting` event (`ExamSubjectMarks` / `ExamResult`) চালু থাকতে হবে; `QueueSmsBatchAction` দিয়ে all-or-nothing
- প্রতিটা পাঠানো `exam_notification_batches`-এ — `marks_last_sent_at` কলাম এখান থেকে
- Resend allowed (সতর্কবার্তা সহ)
- Notification class: `ExamMarksNotification`, `ExamResultPublishedNotification`; trait `NotifiesExamResultPublished`
- `{failed_subjects}` placeholder ইচ্ছাকৃতভাবে বাদ (optional subject rule-এর কারণে গণনা নির্ভরযোগ্য নয়)

## ১.১৪ PDF

| PDF | Route | Action |
|---|---|---|
| Subject list | `subject-list.download?columns[]=&orientation=P\|L` | `BuildSubjectListPdfAction` |
| Exam schedule | `exams.schedule.download` (`pageSize` A5/A4, `subjectLanguage` en/bn) | `BuildExamSchedulePdfAction` |
| Tabulation sheet | `exams.tabulation-sheet.download` (orientation, pageSize, subjectLanguage) | `BuildExamTabulationSheetPdfAction` + `BuildExamTabulationSheetDetail` |
| Result report | `exams.result-report.download` | `BuildExamResultReportPdfAction` + `BuildExamResultReport` |

mPDF সীমাবদ্ধতা: table cell-এ border-radius নেই, rowspan height ছড়িয়ে দেয় — floated div ব্যবহার করুন, hardcoded height নয়।

## ১.১৫ Activity log

- `ExamType` তৈরি: logging বন্ধ করে save, তারপর `activity('exam_type')` দিয়ে config সহ একটা custom log
- `StudentResult` নিজের label সহ log (student label cache `ActivityLogLabelCache`)
- Merit ranking calculate → `activity('merit_ranking')`
- টেস্ট: `ActivityLogExamTest`, `ActivityLogExamTypeActionTest`, `ActivityLogExamSubjectConfigTest`, `ActivityLogStudentResultTest`, `ActivityLogMeritRankingTest`, `ActivityLogClassRelationsTest`

## ১.১৬ Artisan command

| Command | কাজ |
|---|---|
| `php artisan create:class` | প্রতিটা level-এর (pre-primary / primary / secondary / college) ডিফল্ট ক্লাস তালিকা `firstOrCreate` দিয়ে — বিদ্যমান ক্লাস আবার তৈরি হয় না |
| `php artisan create:subject` | primary / secondary / college-এর ডিফল্ট বিষয় তালিকা |
| `php artisan attach:subject` | Science / Commerce / Humanities group তৈরি, ক্লাসে link, এবং প্রতিটা বিষয় class + group-এ attach |

নতুন স্কুল দ্রুত সেটআপের জন্য এই তিনটা ক্রমানুসারে চালানো যায়। ডেটা আসে `ClassDefinitions`, `SubjectDefinitions`, `ClassGroupSubjectDefinitions` থেকে।

ব্যবহারের আগে `php artisan <command> --help` দেখুন।

## ১.১৭ টেস্ট

`CalculateExamRankingsOptionalSubjectTest`, `CalculateExamRankingsPassFailTierTest`, `CalculateWeightedResultsTest`, `CollegeSubjectPapersTest`, `CollegeSessionDefaultsTest`, `EnterStudentMarksValidationTest`, `ExamMarksNotificationTest`, `ExamResultPublishedNotificationTest`, `ExamResultReportTest`, `ExamScheduleRelationManagerTest`, `ExamSubjectConfigApplicableToggleTest`, `ExamSubjectNameTest`, `ExamTabulationSheetTest`, `GradeScaleSettingsTest`, `GradeScaleTest`, `MeritRankingsRelationManagerTest`, `PartAbsenceMarksEntryTest`, `PartialGroupMergeTest`, `ResolveEligibleStudentsForSubjectTest`, `ResolveMergedSubjectOutcomeTest`, `ResultRuleResourceTest`, `StudentResultPassFailRulesTest`, `SubjectMergeResultTest`, `SubjectMergesRelationManagerTest`, `SubjectOrderTest`, `SubjectSpreadsheetImportTest`, `TeacherEnterMarksRestrictionTest`, `TeacherExamClassListTest`, `ViewExamResultMarksDetailTest`, `CreateClassCommandTest`, `CreateSubjectCommandTest`, `AttachSubjectCommandTest`।

## ১.১৮ জানা সমস্যা ও সতর্কতা

1. **Authorization নেই** (১.৩) — Academic Structure-এর সব Resource, admin `EnterStudentMarks` ও `GradeScaleSettings`।
2. `ClassesTable`-এ row-level Delete action নেই, কিন্তু bulk Delete/ForceDelete/Restore আছে।
3. `Classes → SubjectsRelationManager`-এর bulk action-এ `DeleteBulkAction` আছে, যা pivot নয়, **`subjects` রেকর্ড নিজেই** soft delete করে (সব ক্লাস থেকে বিষয় হারিয়ে যায়)। সম্ভবত শুধু `DetachBulkAction` থাকা উচিত।
4. `ExamForm`-এর Class select `Classes::pluck()` — inactive class-ও দেখায়; `ListExams` card শুধু active class দেখায়।
5. Exam publish হওয়ার পর marks entry বন্ধ হয় না — published exam-এ মার্কস বদলালে আবার Calculate Rankings না করা পর্যন্ত ranking পুরনো থাকে।
6. `GradeScale` lookup ইচ্ছাকৃতভাবে `max_mark` উপেক্ষা করে (১.৯) — "ঠিক" করবেন না।
