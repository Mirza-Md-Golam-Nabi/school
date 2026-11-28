# ৭. Document Management ও QR যাচাই — Developer Reference

ব্যবহারকারী নির্দেশিকা: [../../user-guide/features/07-document-management.md](../../user-guide/features/07-document-management.md)

## সূচিপত্র

- [৭.১ কোড ফাইল](#৭১-কোড-ফাইল)
- [৭.২ Database টেবিল](#৭২-database-টেবিল)
- [৭.৩ Permission](#৭৩-permission)
- [৭.৪ Admit card](#৭৪-admit-card)
- [৭.৫ Marksheet](#৭৫-marksheet)
- [৭.৬ Bundle (combined PDF)](#৭৬-bundle-combined-pdf)
- [৭.৭ ID card](#৭৭-id-card)
- [৭.৮ Seat plan ও attendance report](#৭৮-seat-plan-ও-attendance-report)
- [৭.৯ QR ও public verification](#৭৯-qr-ও-public-verification)
- [৭.১০ PDF rendering (mPDF)](#৭১০-pdf-rendering-mpdf)
- [৭.১১ School settings key](#৭১১-school-settings-key)
- [৭.১২ টেস্ট](#৭১২-টেস্ট)
- [৭.১৩ জানা সমস্যা ও সতর্কতা](#৭১৩-জানা-সমস্যা-ও-সতর্কতা)

---

## ৭.১ কোড ফাইল

| অংশ | ফাইল |
|---|---|
| Admit card | `app/Filament/Resources/AdmitCards/` — `ListAdmitCards` (exam type অনুযায়ী সব class), `ManageClassAdmitCards` (class, Download All bundle), `Schemas/AdmitCardDesignRadio`, `AdmitCardLanguageRadios`, `AdmitCardInfolist`, `Tables/AdmitCardsTable` |
| Marksheet | `app/Filament/Resources/Marksheets/` — `ListMarksheets`, `ManageClassMarksheets` (download pattern), `MarksheetsTable`, `MarksheetInfolist` |
| Pages | `app/Filament/Pages/SeatPlan`, `StudentIdCards`, `TeacherIdCards`, `StaffIdCards`, `StudentAttendanceReport` |
| Concerns | `PicksIdCardDesign`, `ConfiguresIdCardFields`, `ReissuesIdCardQr`, `ScansStudentIdCard` |
| Actions | `GenerateAdmitCardsForExamAction`, `GenerateAdmitCardsForExamTypeAction`, `BuildCombinedClassAdmitCardsPdfAction`, `GenerateMarksheetsForExamAction`, `GenerateMarksheetsForExamTypeAction`, `BuildCombinedClassMarksheetPdfAction`, `BuildStudentMarksDetail`, `BuildMarksheetVerificationAction`, `BuildClassSeatPlanPdfAction`, `BuildClassStudentIdCardsPdfAction`, `BuildTeacherIdCardsPdfAction`, `BuildStaffIdCardsPdfAction`, `BuildClassAttendanceReportPdfAction`, `BuildEmployeeAttendanceReportPdfAction`, `BuildTeacherAttendanceSheetPdfAction` |
| Jobs | `GenerateAdmitCardPdfJob`, `GenerateAdmitCardBundlePdfJob`, `GenerateMarksheetPdfJob`, `GenerateMarksheetBundlePdfJob` |
| Controllers | `AdmitCardPdfController`, `AdmitCardSamplePdfController`, `ClassAdmitCardsPdfController`, `MarksheetPdfController`, `ClassMarksheetsPdfController`, `ClassSeatPlanPdfController`, `ClassStudentIdCardsPdfController`, `TeacherIdCardsPdfController`, `StaffIdCardsPdfController`, `ClassAttendanceReportPdfController`, `EmployeeAttendanceReportPdfController`, `TeacherAttendanceSheetPdfController`, `Verify{AdmitCard,Marksheet,IdCard,FeeReceipt,Document}Controller` |
| Support | `PdfImage` (thumbnail cache), `QrCodeImage`, `IdCardLogo`, `IdCardPhotoRenderer`, `IdCardPhotoPosition`, `StudentIdCardLayout`, `TeacherIdCardLayout`, `StaffIdCardLayout`, `AdmitCardBranding`, `AdmitCardSample`, `DocumentLocale`, `BengaliNumber`, `ClassAccentColor` |
| Trait | `HasIdCardToken` |
| Views | `resources/views/documents/*` (`admit-card`, `admit-card-routine`, marksheet, id card…), `resources/views/verify-*.blade.php` |
| Enums | `AdmitCardDesign`, `MarksheetBundleType`, `StudentIdCardField`, `TeacherIdCardField`, `StaffIdCardField`, `StudentIdCardTemplate`, `StudentIdCardValidity`, `CardAccentColor` |
| Command | `PrunePdfThumbnails` (রবিবার ০২:৩০) |
| Models | `AdmitCard`, `AdmitCardBundle`, `Marksheet`, `MarksheetBundle` |

## ৭.২ Database টেবিল

| টেবিল | নোট |
|---|---|
| `admit_cards` | `student_id`, `exam_id`, `verification_token` (৩২, unique, অনুমানযোগ্য নয়), `file_path`, `is_generated`, `file_generated_at`, design / language |
| `admit_card_bundles` | class + exam-এর combined PDF; `is_generated`, stale হলে পুনর্গঠন |
| `marksheets` | `student_id`, `exam_id`, `verification_code` (১৬, unique — QR ও হাতে টাইপ), `file_path`, `is_generated`, `file_generated_at` |
| `marksheet_bundles` | class + exam + `MarksheetBundleType` (all / passed / failed) + language |
| profile tables | `id_card_token` (Student / Teacher / Staff) — reissue-এ নতুন |
| `school_settings` | document settings ও ID card layout key |

## ৭.৩ Permission

`DocumentPermission`: admit cards ও marksheets-এর view / create / edit / delete — দুই resource `HasEntityPermissions` ব্যবহার করে।

**সতর্কতা:** `SeatPlan`, `StudentIdCards`, `TeacherIdCards`, `StaffIdCards`, `StudentAttendanceReport` page-এ `canAccess()` নেই। PDF route-গুলো শুধু `auth` middleware-এর পেছনে, কোনো permission বা panel চেক নয় — **যেকোনো লগইন করা ইউজার (শিক্ষার্থী সহ)** URL জানলে অন্য class-এর ID card, student list, attendance report ইত্যাদি ডাউনলোড করতে পারে (যেমন `/classes/{id}/student-list/download` — এতে guardian phone, birth certificate no থাকতে পারে)। প্রতিটা PDF controller-এ authorization যোগ করা দরকার।

## ৭.৪ Admit card

- `ManageClassAdmitCards` → Generate: exam (class-এর), page size A4 / A5, design (`AdmitCardDesign::Simple` → `documents.admit-card`, `WithRoutine` → `documents.admit-card-routine`), routine language, student info language
- `WithRoutine` + `exam_schedules` খালি → সতর্কবার্তা
- `ListAdmitCards` → exam type বাছাই, যেসব class-এ সেই type-এর **published** exam আছে
- `GenerateAdmitCardPdfJob` per student (queue); design বদলালে পুরনো কার্ড আবার তৈরি
- Sample PDF: `/admit-cards/sample/{design}`

## ৭.৫ Marksheet

- শুধু active student, যার ওই exam-এ `student_merit_rankings` আছে
- Generate-এর সাথে all / passed / failed তিনটা bundle
- Download pattern: bundle type অথবা `PATTERN_FAILED_BY_SUBJECT_COUNT` (from / to) — শেষেরটা `CalculateExamRankings::summarizeStudents()`-এর `failed_subject_count` দিয়ে filter
- ✓ চিহ্ন = bundle ready; নইলে on-demand build
- Layout (`BuildStudentMarksDetail` + marksheet view): লোগো বামে, student photo (না থাকলে gender placeholder), merged subject rowspan, Practical column শুধু কোনো subject-এ practical marks থাকলে, attendance summary (exam totals থাকলে সেটি), signatory (class teacher signature, principal signature, seal — `DocumentSignatoryTest`), optional watermark
- Per-exam subject name override সব document-এ

## ৭.৬ Bundle (combined PDF)

`AdmitCardBundle` / `MarksheetBundle` — `is_generated` এবং stale চেক (সদস্য কার্ড বদলালে)। Status text: তৈরি হয়নি / তৈরি হচ্ছে / পুরনো। Download-এ ready থাকলে সাথে সাথে, না হলে sync build (সময় লাগে)। Admit card bundle: A4, প্রতি পেজে দুই কলাম।

## ৭.৭ ID card

- Design: `StudentIdCardTemplate` — Royal, Emerald, Sunset, Ocean, Midnight; logo recolour (`#RRGGBB` regex); `PicksIdCardDesign` — preview draft, Submit-এ save; submit না করলে class card দেখায় না
- Fields: `ConfiguresIdCardFields` — front / back CheckboxList + order Repeater; `*IdCardLayout::MAX_FIELDS_PER_SIDE`; validity (`issue_date` / `session_end`), issue date, QR toggle
- সবসময় থাকে: photo, নাম, স্কুল তথ্য, ID, স্বাক্ষর; খালি field বাদ
- Teacher photo position: `teacher_profiles.id_card_photo_position` (`IdCardPhotoPosition`)
- Reissue: `ReissuesIdCardQr` → নতুন `id_card_token`, পুরনো QR invalid
- Fee counter: কার্ডের QR (verify URL) USB scanner বা ক্যামেরায় পড়ে student বাছাই (`FindStudentByScannedCardAction`)

## ৭.৮ Seat plan ও attendance report

- Seat plan: class card, language toggle, `seat-plan.class.download?language=`
- Attendance report: (ক) class(es) + month + year → `attendance-report.class.download`; (খ) teachers & staff নির্বাচিত + month + year → `attendance-report.employees.download` (`DownloadEmployeeAttendanceReportRequest`); (গ) সব শিক্ষক sheet → `attendance-report.teachers.download`

## ৭.৯ QR ও public verification

| Route | Lookup | দেখায় |
|---|---|---|
| `/verify/admit-card/{token}` | `admit_cards.verification_token` | student তথ্য, exam, সময়; "বৈধ প্রবেশপত্র" / student inactive / exam শেষ |
| `/verify/marksheet/{code}` | `marksheets.verification_code` | student তথ্য + বর্তমান DB-র result (`BuildMarksheetVerificationAction`) |
| `/verify/id-card/{token}` | profile `id_card_token` | role, নাম, ID; active হলে class/roll ইত্যাদি; inactive / trashed নোট |
| `/verify/receipt/{code}`, `/r/{code}` | fee payment receipt | payment তথ্য |
| `/verify?code=` | marksheet বা receipt code | ফর্ম; না পেলে notFound |

সব `throttle:30,1`, token regex `[A-Za-z0-9]{1,32}`, code `[A-Za-z0-9-]{1,20}`। Bengali digit `BengaliNumber`।

## ৭.১০ PDF rendering (mPDF)

- mPDF: table cell-এ `border-radius` কাজ করে না, `rowspan` height ছড়িয়ে দেয় → floated div; hardcoded height নয়
- Image: `PdfImage` resize ও thumbnail cache (`storage`), প্রতি ব্যবহারে touch; ৩০ দিন অব্যবহৃত হলে `PrunePdfThumbnails` মোছে
- QR: `mpdf/qrcode` → `QrCodeImage`
- Bangla font ও digit: `DocumentLocale`, `BengaliNumber`

## ৭.১১ School settings key

`DocumentSettingsSeeder`: `school_logo`, `school_address`, `school_established_year`, `school_seal`, `principal_signature`, `admit_card_footer_text`, `marksheet_use_watermark` ইত্যাদি। ID card layout / design key-ও `school_settings`-এ। বিস্তারিত [School Settings](13-school-settings.md)।

## ৭.১২ টেস্ট

`AdmitCardBundleTest`, `AdmitCardRoutineTest`, `GenerateAdmitCardPdfJobTest`, `GenerateAdmitCardsForExamActionTest`, `GenerateAdmitCardsForExamTypeActionTest`, `ManageClassAdmitCardsDownloadTest`, `VerifyAdmitCardTest`, `GenerateMarksheetBundlePdfJobTest`, `GenerateMarksheetPdfJobTest`, `GenerateMarksheetsForExamActionTest`, `GenerateMarksheetsForExamTypeActionTest`, `ManageClassMarksheetsDownloadTest`, `MarksheetFilamentPagesSmokeTest`, `MarksheetHeaderAndPhotoTest`, `VerifyMarksheetTest`, `DocumentSignatoryTest`, `BuildCombinedClassAdmitCardsPdfActionTest`, `BuildCombinedClassMarksheetPdfActionTest`, `BuildClassSeatPlanPdfActionTest`, `BuildClassStudentIdCardsPdfActionTest`, `IdCardQrTest`, `StaffIdCardTest`, `TeacherIdCardTest`, `PdfImageTest`, `ActivityLogAdmitCardGenerationTest`, `ActivityLogMarksheetGenerationTest`।

## ৭.১৩ জানা সমস্যা ও সতর্কতা

1. PDF route ও document page-গুলোতে authorization নেই (৭.৩) — data exposure ঝুঁকি।
2. CLAUDE.md-তে `transfer_certificates` ও `document_settings` টেবিল তালিকাভুক্ত, কিন্তু Transfer Certificate (TC) feature বাস্তবায়িত নয় (কোনো model / resource নেই); document settings `school_settings`-এ রাখা হয়।
3. বাকি QR পরিকল্পনা `docs/qr-code-plan.md`-এ।
