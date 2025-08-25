<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\LoyaltyCard;
use App\Models\User;

class CleanupStoreLoyaltyCards extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'loyalty:cleanup-store-cards';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Remove loyalty cards that belong to store users (only clients should have loyalty cards)';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Starting to clean up loyalty cards for store users...');

        // Find all loyalty cards that belong to store users
        $storeLoyaltyCards = LoyaltyCard::whereHas('user', function($query) {
            $query->where('role', 'store');
        })->get();

        $count = $storeLoyaltyCards->count();

        if ($count === 0) {
            $this->info('No loyalty cards found for store users. Nothing to clean up.');
            return 0;
        }

        $this->warn("Found {$count} loyalty cards belonging to store users.");
        
        if ($this->confirm("Do you want to delete these loyalty cards? This action cannot be undone.")) {
            $deletedCount = 0;
            
            foreach ($storeLoyaltyCards as $loyaltyCard) {
                try {
                    $loyaltyCard->delete();
                    $deletedCount++;
                    $this->line("Deleted loyalty card ID: {$loyaltyCard->id} for user: {$loyaltyCard->user->name}");
                } catch (\Exception $e) {
                    $this->error("Failed to delete loyalty card ID {$loyaltyCard->id}: " . $e->getMessage());
                }
            }
            
            $this->info("Cleanup completed! Deleted {$deletedCount} loyalty cards.");
        } else {
            $this->info('Cleanup cancelled.');
        }

        return 0;
    }
}
