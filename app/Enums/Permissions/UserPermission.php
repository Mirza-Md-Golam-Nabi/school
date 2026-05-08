<?php

namespace App\Enums\Permissions;

enum UserPermission: string
{
    case VIEW_USERS = 'view_users';
    case CREATE_USERS = 'create_users';
    case EDIT_USERS = 'edit_users';
    case DELETE_USERS = 'delete_users';

    case VIEW_ROLES = 'view_roles';
    case CREATE_ROLES = 'create_roles';
    case EDIT_ROLES = 'edit_roles';
    case DELETE_ROLES = 'delete_roles';

    case ASSIGN_ROLES = 'assign_roles';
    case ASSIGN_PERMISSIONS = 'assign_permissions';

}
