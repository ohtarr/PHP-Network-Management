<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Mist\Site;

class UpdateMistSiteDhcpServers extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'netman:UpdateMistSiteDhcpServers {sitecodes : Comma separated list of Mist site codes}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Update the DHCP_1-4 site variables for a list of Mist sites';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $sitecodes = explode(',', $this->argument('sitecodes'));

        foreach($sitecodes as $sitecode)
        {
            $sitecode = trim($sitecode);
            print "Processing MIST SITE {$sitecode}..." . PHP_EOL;
            $site = Site::findByName($sitecode);
            if(!isset($site->id))
            {
                print "MIST SITE {$sitecode} NOT FOUND... skipping." . PHP_EOL;
                continue;
            }

            $settings = $site->getSettings();
            $vars = isset($settings->vars) ? (array) $settings->vars : [];

            $vars['DHCP_1'] = env('MIST_DHCP_1');
            $vars['DHCP_2'] = env('MIST_DHCP_2');
            $vars['DHCP_3'] = env('MIST_DHCP_3');
            $vars['DHCP_4'] = env('MIST_DHCP_4');

            $params = ['vars' => $vars];

            print "Updating DHCP servers for MIST SITE {$sitecode}..." . PHP_EOL;
            $site->updateSettings($params);
        }
    }
}
