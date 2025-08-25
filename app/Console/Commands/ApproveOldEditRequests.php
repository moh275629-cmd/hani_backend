<?php
namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\EditStore;
use Carbon\Carbon;

class ApproveOldEditRequests extends Command
{
    protected $signature = 'editrequests:approve-old';
    protected $description = 'Approve edit requests older than 48 hours';

    public function handle()
    {
        $threshold = Carbon::now()->subHours(48);

        $requests = EditStore::where('status', 'pending')
                             ->where('created_at', '<=', $threshold)
                             ->get();

        foreach ($requests as $request) {
            $request->approve($request->reviewed_by ?? null); // if you have a default reviewer ID
            $this->info("Approved edit request ID: {$request->id}");
        }

        $this->info('Done.');
    }
}

