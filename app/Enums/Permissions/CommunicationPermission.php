<?php

namespace App\Enums\Permissions;

enum CommunicationPermission: string
{
    case VIEW_NOTICES = 'view_notices';
    case CREATE_NOTICES = 'create_notices';
    case EDIT_NOTICES = 'edit_notices';
    case DELETE_NOTICES = 'delete_notices';
}
