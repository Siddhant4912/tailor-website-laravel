<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\User;

$updated = User::where('role', \App\Enums\RoleEnum::CUSTOMER)
    ->whereNull('phone_verified_at')
    ->update(['phone_verified_at' => now(), 'email_verified_at' => now()]);

echo "Updated {$updated} unverified customer users in the database to be verified.\n";
