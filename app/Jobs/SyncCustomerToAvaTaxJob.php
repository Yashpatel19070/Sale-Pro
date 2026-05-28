<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Customer;
use App\Services\AvaTaxService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

final class SyncCustomerToAvaTaxJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** @var array<int, int> */
    public array $backoff = [30, 60, 120];

    public function __construct(public Customer $customer) {}

    public function handle(AvaTaxService $svc): void
    {
        $code = $svc->upsertCustomer($this->customer);

        if ($code === null) {
            return;
        }

        $this->customer->forceFill([
            'avatax_customer_id' => $code,
            'avatax_synced_at' => now(),
        ])->save();

        // Cert sync — uses the in-memory $this->customer (just forceFilled with
        // avatax_customer_id above). Avoids an extra SELECT and the soft-delete
        // race window where fresh() could return null.
        $certId = $svc->upsertCertificate($this->customer);
        if ($certId !== null) {
            $this->customer->forceFill(['avatax_certificate_id' => $certId])->save();
        }
    }

    public function failed(Throwable $e): void
    {
        Log::warning('SyncCustomerToAvaTaxJob exhausted retries', [
            'customer_id' => $this->customer->id,
            'message' => $e->getMessage(),
        ]);
    }
}
