<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\ServiceNow\Incident;
use Illuminate\Support\Facades\Log;

class CheckNetworkAlertAggregation extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'netman:checkNetworkAlertAggregation';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Verify the Network Alert Aggregation system has opened at least one ServiceNow incident in the last 24 hours; opens an incident if not.';

    /**
     * The short_description used for alerts raised by this command, also used to detect a pre-existing open ticket.
     *
     * @var string
     */
    protected const ALERT_SHORT_DESCRIPTION = 'Potential Network Alert Aggregation Failure';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $netmonSysId = env('SNOW_NETMON_SYSID');

        if (!$netmonSysId) {
            $this->error('SNOW_NETMON_SYSID is not set in .env');
            return 1;
        }

        try {
            $incidents = Incident::where('opened_by', '=', $netmonSysId)
                ->where('opened_at', '>', 'javascript:gs.hoursAgo(24)')
                ->get();
        } catch (\Exception $e) {
            $this->error('Failed to query ServiceNow for recent incidents: ' . $e->getMessage());
            Log::error('CheckNetworkAlertAggregation', ['state' => 'query_failed', 'error' => $e->getMessage()]);
            return 1;
        }

        if ($incidents->isNotEmpty()) {
            $this->info("Found {$incidents->count()} incident(s) opened by Network Alert Aggregation in the last 24 hours.");
            return 0;
        }

        $this->warn('No incidents found in the last 24 hours.');
        Log::warning('CheckNetworkAlertAggregation', ['state' => 'no_incidents_found']);

        try {
            $existingTicket = Incident::where('assignment_group', '=', env('SNOW_NETWORKSERVICES_SYSID'))
                ->where('short_description', '=', self::ALERT_SHORT_DESCRIPTION)
                ->where('active', '=', 'true')
                ->first();
        } catch (\Exception $e) {
            $this->error('Failed to check for an existing open incident: ' . $e->getMessage());
            Log::error('CheckNetworkAlertAggregation', ['state' => 'existing_check_failed', 'error' => $e->getMessage()]);
            return 1;
        }

        if ($existingTicket) {
            $this->info("An open incident already exists for this issue: {$existingTicket->number}. Skipping creation.");
            Log::info('CheckNetworkAlertAggregation', ['state' => 'existing_ticket_found', 'number' => $existingTicket->number]);
            return 0;
        }

        $this->warn('Opening a new incident.');

        try {
            $ticket = Incident::create([
                'impact'            => 3,
                'urgency'           => 3,
                'short_description' => self::ALERT_SHORT_DESCRIPTION,
                'description'       => "The Network Alert Aggregation system hasn't created any incidents in the past 24 hours.  Please investigate",
                'caller_id'         => $netmonSysId,
                'assignment_group'  => env('SNOW_NETWORKSERVICES_SYSID'),
                'category'          => 'network',
            ]);
        } catch (\Exception $e) {
            $this->error('Failed to create ServiceNow incident: ' . $e->getMessage());
            Log::error('CheckNetworkAlertAggregation', ['state' => 'create_failed', 'error' => $e->getMessage()]);
            return 1;
        }

        $this->info("Created incident: {$ticket->number}");
        Log::warning('CheckNetworkAlertAggregation', ['state' => 'incident_created', 'number' => $ticket->number]);

        return 0;
    }
}
