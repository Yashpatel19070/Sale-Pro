<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Order;
use App\Services\ReceiptService;
use Illuminate\View\View;

class ReceiptController extends Controller
{
    public function __construct(private readonly ReceiptService $receipts) {}

    public function show(Order $order): View
    {
        $this->authorize('view', $order);

        $receipt = $this->receipts->build($order);

        return view('orders.receipt', compact('order', 'receipt'));
    }
}
