<?php

namespace App\Enums\Permissions;

enum AcademicPermission: string
{
    case VIEW_ACADEMIC         = 'view_academic';
    case CREATE_ACADEMIC       = 'create_academic';
    case EDIT_ACADEMIC         = 'edit_academic';
    case DELETE_ACADEMIC       = 'delete_academic';
    case FORCE_DELETE_ACADEMIC = 'force_delete_academic';
    case RESTORE_ACADEMIC      = 'restore_academic';
}
