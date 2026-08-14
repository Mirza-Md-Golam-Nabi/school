<?php

namespace App\Enums\Permissions;

enum DocumentPermission: string
{
    case VIEW_ADMIT_CARDS = 'view_admit_cards';
    case CREATE_ADMIT_CARDS = 'create_admit_cards';
    case EDIT_ADMIT_CARDS = 'edit_admit_cards';
    case DELETE_ADMIT_CARDS = 'delete_admit_cards';

    case VIEW_MARKSHEETS = 'view_marksheets';
    case CREATE_MARKSHEETS = 'create_marksheets';
    case EDIT_MARKSHEETS = 'edit_marksheets';
    case DELETE_MARKSHEETS = 'delete_marksheets';
}
