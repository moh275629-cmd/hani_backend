<?php

require_once 'vendor/autoload.php';

use Illuminate\Support\Facades\Artisan;

// Bootstrap Laravel
$app = require_once 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "Running migration to fix loyalty cards table...\n";

try {
    // Run the migration
    Artisan::call('migrate', ['--path' => 'database/migrations/2025_08_25_000001_fix_loyalty_cards_qr_code_length.php']);
    
    echo "Migration completed successfully!\n";
    echo "Loyalty cards table has been updated to use TEXT columns for encrypted data.\n";
} catch (Exception $e) {
    echo "Migration failed: " . $e->getMessage() . "\n";
}
