<?php

namespace Database\Factories\Log;

use App\Models\Log\Log;
use Illuminate\Database\Eloquent\Factories\Factory;

class LogFactory extends Factory
{
    protected $model = Log::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $controllers = [
            'ManagementController',
            'ProvisioningController',
            'DeprovisioningController',
            'ValidationController',
            'SyncDeviceDnsJob',
            'DiscoverDeviceJob',
            'SyncDeviceLibreNMSJob',
        ];

        $methods = [
            'handle',
            'index',
            'store',
            'show',
            'update',
            'destroy',
            'getSiteSummary',
            'syncNetboxDevice',
            'deployNetboxSite',
            'validateNetboxSite',
        ];

        $messages = [
            'Successfully retrieved NETBOX SITE.',
            'Successfully deployed Mist site.',
            'Device discovery completed.',
            'DNS record created successfully.',
            'Failed to connect to device.',
            'Unable to find NETBOX SITE.',
            'Webhook received and processed.',
            'Validation passed for site.',
            'DHCP scope deployed successfully.',
            'Job completed successfully.',
        ];

        return [
            'controller' => $this->faker->randomElement($controllers),
            'method'     => $this->faker->randomElement($methods),
            'message'    => $this->faker->randomElement($messages),
            'status'     => $this->faker->boolean(80), // 80% success rate
        ];
    }

    /**
     * Indicate a successful log entry.
     */
    public function success(): static
    {
        return $this->state(['status' => true]);
    }

    /**
     * Indicate a failed log entry.
     */
    public function failure(): static
    {
        return $this->state(['status' => false]);
    }
}
