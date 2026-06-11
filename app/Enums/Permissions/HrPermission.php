<?php

namespace App\Enums\Permissions;

enum HrPermission: string
{
    case VIEW_LEAVE_TYPES = 'view_leave_types';
    case CREATE_LEAVE_TYPES = 'create_leave_types';
    case EDIT_LEAVE_TYPES = 'edit_leave_types';
    case DELETE_LEAVE_TYPES = 'delete_leave_types';

    case VIEW_LEAVE_APPLICATIONS = 'view_leave_applications';
    case CREATE_LEAVE_APPLICATIONS = 'create_leave_applications';
    case APPROVE_LEAVE_APPLICATIONS = 'approve_leave_applications';
    case REJECT_LEAVE_APPLICATIONS = 'reject_leave_applications';

    case VIEW_PUBLIC_HOLIDAYS = 'view_public_holidays';
    case CREATE_PUBLIC_HOLIDAYS = 'create_public_holidays';
    case EDIT_PUBLIC_HOLIDAYS = 'edit_public_holidays';
    case DELETE_PUBLIC_HOLIDAYS = 'delete_public_holidays';

    case VIEW_ACTING_ADMINS = 'view_acting_admins';
    case CREATE_ACTING_ADMINS = 'create_acting_admins';
    case EDIT_ACTING_ADMINS = 'edit_acting_admins';
    case DELETE_ACTING_ADMINS = 'delete_acting_admins';

    case VIEW_SCHOOL_SETTINGS = 'view_school_settings';
    case EDIT_SCHOOL_SETTINGS = 'edit_school_settings';
}
