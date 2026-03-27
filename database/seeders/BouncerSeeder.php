<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Silber\Bouncer\BouncerFacade as Bouncer;

class BouncerSeeder extends Seeder
{
    /**
     * All Eloquent model classes that receive model-scoped abilities.
     */
    protected function models(): array
    {
        return [
            \App\Models\Device\Device::class,
            \App\Models\Device\Aruba\Aruba::class,
            \App\Models\Device\Cisco\Cisco::class,
            \App\Models\Device\Cisco\IOS\CiscoIOS::class,
            \App\Models\Device\Cisco\IOSXE\CiscoIOSXE::class,
            \App\Models\Device\Cisco\IOSXR\CiscoIOSXR::class,
            \App\Models\Device\Cisco\NXOS\CiscoNXOS::class,
            \App\Models\Device\Juniper\Juniper::class,
            \App\Models\Device\Opengear\Opengear::class,
            \App\Models\Device\Ubiquiti\Ubiquiti::class,
            \App\Models\Location\Site\Site::class,
            \App\Models\Location\Address\Address::class,
            \App\Models\Location\Building\Building::class,
            \App\Models\Location\Room\Room::class,
            \App\Models\ServiceNow\Incident::class,
            \App\Models\Mist\Device::class,
            \App\Models\Mist\Site::class,
        ];
    }

    /**
     * Custom (non-model) provisioning abilities.
     */
    protected function customAbilities(): array
    {
        return [
            'provision-netbox-sites',
            'provision-netbox-devices',
            'provision-dhcp-scopes',
            'provision-mist-sites',
            'provision-mist-devices',
        ];
    }

    /**
     * Seed Bouncer roles and abilities.
     *
     * Roles:
     *   - Engineer     : full CRUD on all models + all custom provisioning abilities
     *   - FieldServices: read-only on all models
     *
     * Run with: php artisan db:seed --class=BouncerSeeder
     */
    public function run(): void
    {
        // ── Engineer role ─────────────────────────────────────────────────────
        // Full CRUD on every model
        foreach ($this->models() as $model) {
            foreach (['create', 'read', 'update', 'delete'] as $ability) {
                Bouncer::allow('Engineer')->to($ability, $model);
            }
        }

        // Custom provisioning abilities (not model-scoped)
        foreach ($this->customAbilities() as $ability) {
            Bouncer::allow('Engineer')->to($ability);
        }

        // ── FieldServices role ────────────────────────────────────────────────
        // Read-only on every model
        foreach ($this->models() as $model) {
            Bouncer::allow('FieldServices')->to('read', $model);
        }

        // Clear the Bouncer permission cache so changes take effect immediately
        Bouncer::refresh();

        $this->command->info('Bouncer roles and abilities seeded successfully.');
        $this->command->info('  Engineer     : create/read/update/delete on all models + all provisioning abilities');
        $this->command->info('  FieldServices: read-only on all models');
    }
}
