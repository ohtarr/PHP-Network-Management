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

        $usernames = [
            'jsmith@kiewit.com',
            'bjones@kiewit.com',
            'awilliams@kiewit.com',
            null, // system/queue processes
        ];

        return [
            'message'  => $this->faker->randomElement($messages),
            'username' => $this->faker->randomElement($usernames),
        ];
    }

    /**
     * Indicate a log entry from an authenticated user.
     */
    public function withUser(string $username = 'jsmith@kiewit.com'): static
    {
        return $this->state(['username' => $username]);
    }

    /**
     * Indicate a system/queue log entry (no authenticated user).
     */
    public function system(): static
    {
        return $this->state(['username' => null]);
    }
}
