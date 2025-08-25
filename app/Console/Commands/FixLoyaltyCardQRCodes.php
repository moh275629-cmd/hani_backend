<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\LoyaltyCard;

class FixLoyaltyCardQRCodes extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'loyalty:fix-qr-codes';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Fix all existing loyalty card QR codes by regenerating them with proper data structure';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Starting to fix loyalty card QR codes...');

        $loyaltyCards = LoyaltyCard::forClients()->get();
        $fixedCount = 0;
        $totalCount = $loyaltyCards->count();

        $this->info("Found {$totalCount} loyalty cards for client users to process...");

        $progressBar = $this->output->createProgressBar($totalCount);
        $progressBar->start();

        foreach ($loyaltyCards as $loyaltyCard) {
            try {
                // Check if QR code is properly formatted
                $qrData = json_decode($loyaltyCard->qr_code, true);
                
                if (!$qrData || !isset($qrData['user_id']) || !isset($qrData['card_number']) || !isset($qrData['timestamp'])) {
                    // Generate new QR code with proper data structure
                    $newQrCode = LoyaltyCard::generateQrCode($loyaltyCard->user_id, $loyaltyCard->card_number, $loyaltyCard->store_id);
                    
                    $loyaltyCard->update([
                        'qr_code' => $newQrCode
                    ]);
                    
                    $fixedCount++;
                }
            } catch (\Exception $e) {
                $this->error("Error processing loyalty card ID {$loyaltyCard->id}: " . $e->getMessage());
            }
            
            $progressBar->advance();
        }

        $progressBar->finish();
        $this->newLine();

        $this->info("QR code fixing completed!");
        $this->info("Total loyalty cards processed: {$totalCount}");
        $this->info("QR codes fixed: {$fixedCount}");
        $this->info("QR codes already correct: " . ($totalCount - $fixedCount));

        return 0;
    }
}
