# Product Module — Schema

## Migration File
`database/migrations/YYYY_MM_DD_HHMMSS_create_products_table.php`

> Run **after** `create_product_categories_table` migration.

## Table: `products`

```php
Schema::create('products', function (Blueprint $table) {
    $table->id();
    $table->foreignId('category_id')
        ->nullable()
        ->constrained('product_categories')
        ->nullOnDelete();
    $table->string('sku', 64)->unique();
    $table->string('name', 200);
    $table->text('description')->nullable();
    $table->unsignedDecimal('purchase_price', 10, 2)->nullable();
    $table->unsignedDecimal('regular_price', 10, 2);
    $table->unsignedDecimal('sale_price', 10, 2)->nullable();
    $table->string('notes', 500)->nullable();      // internal staff notes
    $table->boolean('is_active')->default(true);
    $table->timestamps();
    $table->softDeletes();

    $table->index('category_id');
    $table->index('is_active');
    $table->index('deleted_at');
});
```

## Columns

| Column | Type | Constraints | Notes |
|--------|------|-------------|-------|
| id | bigint | PK auto-increment | |
| category_id | bigint | nullable FK → product_categories.id, nullOnDelete | null = uncategorised |
| sku | varchar(64) | UNIQUE NOT NULL | Immutable after creation. Uppercase, alphanumeric + dash/dot |
| name | varchar(200) | NOT NULL | Display name |
| description | text | nullable | Full product description (shown in admin) |
| purchase_price | decimal(10,2) | nullable, unsigned | What we pay; internal only — never shown to customers |
| regular_price | decimal(10,2) | NOT NULL, unsigned | Standard selling price — shown on all listings |
| sale_price | decimal(10,2) | nullable, unsigned | Discounted price; when set, this is the active price; regular_price becomes strike-through |
| notes | varchar(500) | nullable | Internal staff notes; never shown to customers |
| is_active | boolean | NOT NULL default true | Inactive products hide all their listings |
| created_at / updated_at | timestamp | | |
| deleted_at | timestamp | nullable | Soft delete |

## Key Design Decisions

### SKU Immutability
SKU is the stable external identifier. Once set it must not change — orders, listings, and integrations reference it.
- Enforced in `UpdateProductRequest` — SKU field absent from rules (not accepted)
- Controller never passes SKU to `service->update()`

### purchase_price nullable
Some products may have unknown or N/A cost (e.g., bundled). Always `nullable`.
Never expose `purchase_price` to customers in any view — internal staff only.

### regular_price vs sale_price
`regular_price` is the standard list price shown to customers.
When `sale_price` is set, it becomes the active selling price and `regular_price` is shown struck-through.
All listings belonging to this product inherit these prices directly — no price duplication at listing level.

### nullOnDelete on category_id
When a category is soft-deleted the product stays; category_id becomes null.
Product becomes "uncategorised" — still usable, just harder to find by category filter.

## Checklist
- [ ] Migration runs after product_categories migration
- [ ] `sku` column is UNIQUE
- [ ] `category_id` FK with `nullOnDelete()`
- [ ] `purchase_price` nullable, `regular_price` NOT NULL, `sale_price` nullable — all `unsignedDecimal(10,2)`
- [ ] `php artisan migrate` runs clean
- [ ] `php artisan migrate:rollback` drops table cleanly
