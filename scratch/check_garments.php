<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';

$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$garments = \App\Models\Garment::with('design')->get(['id', 'name', 'price', 'design_id']);
foreach ($garments as $g) {
    echo "ID: {$g->id} | Name: {$g->name} | Price: {$g->price} | Design Price: " . ($g->design ? $g->design->additional_price : 'N/A') . "\n";
}
