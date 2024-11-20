<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Netbox\DCIM\Devices;
use App\Models\Netbox\DCIM\FrontPorts;
use App\Models\Netbox\DCIM\Racks;
use App\Models\Netbox\DCIM\Cables;

class NetboxCableReport extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'netman:NetboxCableReport {--cableid=} {--rackid=} {--deviceid=} {--filename=}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate a Cable Report';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $this->GenerateCableReport();
    }

    public function GenerateCableReport()
    {
        $options = $this->options();
        if($options['cableid'])
        {
            $cable = Cables::find($options['cableid']);
            $results = $cable->generateReport();
            print_r($results);
            return $results;
        }
        if($options['rackid'])
        {
            $cables = Cables::where('rack_id',$options['rackid'])->limit(9999)->get();
        } elseif($options['deviceid'])
        {
            $cables = Cables::where('device_id',$options['deviceid'])->limit(9999)->get();
        } else {
            print "No cableid, deviceid, or rackid provided!\n";
            return null;
        }
        $results = [];
        foreach($cables as $cable)
        {
            $results[] = $cable->generateReport();
        }
        print_r($results);
        if(isset($options['filename']))
        {

            $filename = $options['filename'];
            if(file_exists($filename))
            {
                unlink($filename);
            }
            $fp = fopen($filename, 'w');
            foreach ($results as $report) {
                //$label = $report['label'][0] . "\n" . $report['label'][1];
                //$flabel = $report['friendly_label'][0] . "\n" . $report['friendly_label'][1];
                $report2 = array_values($report);
                //unset($report2[18]);
                //$report2[18] = $label;
                //unset($report2[19]);
                //$report2[19] = $flabel;
                print_r($report2);
                fputcsv($fp, $report2);
            }
            fclose($fp);
        }
        return $results;
    }

}
