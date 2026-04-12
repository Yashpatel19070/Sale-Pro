# ProductCategory Module — Schema

## Migration File
`database/migrations/YYYY_MM_DD_HHMMSS_create_product_categories_table.php`

## Table: `product_categories`

```php
Schema::create('product_categories', function (Blueprint $table) {
    $table->id();
    $table->foreignId('parent_id')
        ->nullable()
        ->constrained('product_categories')
        ->nullOnDelete();
    $table->string('name', 100);
    $table->text('description')->nullable();
    $table->boolean('is_active')->default(true);
    $table->timestamps();
    $table->softDeletes();

    $table->unique(['parent_id', 'name']);   // name unique within same parent
    $table->index('is_active');
    $table->index('deleted_at');
});
```

## Columns

| Column | Type | Constraints | Notes |
|--------|------|-------------|-------|
| id | bigint | PK, auto-increment | |
| parent_id | bigint | nullable FK → product_categories.id, nullOnDelete | null = root category |
| name | varchar(100) | NOT NULL | Unique within same parent |
| description | text | nullable | |
| is_active | boolean | NOT NULL, default true | |
| created_at | timestamp | | |
| updated_at | timestamp | | |
| deleted_at | timestamp | nullable | Soft deletes |

## Key Design Decisions

### Adjacency List (self-referencing parent_id)
- Root categories: `parent_id IS NULL`
- Child categories: `parent_id = {parent.id}`
- Unlimited depth — a child can itself be a parent
- No depth limit enforced in DB — enforced in UI (dropdown excludes descendants)

### Unique Constraint
`UNIQUE(parent_id, name)` — name must be unique within the same parent.
- "Electronics > Phones" and "Accessories > Phones" are both valid
- Two "Phones" under "Electronics" are not allowed
- MySQL treats NULL as distinct in unique indexes — two roots can share a name only if different parent_id NULLs. Use application-level validation to prevent duplicate root names.

### nullOnDelete
When a parent category is soft-deleted, children keep their `parent_id`.
When a parent is hard-deleted (won't happen — soft deletes only), children become roots (`parent_id` → NULL).

## Checklist
- [ ] Migration created with `parent_id` nullable FK to self
- [ ] `nullOnDelete()` on the FK constraint
- [ ] Composite unique on `(parent_id, name)`
- [ ] Indexes on `is_active` and `deleted_at`
- [ ] `php artisan migrate` runs without error
- [ ] `php artisan migrate:rollback` drops table cleanly
