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
        };
    }

    public function source(): SmsSource
    {
        return match ($this) {
            self::AttendancePresent, self::AttendanceAbsent, self::DeviceEntry, self::DeviceExit => SmsSource::Attendance,
            self::FeeInvoiceGenerated, self::FeePaymentReceived => SmsSource::Fee,
            self::Notice => SmsSource::Notice,
        };
    }

    public function defaultTemplate(): string
    {
        return match ($this) {
            self::AttendancePresent => 'প্রিয় অভিভাবক, {name} {date} তারিখে স্কুলে উপস্থিত। - {school}',
            self::AttendanceAbsent => 'প্রিয় অভিভাবক, {name} {date} তারিখে স্কুলে অনুপস্থিত। - {school}',
            self::DeviceEntry => 'প্রিয় অভিভাবক, {name} {time} সময়ে স্কুলে প্রবেশ করেছে। - {school}',
            self::DeviceExit => 'প্রিয় অভিভাবক, {name} {time} সময়ে স্কুল থেকে বের হয়েছে। - {school}',
            self::FeeInvoiceGenerated => 'প্রিয় অভিভাবক, {name} (ID: {student_id})-এর {fee_types} বাবদ {amount} টাকা ফি ধার্য হয়েছে। - {school}',
            self::FeePaymentReceived => 'প্রিয় অভিভাবক, {name} (ID: {student_id})-এর {amount} টাকা ফি গ্রহণ করা হয়েছে। রসিদ নং: {receipt_no} - {school}',
            self::Notice => '{school}: নতুন নোটিশ - {title}',
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
            self::FeePaymentReceived => $student + ['{amount}' => 'পরিশোধিত টাকা', '{receipt_no}' => 'রসিদ নং', '{date}' => 'তারিখ'] + $school,
            self::Notice => ['{title}' => 'নোটিশের শিরোনাম', '{body}' => 'নোটিশের লেখা'] + $school,
        };
    }
}
