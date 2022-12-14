<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class processTcpdump extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'netman:processTcpdump';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Process TCPDUMP packets for autodiscovery';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $INCREMENT = 300;
        $TIME = time(); // Get our start time, we rotate the IPS array every 60 secondsish.

        while ( !feof(STDIN) )                  // Loop while there is a STDIN stream
        {
            $LINE = fgets(STDIN, 8192);     // Get a line of input to process
            $REGEX = "/^\S+\s+IP\s+(\d+\.\d+\.\d+\.\d+)\.\d+\s>\s\d+\.\d+\.\d+\.\d+\.\d+:\s.+$/";
            if ( preg_match($REGEX,$LINE,$MATCH) )
            {
                // process the output:
                // Increment the number of hits we have seen this ip for THIS TIME INTERVAL!
                if(!isset($IPS[$MATCH[1]]))
                { 
                    $IPS[$MATCH[1]] = 1;
                }
            }
            // Process rotation of the IPS for each time interval
            $NOW = time();
            if ($NOW > $TIME + $INCREMENT)  // If we are outside the time interval, rotate the IPS array!
            {
                foreach($IPS as $ip => $hits)
                {
                    $this->call('netman:AutoDiscoverDevice',['--ip' => $ip]);
                }
                $IPS = [];
                $TIME = $NOW;
            }
        }

/*         while ( !feof(STDIN) )                  // Loop while there is a STDIN stream
        {
            $LINE = fgets(STDIN, 8192);     // Get a line of input to process
            $REGEX = "/IP\s+(\d+\.\d+\.\d+\.\d+)\.\d+\s>\s\d+\.\d+\.\d+\.\d+\.\d+/";
            if ( preg_match($REGEX,$LINE,$MATCH) )
            {
                $this->call('netman:AutoDiscoverDevice',['--ip' => $MATCH[1]]);
            }
        } */
    }
}