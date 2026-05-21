# Notes Module — Schema

## Purpose

Free-text notes attached to orders. Single table covers the entire order lifecycle — complaints, replacements, refunds, and shipments all share one note stream per order. One `WHERE order_id = ?` returns the full history.

---

## Tables Overview

| Table | Purpose |
|-------|---------|
| `notes` | Staff notes on any order-related event |

---

## Table: `notes`

| Column | Type | Nullable | Default | Notes |
|--------|------|----------|---------|-------|
| id | bigIncrements | No | — | Primary key |
| order_id | foreignId | No | — | FK → orders.id, cascade delete |
| body | text | No | — | Note content — free text |
| created_by | foreignId | No | — | FK → users.id — staff member who wrote the note |
| created_at | timestamp | Yes | — | Auto |

### Indexes
- `order_id` — foreign key index (primary query pattern)
- `created_by` — foreign key index

### Notes
- No soft delete — notes are permanent records
- No update after creation — append only
- `order_id` always set — every note ties to the originating order regardless of whether it relates to a complaint, replacement, refund, or the order itself

---

## Query pattern

```php
// Full order history — one query returns all notes for order + all related events
Note::where('order_id', $order->id)
    ->with('author')
    ->orderBy('created_at')
    ->get();
```

---

## Migration Order

```
1. orders   (order_id FK)
2. users    (created_by FK — already exists)
3. notes (depends on: orders, users)
```

---

## Relationships Summary

```
Order hasMany Notes
Note belongsTo Order
Note belongsTo User (created_by — aliased as 'author')
```
