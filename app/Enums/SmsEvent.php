<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * Every situation the school can send an SMS for. Each one is switched on or
 * off by the admin in SMS Settings and has its own editable template.
 */
enum SmsEvent: string implements HasLabel
{
    case AttendancePresent = 'attendance_present';
    case AttendanceAbsent = 'attendance_absent';
    case DeviceEntry = 'device_entry';
    case DeviceExit = 'device_exit';
    case FeeInvoiceGenerated = 'fee_invoice_generated';
    case FeePaymentReceived = 'fee_payment_received';
    case Notice = 'notice';
    case ExamSubjectMarks = 'exam_subject_marks';
    case ExamResult = 'exam_result';

    public function getLabel(): string
    {
        return match ($this) {
            self::AttendancePresent => 'Attendance: Present',
            self::AttendanceAbsent => 'Attendance: Absent',
            self::DeviceEntry => 'Device: School Entry',
            self::DeviceExit => 'Device: School Exit',
            self::FeeInvoiceGenerated => 'Fee Generated',
            self::FeePaymentReceived => 'Fee Payment Received',
            self::Notice => 'Notice',
            self::ExamSubjectMarks => 'Exam: Subject Marks',
            self::ExamResult => 'Exam: Result',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::AttendancePresent => 'হাতে উপস্থিতি নেওয়ার সময় শিক্ষার্থী উপস্থিত হলে অভিভাবককে SMS (শুধু আজকের তারিখের জন্য)।',
            self::AttendanceAbsent => 'শিক্ষার্থী অনুপস্থিত হলে অভিভাবককে SMS — হাতে নেওয়া ও ডিভাইসের স্বয়ংক্রিয় অনুপস্থিতি দুটোতেই (শুধু আজকের তারিখের জন্য)।',
            self::DeviceEntry => 'ডিভাইসে দিনের প্রথম পাঞ্চে (স্কুলে প্রবেশ) অভিভাবককে SMS।',
            self::DeviceExit => 'ডিভাইসে স্কুল থেকে বের হওয়ার পাঞ্চে অভিভাবককে SMS।',
            self::FeeInvoiceGenerated => 'ফি জেনারেট হলে অভিভাবককে SMS — প্রতি শিক্ষার্থীর জন্য একটা SMS-এ মোট টাকা।',
            self::FeePaymentReceived => 'ফি পরিশোধ হলে অভিভাবককে SMS — এক রসিদে একটা SMS।',
            self::Notice => 'নোটিশ প্রকাশের সময় "Send SMS" চালু থাকলে প্রাপকদের SMS।',
            self::ExamSubjectMarks => 'পরীক্ষার ভেতর থেকে admin বিষয়ের মার্ক পাঠালে (চ্যানেলে SMS বাছাই থাকলে) অভিভাবককে SMS — কয়েকটা বিষয় একসাথে পাঠালে এক SMS-এ।',
            self::ExamResult => 'পরীক্ষার সম্পূর্ণ রেজাল্ট পাঠালে (চ্যানেলে SMS বাছাই থাকলে) অভিভাবককে SMS — GPA, গ্রেড, মোট নম্বর ও মেধাক্রম।',
        };
    }

    public function source(): SmsSource
    {
        return match ($this) {
            self::AttendancePresent, self::AttendanceAbsent, self::DeviceEntry, self::DeviceExit => SmsSource::Attendance,
            self::FeeInvoiceGenerated, self::FeePaymentReceived => SmsSource::Fee,
            self::Notice => SmsSource::Notice,
            self::ExamSubjectMarks, self::ExamResult => SmsSource::Exam,
        };
    }

    /**
     * The template used while the school hasn't written its own, in the
     * school's SMS language. English defaults stay plain ASCII so they bill
     * at 160 characters per part.
     */
    public function defaultTemplate(SmsLanguage $language = SmsLanguage::Bangla): string
    {
        if ($language === SmsLanguage::English) {
            return match ($this) {
                self::AttendancePresent => 'Dear guardian, {name} is present at school on {date}. - {school}',
                self::AttendanceAbsent => 'Dear guardian, {name} is absent from school on {date}. - {school}',
                self::DeviceEntry => 'Dear guardian, {name} entered school at {time}. - {school}',
                self::DeviceExit => 'Dear guardian, {name} left school at {time}. - {school}',
                self::FeeInvoiceGenerated => 'Dear guardian, fee of Tk {amount} for {fee_types} is due for {name} (ID: {student_id}). - {school}',
                self::FeePaymentReceived => 'Dear guardian, fee of Tk {amount} for {name} (ID: {student_id}) received. Receipt: {receipt_link} - {school}',
                self::Notice => '{school}: New notice - {title}',
                self::ExamSubjectMarks => 'Dear guardian, {name} ({exam}) marks: {marks} - {school}',
                self::ExamResult => 'Dear guardian, {name} ({exam}) result: GPA {gpa} ({grade}), Total {total_marks}, Position {position}. - {school}',
            };
        }

        return match ($this) {
            self::AttendancePresent => 'প্রিয় অভিভাবক, {name} {date} তারিখে স্কুলে উপস্থিত। - {school}',
            self::AttendanceAbsent => 'প্রিয় অভিভাবক, {name} {date} তারিখে স্কুলে অনুপস্থিত। - {school}',
            self::DeviceEntry => 'প্রিয় অভিভাবক, {name} {time} সময়ে স্কুলে প্রবেশ করেছে। - {school}',
            self::DeviceExit => 'প্রিয় অভিভাবক, {name} {time} সময়ে স্কুল থেকে বের হয়েছে। - {school}',
            self::FeeInvoiceGenerated => 'প্রিয় অভিভাবক, {name} (ID: {student_id})-এর {fee_types} বাবদ {amount} টাকা ফি ধার্য হয়েছে। - {school}',
            self::FeePaymentReceived => '{name} (ID: {student_id})-এর {amount} টাকা ফি গ্রহণ করা হয়েছে। রসিদ: {receipt_link} - {school}',
            self::Notice => '{school}: নতুন নোটিশ - {title}',
            self::ExamSubjectMarks => 'প্রিয় অভিভাবক, {name}-এর {exam} পরীক্ষার নম্বর: {marks} - {school}',
            self::ExamResult => 'প্রিয় অভিভাবক, {name}-এর {exam} পরীক্ষার ফলাফল: GPA {gpa} ({grade}), মোট নম্বর {total_marks}, মেধাক্রম {position}। - {school}',
        };
    }

    /**
     * The placeholders this event's template may use, with what each becomes.
     *
     * @return array<string, string>
     */
    public function placeholders(): array
    {
        $student = [
            '{name}' => 'শিক্ষার্থীর নাম',
            '{name_bn}' => 'শিক্ষার্থীর বাংলা নাম',
            '{student_id}' => 'Student ID',
        ];

        $school = ['{school}' => 'স্কুলের নাম'];

        return match ($this) {
            self::AttendancePresent, self::AttendanceAbsent => $student + ['{class}' => 'ক্লাস', '{date}' => 'তারিখ'] + $school,
            self::DeviceEntry, self::DeviceExit => $student + ['{class}' => 'ক্লাস', '{date}' => 'তারিখ', '{time}' => 'সময়'] + $school,
            self::FeeInvoiceGenerated => $student + ['{amount}' => 'মোট টাকা', '{fee_types}' => 'ফি-এর ধরন'] + $school,
            self::FeePaymentReceived => $student + ['{amount}' => 'পরিশোধিত টাকা', '{receipt_no}' => 'রসিদ নং', '{receipt_link}' => 'রসিদ যাচাইয়ের লিংক', '{date}' => 'তারিখ'] + $school,
            self::Notice => ['{title}' => 'নোটিশের শিরোনাম', '{body}' => 'নোটিশের লেখা'] + $school,
            self::ExamSubjectMarks => $student + ['{class}' => 'ক্লাস', '{roll}' => 'রোল', '{exam}' => 'পরীক্ষার নাম', '{marks}' => 'বিষয়ের নম্বর (যেমন: গণিত 72/100 (A))'] + $school,
            self::ExamResult => $student + ['{class}' => 'ক্লাস', '{roll}' => 'রোল', '{exam}' => 'পরীক্ষার নাম', '{gpa}' => 'GPA', '{grade}' => 'গ্রেড', '{total_marks}' => 'মোট নম্বর', '{position}' => 'মেধাক্রম'] + $school,
        };
    }
}
