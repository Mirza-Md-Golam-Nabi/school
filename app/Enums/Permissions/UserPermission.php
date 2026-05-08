<?php

namespace App\Enums\Permissions;

enum UserPermission: string
{
    case VIEW_USERS = 'view_users';
    case CREATE_USERS = 'create_users';
    case EDIT_USERS = 'edit_users';
    case DELETE_USERS = 'delete_users';

}
