<?php

declare(strict_types=1);

namespace App\Enums;

class Permission
{
    // Users
    const USERS_VIEW_ANY      = 'users.view-any';
    const USERS_VIEW          = 'users.view';
    const USERS_CREATE        = 'users.create';
    const USERS_EDIT          = 'users.edit';
    const USERS_DELETE        = 'users.delete';
    const USERS_RESTORE       = 'users.restore';
    const USERS_CHANGE_STATUS = 'users.change-status';
    const USERS_RESET_PASSWORD = 'users.reset-password';

    // Departments
    const DEPARTMENTS_VIEW_ANY = 'departments.view-any';
    const DEPARTMENTS_VIEW     = 'departments.view';
    const DEPARTMENTS_CREATE   = 'departments.create';
    const DEPARTMENTS_EDIT     = 'departments.edit';
    const DEPARTMENTS_DELETE   = 'departments.delete';
    const DEPARTMENTS_RESTORE  = 'departments.restore';

    // Roles
    const ROLES_VIEW   = 'roles.view';
    const ROLES_MANAGE = 'roles.manage';

    // Customers
    const CUSTOMERS_VIEW_ANY = 'customers.view-any';
    const CUSTOMERS_VIEW     = 'customers.view';
    const CUSTOMERS_CREATE   = 'customers.create';
    const CUSTOMERS_EDIT     = 'customers.edit';
    const CUSTOMERS_DELETE   = 'customers.delete';
    const CUSTOMERS_RESTORE  = 'customers.restore';
    const CUSTOMERS_ASSIGN   = 'customers.assign';
}
