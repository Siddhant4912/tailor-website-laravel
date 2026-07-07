<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';

$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$invoices = \App\Models\Invoice::all();
foreach ($invoices as $inv) {
    if ($inv->total_amount == 0 && $inv->invoiceable_type === 'App\Models\Order') {
        echo "Found Zero Order Invoice: ID {$inv->id} | Num: {$inv->invoice_number} | Order ID: {$inv->invoiceable_id} | Created: {$inv->created_at}\n";
    }
}
