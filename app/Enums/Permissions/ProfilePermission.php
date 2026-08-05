<?php

namespace App\Enums\Permissions;

enum ProfilePermission: string
{
    case VIEW_STUDENT_PROFILES = 'view_student_profiles';
    case CREATE_STUDENT_PROFILES = 'create_student_profiles';
    case EDIT_STUDENT_PROFILES = 'edit_student_profiles';
    case DELETE_STUDENT_PROFILES = 'delete_student_profiles';
    case RESTORE_STUDENT_PROFILES = 'restore_student_profiles';
    case FORCE_DELETE_STUDENT_PROFILES = 'force_delete_student_profiles';

    case VIEW_TEACHER_PROFILES = 'view_teacher_profiles';
    case CREATE_TEACHER_PROFILES = 'create_teacher_profiles';
    case EDIT_TEACHER_PROFILES = 'edit_teacher_profiles';
    case DELETE_TEACHER_PROFILES = 'delete_teacher_profiles';
    case RESTORE_TEACHER_PROFILES = 'restore_teacher_profiles';
    case FORCE_DELETE_TEACHER_PROFILES = 'force_delete_teacher_profiles';

    case VIEW_STAFF_PROFILES = 'view_staff_profiles';
    case CREATE_STAFF_PROFILES = 'create_staff_profiles';
    case EDIT_STAFF_PROFILES = 'edit_staff_profiles';
    case DELETE_STAFF_PROFILES = 'delete_staff_profiles';
    case RESTORE_STAFF_PROFILES = 'restore_staff_profiles';
    case FORCE_DELETE_STAFF_PROFILES = 'force_delete_staff_profiles';
}
