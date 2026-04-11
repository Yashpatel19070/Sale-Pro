# Permissions Module — Migration

## Goal

Add `is_admin` (bool) and `is_super` (bool) columns to Spatie's `roles` table.
These flags drive `EnsureIsAdmin` and `EnsureSuperAdmin` middleware — no hardcoded
role names anywhere in middleware or routes.

## Migration

File: `database/migrations/YYYY_MM_DD_HHMMSS_add_flags_to_roles_table.php`

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('roles', function (Blueprint $table): void {
            $table->boolean('is_admin')->default(false)->after('guard_name');
            $table->boolean('is_super')->default(false)->after('is_admin');
        });
    }

    public function down(): void
    {
        Schema::table('roles', function (Blueprint $table): void {
            $table->dropColumn(['is_admin', 'is_super']);
        });
    }
};
```

## Role Model

Extend Spatie's Role model to expose the new columns:

File: `app/Models/Role.php`

```php
<?php

declare(strict_types=1);

namespace App\Models;

use Spatie\Permission\Models\Role as SpatieRole;

class Role extends SpatieRole
{
    protected $fillable = ['name', 'guard_name', 'is_admin', 'is_super'];

    protected function casts(): array
    {
        return [
            'is_admin' => 'boolean',
            'is_super' => 'boolean',
        ];
    }
}
```

## Config Update

Tell Spatie to use the custom Role model:

`config/permission.php` → `'models' => ['role' => App\Models\Role::class]`
