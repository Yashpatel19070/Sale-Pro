<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();

            $table->foreignId('customer_id')
                ->constrained('customers')
                ->restrictOnDelete();

            $table->string('number', 20)->unique();

            $table->string('source', 20);        // online | walk_in | phone — PHP guard via OrderSource enum
            $table->string('status', 30)->default('pending');          // OrderStatus enum
            $table->string('payment_status', 10)->default('unpaid');   // unpaid | paid

            $table->foreignId('created_by')
                ->constrained('users')
                ->restrictOnDelete();

            // Totals
            $table->decimal('subtotal', 12, 2)->unsigned()->default(0.00);
            $table->decimal('fees', 12, 2)->unsigned()->default(0.00);
            $table->decimal('core_charges', 12, 2)->unsigned()->default(0.00);
            $table->decimal('shipping', 12, 2)->unsigned()->default(0.00);
            $table->decimal('grand_total', 12, 2)->unsigned()->default(0.00);
            $table->char('currency', 3)->default('USD');

            // Billing snapshot — NULL for cash, stripe_terminal, stripe_checkout, cheque
            $table->string('billing_first_name', 100)->nullable();
            $table->string('billing_last_name', 100)->nullable();
            $table->string('billing_email', 255)->nullable();
            $table->string('billing_phone', 30)->nullable();
            $table->string('billing_address_line1', 255)->nullable();
            $table->string('billing_address_line2', 255)->nullable();
            $table->string('billing_city', 100)->nullable();
            $table->string('billing_state', 10)->nullable();
            $table->string('billing_postal_code', 20)->nullable();
            $table->char('billing_country', 2)->nullable();

            // Shipping snapshot — NULL for in-store pickup; required when carrier shipment exists
            $table->string('shipping_first_name', 100)->nullable();
            $table->string('shipping_last_name', 100)->nullable();
            $table->string('shipping_email', 255)->nullable();
            $table->string('shipping_phone', 30)->nullable();
            $table->string('shipping_address_line1', 255)->nullable();
            $table->string('shipping_address_line2', 255)->nullable();
            $table->string('shipping_city', 100)->nullable();
            $table->string('shipping_state', 10)->nullable();
            $table->string('shipping_postal_code', 20)->nullable();
            $table->char('shipping_country', 2)->nullable();

            // Shipment audit
            $table->timestamp('shipped_at')->nullable();
            $table->foreignId('shipped_by')
                ->nullable()
                ->constrained('users')
                ->restrictOnDelete();

            // Delivery audit (carrier orders — admin records manually)
            $table->timestamp('delivered_at')->nullable();
            $table->foreignId('delivered_by')
                ->nullable()
                ->constrained('users')
                ->restrictOnDelete();

            // Terminal audit — reused for both cancelled and refunded states
            $table->timestamp('cancelled_at')->nullable();
            $table->foreignId('cancelled_by')
                ->nullable()
                ->constrained('users')
                ->restrictOnDelete();

            $table->timestamps();

            $table->index('customer_id');
            $table->index('status');
            $table->index('payment_status');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
