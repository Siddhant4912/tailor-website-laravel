<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';

$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "=== RECENT ORDERS ===\n";
$orders = \App\Models\Order::with('items')->latest()->take(5)->get();
foreach ($orders as $order) {
    echo "Order #{$order->order_number} | ID: {$order->id} | Subtotal: {$order->subtotal} | Total: {$order->total_price} | Advance Paid: {$order->advance_paid} | Status: {$order->status->value}\n";
    foreach ($order->items as $item) {
        echo "  - Item: {$item->garment_name} | Qty: {$item->quantity} | Price: {$item->price}\n";
    }
}

echo "\n=== RECENT INVOICES ===\n";
$invoices = \App\Models\Invoice::latest()->take(5)->get();
foreach ($invoices as $inv) {
    echo "Invoice #{$inv->invoice_number} | ID: {$inv->id} | Subtotal: {$inv->subtotal} | Total: {$inv->total_amount} | Advance Paid: {$inv->advance_paid} | Status: {$inv->status->value} | Morph: {$inv->invoiceable_type} (#{$inv->invoiceable_id})\n";
}
