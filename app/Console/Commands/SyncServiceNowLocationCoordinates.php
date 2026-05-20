<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\ServiceNow\Location;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;

class SyncServiceNowLocationCoordinates extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'netman:SyncServiceNowLocationCoordinates
                            {--dry-run : Show what would be updated without actually updating ServiceNow}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Find ServiceNow locations missing GPS coordinates, geocode them via Google API, and update ServiceNow';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $dryRun = $this->option('dry-run');

        if ($dryRun) {
            $this->line("*** DRY RUN MODE - No changes will be made to ServiceNow ***");
            $this->line("");
        }

        $apiKey = env('GOOGLE_GEOCODING_API_KEY');
        if (!$apiKey) {
            $this->error("Crikey! GOOGLE_GEOCODING_API_KEY is not set in your .env file, mate!");
            return 1;
        }

        $this->line("Fetching all ServiceNow locations (pre-mob and active only)... hold onto your hat!");
        $locations = Location::allPremobeAndActive();

        if (!$locations || $locations->isEmpty()) {
            $this->warn("Blimey! No ServiceNow locations found. Nothing to do, cobber!");
            return 0;
        }

        $this->line("Found " . $locations->count() . " total locations. Filtering for ones missing GPS coordinates...");
        $this->line("");

        $missing = $locations->filter(function ($loc) {
            return empty($loc->latitude) || empty($loc->longitude);
        });

        if ($missing->isEmpty()) {
            $this->info("Beauty! All locations already have GPS coordinates. Nothing to do, mate!");
            return 0;
        }

        $this->line("Found " . $missing->count() . " location(s) missing coordinates. Let's get into it!");
        $this->line("");

        $updated  = 0;
        $skipped  = 0;
        $failed   = 0;

        foreach ($missing as $location) {
            $this->line("--------------------------------------------------");
            $this->line("Processing: " . $location->name . " (sys_id: " . $location->sys_id . ")");

            $address = $this->buildAddress($location);

            if (!$address) {
                $this->warn("  Strewth! Not enough address data to geocode this one. Skipping!");
                $skipped++;
                continue;
            }

            $this->line("  Address: " . $address);

            $coords = $this->geocodeAddress($address, $apiKey);

            if (!$coords) {
                $this->error("  No worries... well, actually there IS a worry — Google couldn't geocode this address. Skipping!");
                $failed++;
                continue;
            }

            $this->line("  Coordinates found: lat=" . $coords['lat'] . ", lng=" . $coords['lng']);

            if ($dryRun) {
                $this->info("  [DRY RUN] Would update ServiceNow with latitude=" . $coords['lat'] . ", longitude=" . $coords['lng']);
                $updated++;
                continue;
            }

            try {
                $location->latitude  = $coords['lat'];
                $location->longitude = $coords['lng'];
                $location->save();
                $this->info("  She's apples! Updated ServiceNow location with new coordinates.");
                $updated++;
            } catch (\Exception $e) {
                $this->error("  Crikey! Failed to update ServiceNow: " . $e->getMessage());
                $failed++;
            }
        }

        $this->line("");
        $this->line("==================================================");
        $this->line("All done, mate! Here's the wrap-up:");
        $this->info("  Updated : " . $updated);
        $this->warn("  Skipped : " . $skipped);
        $this->error("  Failed  : " . $failed);
        $this->line("==================================================");

        return 0;
    }

    /**
     * Build a geocodable address string from a ServiceNow location record.
     *
     * @param  \App\Models\ServiceNow\Location  $location
     * @return string|null
     */
    protected function buildAddress(Location $location): ?string
    {
        // We need at minimum a street name and a city to have a crack at geocoding
        if (empty($location->u_street_name) || empty($location->city)) {
            return null;
        }

        $parts = [];

        // Build the street line
        $street = '';
        if (!empty($location->u_street_number)) {
            $street .= trim($location->u_street_number) . ' ';
        }
        if (!empty($location->u_street_predirectional)) {
            $street .= trim($location->u_street_predirectional) . ' ';
        }
        $street .= trim($location->u_street_name);
        if (!empty($location->u_street_suffix)) {
            $street .= ' ' . trim($location->u_street_suffix);
        }
        if (!empty($location->u_street_postdirectional)) {
            $street .= ' ' . trim($location->u_street_postdirectional);
        }

        // Secondary address (suite, unit, etc.)
        if (!empty($location->u_secondary_unit_indicator) && !empty($location->u_secondary_number)) {
            $street .= ' ' . trim($location->u_secondary_unit_indicator) . ' ' . trim($location->u_secondary_number);
        }

        $parts[] = trim($street);
        $parts[] = trim($location->city);

        if (!empty($location->state)) {
            $parts[] = trim($location->state);
        }
        if (!empty($location->zip)) {
            $parts[] = trim($location->zip);
        }
        if (!empty($location->country)) {
            $parts[] = trim($location->country);
        }

        return implode(', ', array_filter($parts));
    }

    /**
     * Hit the Google Geocoding API and return lat/lng coordinates.
     *
     * @param  string  $address
     * @param  string  $apiKey
     * @return array|null  ['lat' => float, 'lng' => float] or null on failure
     */
    protected function geocodeAddress(string $address, string $apiKey): ?array
    {
        $client = new Client([
            'timeout' => 10,
        ]);

        try {
            $response = $client->get('https://maps.googleapis.com/maps/api/geocode/json', [
                'query' => [
                    'address' => $address,
                    'key'     => $apiKey,
                ],
            ]);

            $body = json_decode($response->getBody()->getContents(), true);

            if (
                isset($body['status']) &&
                $body['status'] === 'OK' &&
                !empty($body['results'][0]['geometry']['location'])
            ) {
                $loc = $body['results'][0]['geometry']['location'];
                return [
                    'lat' => $loc['lat'],
                    'lng' => $loc['lng'],
                ];
            }

            $status = $body['status'] ?? 'UNKNOWN';
            $this->warn("  Google API returned status: " . $status);
            return null;

        } catch (RequestException $e) {
            $this->error("  Guzzle request failed, mate: " . $e->getMessage());
            return null;
        }
    }
}
