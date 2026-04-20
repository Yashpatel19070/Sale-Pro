# PO Pipeline Module — Form Requests

## StartUnitJobRequest

```php
<?php
// app/Http/Requests/Pipeline/StartUnitJobRequest.php

declare(strict_types=1);

namespace App\Http\Requests\Pipeline;

use Illuminate\Foundation\Http\FormRequest;

class StartUnitJobRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('start', $this->route('unitJob'));
    }

    public function rules(): array
    {
        return []; // No input required — claiming a job needs no data
    }
}
```

---

## CreateUnitJobRequest

```php
<?php
// app/Http/Requests/Pipeline/CreateUnitJobRequest.php

declare(strict_types=1);

namespace App\Http\Requests\Pipeline;

use Illuminate\Foundation\Http\FormRequest;

class CreateUnitJobRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('createJob', \App\Models\PoUnitJob::class);
    }

    public function rules(): array
    {
        return [
            'po_line_id' => ['required', 'integer', 'exists:po_lines,id'],
            'notes'      => ['nullable', 'string', 'max:2000'],
        ];
    }
}
```

---

## PassUnitJobRequest

```php
<?php
// app/Http/Requests/Pipeline/PassUnitJobRequest.php

declare(strict_types=1);

namespace App\Http\Requests\Pipeline;

use App\Enums\PipelineStage;
use Illuminate\Foundation\Http\FormRequest;

class PassUnitJobRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('pass', $this->route('unitJob'));
    }

    public function rules(): array
    {
        $job   = $this->route('unitJob');
        $stage = $job?->current_stage;

        $rules = [
            'notes' => ['nullable', 'string', 'max:2000'],
        ];

        // serial_assign stage: operator scans/enters serial number once.
        // Stored on job->pending_serial_number. NOT re-entered at shelf.
        if ($stage === PipelineStage::SerialAssign) {
            $rules['serial_number'] = [
                'required',
                'string',
                'max:100',
                'unique:inventory_serials,serial_number',
                'unique:po_unit_jobs,pending_serial_number', // prevent race: two workers scanning same serial
            ];
        }

        // shelf stage: operator selects the shelf location only.
        // Serial number read from job->pending_serial_number (no re-entry).
        // Purchase price locked from PO line (no re-entry).
        if ($stage === PipelineStage::Shelf) {
            $rules['inventory_location_id'] = ['required', 'integer', 'exists:inventory_locations,id'];
        }

        return $rules;
    }
}
```

---

## FailUnitJobRequest

```php
<?php
// app/Http/Requests/Pipeline/FailUnitJobRequest.php

declare(strict_types=1);

namespace App\Http\Requests\Pipeline;

use Illuminate\Foundation\Http\FormRequest;

class FailUnitJobRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('fail', $this->route('unitJob'));
    }

    public function rules(): array
    {
        return [
            'notes' => ['required', 'string', 'max:2000'], // notes mandatory on fail — reason required
        ];
    }
}
```

---

## Notes

- `StartUnitJobRequest` has no validation rules — claiming needs no user-supplied data. authorize() delegates to `PoUnitJobPolicy::start()` which checks the job is pending AND user has stage permission.
- `PassUnitJobRequest` uses dynamic rules based on `$job->current_stage`. The `serial_assign`
  and `shelf` stages have extra required fields. Other stages only accept optional notes.
- `serial_number` at `serial_assign` validated `unique:inventory_serials,serial_number` AND `unique:po_unit_jobs,pending_serial_number` — prevents duplicate serial (both from existing stock and from concurrent workers scanning same serial in-flight).
- `serial_number` NOT required at `shelf` — read from `$job->pending_serial_number`. Operator only picks the shelf location.
- `purchase_price` NOT in any request — always taken from `$poLine->unit_price` (locked at PO creation). Never user-editable.
- `FailUnitJobRequest` makes `notes` **required** — a failure reason must always be recorded.
- `CreateUnitJobRequest`: `po_line_id` validated to exist. Service also guards PO status and line fulfillment.
- All authorize() delegate to `PoUnitJobPolicy`.

---

## Implementation Deviations (actual code differs from plan above)

### `CreateUnitJobRequest` — `po_line_id` scoped to open/partial purchase POs (IDOR fix)
Plan had bare `exists:po_lines,id`. Actual code scopes via `Rule::exists` with a subquery:
```php
Rule::exists('po_lines', 'id')->where(function ($query) {
    $query->whereIn('purchase_order_id', function ($sub) {
        $sub->select('id')->from('purchase_orders')
            ->where('type', PoType::Purchase->value)
            ->whereIn('status', [PoStatus::Open->value, PoStatus::Partial->value]);
    });
}),
```
Prevents passing a `po_line_id` from a draft, cancelled, closed, or return PO. Service-level `canReceive()` check is a second layer, not a substitute for input validation.

### `PassUnitJobRequest::authorize()` — null-safe route binding guard
Plan had `$this->user()->can('pass', $this->route('unitJob'))`. If route binding returns null (e.g. PHPStan analysis, edge case), policy receives null instead of `PoUnitJob` → TypeError. Actual code:
```php
$job = $this->route('unitJob');
return $job instanceof PoUnitJob && $this->user()->can('pass', $job);
```
Short-circuits to `false` if binding is null instead of throwing.
