<?php

declare(strict_types=1);

namespace App\Enums;

class Permission
{
    // Users
    const USERS_VIEW_ANY = 'users.view-any';

    const USERS_VIEW = 'users.view';

    const USERS_CREATE = 'users.create';

    const USERS_EDIT = 'users.edit';

    const USERS_DELETE = 'users.delete';

    const USERS_RESTORE = 'users.restore';

    const USERS_CHANGE_STATUS = 'users.change-status';

    const USERS_RESET_PASSWORD = 'users.reset-password';

    // Departments
    const DEPARTMENTS_VIEW_ANY = 'departments.view-any';

    const DEPARTMENTS_VIEW = 'departments.view';

    const DEPARTMENTS_CREATE = 'departments.create';

    const DEPARTMENTS_EDIT = 'departments.edit';

    const DEPARTMENTS_DELETE = 'departments.delete';

    const DEPARTMENTS_RESTORE = 'departments.restore';

    // Roles
    const ROLES_VIEW = 'roles.view';

    const ROLES_MANAGE = 'roles.manage';

    // Product Categories
    const PRODUCT_CATEGORIES_VIEW_ANY = 'product_categories.viewAny';

    const PRODUCT_CATEGORIES_VIEW = 'product_categories.view';

    const PRODUCT_CATEGORIES_CREATE = 'product_categories.create';

    const PRODUCT_CATEGORIES_UPDATE = 'product_categories.update';

    const PRODUCT_CATEGORIES_DELETE = 'product_categories.delete';

    // Customers
    const CUSTOMERS_VIEW_ANY = 'customers.view-any';

    const CUSTOMERS_VIEW = 'customers.view';

    const CUSTOMERS_CREATE = 'customers.create';

    const CUSTOMERS_EDIT = 'customers.edit';

    const CUSTOMERS_DELETE = 'customers.delete';

    const CUSTOMERS_RESTORE = 'customers.restore';

    const CUSTOMERS_ASSIGN = 'customers.assign';

    // Products
    const PRODUCTS_VIEW_ANY = 'products.view-any';

    const PRODUCTS_VIEW = 'products.view';

    const PRODUCTS_CREATE = 'products.create';

    const PRODUCTS_EDIT = 'products.edit';

    const PRODUCTS_DELETE = 'products.delete';

    const PRODUCTS_RESTORE = 'products.restore';

    // Audit Log
    const AUDIT_LOG_VIEW_ANY = 'audit-log.view-any';

    const AUDIT_LOG_VIEW = 'audit-log.view';

    // Product Listings
    const PRODUCT_LISTINGS_VIEW_ANY = 'product-listings.view-any';

    const PRODUCT_LISTINGS_VIEW = 'product-listings.view';

    const PRODUCT_LISTINGS_CREATE = 'product-listings.create';

    const PRODUCT_LISTINGS_EDIT = 'product-listings.edit';

    const PRODUCT_LISTINGS_DELETE = 'product-listings.delete';

    const PRODUCT_LISTINGS_RESTORE = 'product-listings.restore';

    // Inventory Locations
    const INVENTORY_LOCATIONS_VIEW_ANY = 'inventory-locations.view-any';

    const INVENTORY_LOCATIONS_VIEW = 'inventory-locations.view';

    const INVENTORY_LOCATIONS_CREATE = 'inventory-locations.create';

    const INVENTORY_LOCATIONS_EDIT = 'inventory-locations.edit';

    const INVENTORY_LOCATIONS_DELETE = 'inventory-locations.delete';

    const INVENTORY_LOCATIONS_RESTORE = 'inventory-locations.restore';

    // Inventory Serials
    const INVENTORY_SERIALS_VIEW_ANY = 'inventory-serials.view-any';

    const INVENTORY_SERIALS_VIEW = 'inventory-serials.view';

    const INVENTORY_SERIALS_CREATE = 'inventory-serials.create';

    const INVENTORY_SERIALS_EDIT = 'inventory-serials.edit';

    const INVENTORY_SERIALS_MARK_DAMAGED = 'inventory-serials.mark-damaged';

    const INVENTORY_SERIALS_MARK_MISSING = 'inventory-serials.mark-missing';
}
