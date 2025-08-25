<?php

require_once 'vendor/autoload.php';

use App\Models\User;
use Illuminate\Support\Facades\Hash;

// Test user deletion
echo "Testing user deletion...\n";

// Create a test user
$testUser = User::create([
    'name' => 'Test User',
    'email' => 'test@example.com',
    'password' => Hash::make('password123'),
    'phone' => '1234567890',
    'role' => 'client',
    'is_active' => true,
]);

echo "Created test user with ID: " . $testUser->id . "\n";

// Try to delete the user
try {
    $deleted = $testUser->delete();
    echo "User deletion result: " . ($deleted ? 'SUCCESS' : 'FAILED') . "\n";
    
    // Check if user still exists
    $userExists = User::find($testUser->id);
    echo "User still exists in database: " . ($userExists ? 'YES' : 'NO') . "\n";
    
} catch (Exception $e) {
    echo "Error deleting user: " . $e->getMessage() . "\n";
}

echo "Test completed.\n";
