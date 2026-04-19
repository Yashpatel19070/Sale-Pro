# PO Pipeline Module — Schema

## Table: `po_unit_jobs`

| Column | Type | Nullable | Default | Notes |
|--------|------|----------|---------|-------|
| id | bigIncrements | No | — | Primary key |
| purchase_order_id | unsignedBigInteger | No | — | FK → purchase_orders.id |
| po_line_id | unsignedBigInteger | No | — | FK → po_lines.id (which product/price) |
| inventory_serial_id | unsignedBigInteger | Yes | null | FK → inventory_serials.id. Null until shelf stage completes. |
| pending_serial_number | string(100) | Yes | null | Serial number scanned at serial_assign stage. Persists to shelf. Read-only after serial_assign passes. |
| current_stage | string(20) | No | receive | Current pipeline stage. See PipelineStage enum. |
| status | string(20) | No | pending | Job status. See UnitJobStatus enum. |
| assigned_to_user_id | unsignedBigInteger | Yes | null | FK → users.id. Who claimed this job. |
| notes | text | Yes | null | Optional notes on the job (separate from events) |
| created_at | timestamp | Yes | — | Auto by Laravel |
| updated_at | timestamp | Yes | — | Auto by Laravel |

**No `deleted_at`** — jobs are never deleted. Failed jobs stay for audit.

---

## Table: `po_unit_events`

| Column | Type | Nullable | Default | Notes |
|--------|------|----------|---------|-------|
| id | bigIncrements | No | — | Primary key |
| po_unit_job_id | unsignedBigInteger | No | — | FK → po_unit_jobs.id |
| stage | string(20) | No | — | Stage this event belongs to. See PipelineStage enum. |
| action | string(20) | No | — | What happened. See UnitEventAction enum. |
| user_id | unsignedBigInteger | No | — | FK → users.id. Who performed the action. |
| notes | text | Yes | null | Optional user-entered notes for this action |
| created_at | timestamp | No | — | When this event was recorded. IMMUTABLE. |

**No `updated_at`** — events are immutable. No `deleted_at` — never deleted.

---

## Migrations

### po_unit_jobs

```php
<?php
// database/migrations/xxxx_create_po_unit_jobs_table.php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('po_unit_jobs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_order_id')
                  ->constrained('purchase_orders')
                  ->cascadeOnDelete();
            $table->foreignId('po_line_id')
                  ->constrained('po_lines')
                  ->cascadeOnDelete();
            $table->foreignId('inventory_serial_id')
                  ->nullable()
                  ->constrained('inventory_serials')
                  ->nullOnDelete();
            $table->string('pending_serial_number', 100)->nullable();
            $table->string('current_stage', 20)->default('receive');
            $table->string('status', 20)->default('pending');
            $table->foreignId('assigned_to_user_id')
                  ->nullable()
                  ->constrained('users')
                  ->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index('purchase_order_id');
            $table->index('po_line_id');
            $table->index('current_stage');
            $table->index('status');
            $table->index(['current_stage', 'status']); // queue filter
            $table->unique('pending_serial_number');     // MySQL ignores NULLs — only enforced on assigned serials
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('po_unit_jobs');
    }
};
```

### po_unit_events

```php
<?php
// database/migrations/xxxx_create_po_unit_events_table.php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('po_unit_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('po_unit_job_id')
                  ->constrained('po_unit_jobs')
                  ->cascadeOnDelete();
            $table->string('stage', 20);
            $table->string('action', 20);
            $table->foreignId('user_id')
                  ->constrained('users')
                  ->restrictOnDelete();
            $table->text('notes')->nullable();
            $table->timestamp('created_at')->useCurrent(); // immutable, no updated_at

            $table->index('po_unit_job_id');
            $table->index(['po_unit_job_id', 'stage']); // stage history lookup
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('po_unit_events');
    }
};
```

---

## Enums

### PipelineStage

```php
<?php
// app/Enums/PipelineStage.php

declare(strict_types=1);

namespace App\Enums;

enum PipelineStage: string
{
    case Receive      = 'receive';
    case Visual       = 'visual';
    case SerialAssign = 'serial_assign';
    case Tech         = 'tech';
    case Qa           = 'qa';
    case Shelf        = 'shelf';

    public function label(): string
    {
        return match($this) {
            self::Receive      => 'Receive',
            self::Visual       => 'Visual Inspection',
            self::SerialAssign => 'Serial Assignment',
            self::Tech         => 'Tech Inspection',
            self::Qa           => 'QA',
            self::Shelf        => 'Shelf',
        };
    }

    /**
     * Returns the next stage in the pipeline. Null means this is the final stage.
     */
    public function next(): ?self
    {
        return match($this) {
            self::Receive      => self::Visual,
            self::Visual       => self::SerialAssign,
            self::SerialAssign => self::Tech,
            self::Tech         => self::Qa,
            self::Qa           => self::Shelf,
            self::Shelf        => null,
        };
    }

    public function isFinal(): bool
    {
        return $this === self::Shelf;
    }
}
```

### UnitJobStatus

```php
<?php
// app/Enums/UnitJobStatus.php

declare(strict_types=1);

namespace App\Enums;

enum UnitJobStatus: string
{
    case Pending    = 'pending';      // unclaimed — visible in queue
    case InProgress = 'in_progress'; // claimed by worker — hidden from queue, visible on detail
    case Passed     = 'passed';      // terminal — stage passed
    case Failed     = 'failed';      // terminal — triggers return PO
    case Skipped    = 'skipped';     // terminal — bypassed per PO skip flag (system-written)

    public function isTerminal(): bool
    {
        // DESIGN ASSUMPTION: Passed is safe to treat as terminal here because
        // advance() always resets status → Pending when moving between stages.
        // Therefore status=Passed only persists after shelf stage completes.
        // Do NOT use this outside the pipeline context without understanding this.
        return in_array($this, [self::Passed, self::Failed, self::Skipped], true);
    }
}
```

### UnitEventAction

```php
<?php
// app/Enums/UnitEventAction.php

declare(strict_types=1);

namespace App\Enums;

enum UnitEventAction: string
{
    case Start  = 'start';   // worker claimed the job (pending → in_progress)
    case Pass   = 'pass';    // stage passed, advance to next
    case Fail   = 'fail';    // stage failed, trigger return PO
    case Skip   = 'skip';    // stage skipped per PO flag (system-written, no human takes it)
    case Reopen = 'reopen';  // job reopened by manager (undo a pass/fail)
}
```

---

## Notes

- `po_unit_events` has no `updated_at` — use `$table->timestamp('created_at')->useCurrent()`.
- `current_stage` and `status` stored as strings (not enums at DB level) for flexibility. Enum casting in model.
- Compound index on `(current_stage, status)` is the queue filter index — used every page load.
- `pending_serial_number` set once at `serial_assign` stage, never overwritten. Read at `shelf` stage to create `InventorySerial`. After shelf passes, `inventory_serial_id` points to the real record.
- `inventory_serial_id` is null until shelf stage completes — `InventorySerial` row only created at shelf.
- `UnitJobStatus` lifecycle: `pending` (unclaimed) → `in_progress` (worker claimed, redirected to detail page) → `passed`/`failed`/`skipped` (terminal).
- Queue page only shows `pending` jobs. Once claimed, job disappears from queue — prevents two workers taking the same unit.
- `UnitEventAction::Skip` is written by the system (no human takes it) when PO has `skip_tech`/`skip_qa` flags. Jobs skip directly from `pending` to `skipped` with no `in_progress` step.
- A unit that fails at any stage has `status = failed` on its job row and no further stage progression.
