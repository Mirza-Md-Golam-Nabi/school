<?php

namespace App\Enums\Permissions;

enum SmsPermission: string
{
    case MANAGE_SMS_SETTINGS = 'manage_sms_settings';
    case VIEW_SMS_LOGS = 'view_sms_logs';
}
