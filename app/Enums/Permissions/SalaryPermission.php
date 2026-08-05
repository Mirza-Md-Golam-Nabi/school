<?php

namespace App\Enums\Permissions;

enum SalaryPermission: string
{
    case VIEW_SALARY_COMPONENTS = 'view_salary_components';
    case CREATE_SALARY_COMPONENTS = 'create_salary_components';
    case EDIT_SALARY_COMPONENTS = 'edit_salary_components';
    case DELETE_SALARY_COMPONENTS = 'delete_salary_components';

    case VIEW_SALARY_STRUCTURES = 'view_salary_structures';
    case CREATE_SALARY_STRUCTURES = 'create_salary_structures';
    case EDIT_SALARY_STRUCTURES = 'edit_salary_structures';
    case DELETE_SALARY_STRUCTURES = 'delete_salary_structures';

    case VIEW_SALARY_INVOICES = 'view_salary_invoices';
    case CREATE_SALARY_INVOICES = 'create_salary_invoices';
    case EDIT_SALARY_INVOICES = 'edit_salary_invoices';
    case DELETE_SALARY_INVOICES = 'delete_salary_invoices';

    case VIEW_SALARY_PAYMENTS = 'view_salary_payments';
    case CREATE_SALARY_PAYMENTS = 'create_salary_payments';
    case EDIT_SALARY_PAYMENTS = 'edit_salary_payments';
    case DELETE_SALARY_PAYMENTS = 'delete_salary_payments';

    case VIEW_SALARY_BULK_PAYMENTS = 'view_salary_bulk_payments';
    case CREATE_SALARY_BULK_PAYMENTS = 'create_salary_bulk_payments';
    case EDIT_SALARY_BULK_PAYMENTS = 'edit_salary_bulk_payments';
    case DELETE_SALARY_BULK_PAYMENTS = 'delete_salary_bulk_payments';
}
