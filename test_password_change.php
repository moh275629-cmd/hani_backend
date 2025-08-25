<?php

require_once 'vendor/autoload.php';

use App\Models\User;
use Illuminate\Support\Facades\Hash;

// Test password change
echo "Testing password change...\n";

// Create a test user
$testUser = User::create([
    'name' => 'Test User',
    'email' => 'test2@example.com',
    'password' => Hash::make('oldpassword'),
    'phone' => '1234567891',
    'role' => 'client',
    'is_active' => true,
]);

echo "Created test user with ID: " . $testUser->id . "\n";
echo "Original password hash: " . $testUser->password . "\n";

// Try to change password
try {
    $newPassword = 'newpassword123';
    $testUser->password = Hash::make($newPassword);
    $saved = $testUser->save();
    
    echo "Password change result: " . ($saved ? 'SUCCESS' : 'FAILED') . "\n";
    echo "New password hash: " . $testUser->password . "\n";
    
    // Verify the new password works
    $passwordValid = Hash::check($newPassword, $testUser->password);
    echo "New password verification: " . ($passwordValid ? 'VALID' : 'INVALID') . "\n";
    
    // Verify old password doesn't work
    $oldPasswordValid = Hash::check('oldpassword', $testUser->password);
    echo "Old password verification: " . ($oldPasswordValid ? 'VALID' : 'INVALID') . "\n";
    
} catch (Exception $e) {
    echo "Error changing password: " . $e->getMessage() . "\n";
}

// Clean up
$testUser->delete();
echo "Test completed.\n";
