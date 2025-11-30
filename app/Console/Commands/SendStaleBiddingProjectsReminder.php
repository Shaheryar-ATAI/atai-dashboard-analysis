<?php

namespace App\Console\Commands;

use App\Mail\StaleBiddingProjectsMail;
use App\Models\Project;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

class SendStaleBiddingProjectsReminder extends Command
{
    protected $signature   = 'atai:stale-bidding-reminder';
    protected $description = 'Email sales managers about bidding projects with no update for more than 3 months';

    public function handle(): int
    {
        $this->info('Checking for stale bidding projects per salesman...');

        // 🔹 TEMP: test mode (hard-coded to your email)
        $testMode  = true;   // ⬅️ flip to false when you go live
        $testEmail = 'm.shaheryar@ataiksa.com'; // your email

        // 1) Get all stale bidding projects once
        $staleProjects = Project::staleBidding()
            ->whereNotNull('salesman')   // make sure this matches your column name
            ->get();

        if ($staleProjects->isEmpty()) {
            $this->info('No stale bidding projects found at all. Nothing to do.');
            return Command::SUCCESS;
        }

        // 2) Get distinct salesman names from these projects
        $salesmanNames = $staleProjects
            ->pluck('salesman')
            ->filter()
            ->unique()
            ->values();

        if ($salesmanNames->isEmpty()) {
            $this->warn('No salesman names found on stale projects. Check salesman column.');
            return Command::SUCCESS;
        }

        // 3) Get GM/Admin emails for CC (still fine in test mode)
        $gmUsers = User::query()
            ->whereHas('roles', function ($q) {
                $q->whereIn('name', ['gm', 'admin']);
            })
            ->whereNotNull('email')
            ->get();

        $gmEmails = $gmUsers->pluck('email')->filter()->unique()->values()->all();

        foreach ($salesmanNames as $salesmanName) {
            $this->info("Processing salesman: {$salesmanName}");

            // 4) Find the actual User record for this salesman
            $salesman = User::query()
                ->where('name', $salesmanName)
                ->whereNotNull('email')
                ->first();

            if (!$salesman) {
                $this->warn("  No user found with name '{$salesmanName}' (skipping).");
                continue;
            }

            // 5) Projects for THIS salesman
            $projectsForSalesman = $staleProjects
                ->where('salesman', $salesmanName)
                ->sortBy('quotation_date')
                ->values();

            if ($projectsForSalesman->isEmpty()) {
                $this->info("  No stale projects for {$salesmanName} (after filter).");
                continue;
            }

            // 6) Build mail
            $mail = new StaleBiddingProjectsMail(
                projects: $projectsForSalesman,
                regionName: $salesmanName
            );

            if ($testMode) {
                // 🔴 TEST: always send to YOU only
                Mail::to($testEmail)
                      ->cc('noreply@atai-dashboard.com')->send($mail);
                $this->info("  [TEST MODE] Email sent to {$testEmail} for salesman {$salesmanName}");
            } else {
                // ✅ LIVE: real behavior
                Mail::to($salesman->email)
                    ->cc($gmEmails)
                    ->send($mail);

                $this->info("  Email queued for {$salesman->email} (CC GM)");
            }
        }

        $this->info('Stale bidding reminder completed (per salesman).');
        return Command::SUCCESS;
    }



}
