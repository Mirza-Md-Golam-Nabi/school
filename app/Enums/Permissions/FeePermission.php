<?php

namespace App\Enums\Permissions;

enum FeePermission: string
{
    case VIEW_FEE_TYPES = 'view_fee_types';
    case CREATE_FEE_TYPES = 'create_fee_types';
    case EDIT_FEE_TYPES = 'edit_fee_types';
    case DELETE_FEE_TYPES = 'delete_fee_types';

    case VIEW_FEE_STRUCTURES = 'view_fee_structures';
    case CREATE_FEE_STRUCTURES = 'create_fee_structures';
    case EDIT_FEE_STRUCTURES = 'edit_fee_structures';
    case DELETE_FEE_STRUCTURES = 'delete_fee_structures';

    case VIEW_FEE_DISCOUNTS = 'view_fee_discounts';
    case CREATE_FEE_DISCOUNTS = 'create_fee_discounts';
    case EDIT_FEE_DISCOUNTS = 'edit_fee_discounts';
    case DELETE_FEE_DISCOUNTS = 'delete_fee_discounts';

    case VIEW_STUDENT_FEE_INVOICES = 'view_student_fee_invoices';
    case CREATE_STUDENT_FEE_INVOICES = 'create_student_fee_invoices';
    case EDIT_STUDENT_FEE_INVOICES = 'edit_student_fee_invoices';
    case DELETE_STUDENT_FEE_INVOICES = 'delete_student_fee_invoices';

    case VIEW_FEE_PAYMENTS = 'view_fee_payments';
    case CREATE_FEE_PAYMENTS = 'create_fee_payments';
    case EDIT_FEE_PAYMENTS = 'edit_fee_payments';
    case DELETE_FEE_PAYMENTS = 'delete_fee_payments';

    case VIEW_LATE_FEE_RULES = 'view_late_fee_rules';
    case CREATE_LATE_FEE_RULES = 'create_late_fee_rules';
    case EDIT_LATE_FEE_RULES = 'edit_late_fee_rules';
    case DELETE_LATE_FEE_RULES = 'delete_late_fee_rules';

    case VIEW_SCHOOL_ACCOUNTS = 'view_school_accounts';
    case CREATE_SCHOOL_ACCOUNTS = 'create_school_accounts';
    case EDIT_SCHOOL_ACCOUNTS = 'edit_school_accounts';
    case DELETE_SCHOOL_ACCOUNTS = 'delete_school_accounts';

    case VIEW_ACCOUNT_TRANSACTIONS = 'view_account_transactions';
    case CREATE_ACCOUNT_TRANSACTIONS = 'create_account_transactions';
    case EDIT_ACCOUNT_TRANSACTIONS = 'edit_account_transactions';
    case DELETE_ACCOUNT_TRANSACTIONS = 'delete_account_transactions';

    case VIEW_TRANSACTION_CATEGORIES = 'view_transaction_categories';
    case CREATE_TRANSACTION_CATEGORIES = 'create_transaction_categories';
    case EDIT_TRANSACTION_CATEGORIES = 'edit_transaction_categories';
    case DELETE_TRANSACTION_CATEGORIES = 'delete_transaction_categories';

    case VIEW_FUND_TRANSACTIONS = 'view_fund_transactions';
    case CREATE_FUND_TRANSACTIONS = 'create_fund_transactions';
    case EDIT_FUND_TRANSACTIONS = 'edit_fund_transactions';
    case DELETE_FUND_TRANSACTIONS = 'delete_fund_transactions';
}
