<?php

namespace App\Console\Commands;

use App\Models\Activation;
use App\Models\User;
use App\Models\Store;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Carbon\Carbon;

class DeactivateExpiredAccounts extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'accounts:deactivate-expired';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Deactivate expired user accounts and send notification emails';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Starting expired accounts deactivation...');

        try {
            // Get all expired activations
            $expiredActivations = Activation::with('user')
                ->expired()
                ->whereHas('user', function ($query) {
                    $query->where('is_active', true);
                })
                ->get();

            $deactivatedCount = 0;

            foreach ($expiredActivations as $activation) {
                $user = $activation->user;
                
                if (!$user) {
                    continue;
                }

                $this->info("Deactivating expired account: {$user->name} ({$user->email}) - Role: {$user->role}");

                // Deactivate the user
                $user->deactivate();

                // If it's a store, also deactivate the store
                if ($user->role === 'store') {
                    $store = Store::where('user_id', $user->id)->first();
                    if ($store) {
                        $store->deactivate();
                        $this->info("Store '{$store->store_name}' also deactivated");
                    }
                }

                // Send deactivation email
                $this->sendDeactivationEmail($user);

                $deactivatedCount++;
            }

            $this->info("Successfully deactivated {$deactivatedCount} expired accounts.");

            // Log the operation
            Log::info("Deactivated {$deactivatedCount} expired accounts via scheduled command");

        } catch (\Exception $e) {
            $this->error("Error during account deactivation: " . $e->getMessage());
            Log::error("Error during account deactivation: " . $e->getMessage());
            return 1;
        }

        return 0;
    }

    /**
     * Send deactivation email to user
     */
    private function sendDeactivationEmail(User $user)
    {
        try {
            Mail::send('emails.account-expired', ['user' => $user], function ($message) use ($user) {
                $message->to($user->email, $user->name)
                        ->subject('Your Hani Account Has Expired');
            });
            
            Log::info("Deactivation email sent to user: {$user->email}");
        } catch (\Exception $e) {
            Log::error("Failed to send deactivation email to user {$user->email}: " . $e->getMessage());
        }
    }
}
