<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Netbox\DCIM\Devices;
use App\Models\Netbox\DCIM\VirtualChassis;
use App\Models\Netbox\VIRTUALIZATION\VirtualMachines;
use App\Models\Gizmo\Dhcp;
use App\Models\Gizmo\DNS\Cname;

class syncDhcp extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'netman:syncDhcp';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync DHCP reservations from netbox to DNS';

    /**
     * Execute the console command.
     *
     * @return int
     */

    public $scopes;
    public $netboxdevices;
    public $generated;
    public $reservations;
    public $nmreservations;

    public function handle()
    {
        $start = microtime(true);
        //print count($this->getAllReservations()) . PHP_EOL;
        //print count($this->getAllNetmanReservations()) . PHP_EOL;
        //print count($this->reservationsToDelete()) . PHP_EOL;
        //print count($this->reservationsToAdd()) . PHP_EOL;
        //print_r($this->reservationsToDelete());
        $this->deleteReservations();
        //print_r($this->reservationsToAdd());
        $this->addReservations();
        $end = microtime(true);
        $duration = $end - $start;
        print "Completed in {$duration} seconds." . PHP_EOL;
    }

    public function getNetboxDevices()
    {
        if(!$this->netboxdevices)
        {
            print "Fetching Netbox Devices..." . PHP_EOL;
            $devices = Devices::where('virtual_chassis_member', 'false')->where('name__empty','false')->where('limit','9999')->get();
            $vcs = VirtualChassis::where('limit','1000')->get();
            $merged = $devices->merge($vcs);
            print count($merged) . PHP_EOL;
            $this->netboxdevices = $merged;
        }
        return $this->netboxdevices;
    }

/*                 [1651] => Array
                (
                    [scopeId] => 10.140.64.0
                    [clientId] => 36-34-63-33-64-36-61-32-37-64-38-30-2d-30
                    [ipAddress] => 10.140.64.65
                    [description] => NETMAN-TUSGAAAOSWA0101
                )
 */
    public function generateReservations()
    {
        if(!$this->generated)
        {
            print "Generating DHCP Reservations from Netbox Devices..." . PHP_EOL;
            $reservations = [];
            $nbdevices = $this->getNetboxDevices();
            foreach($nbdevices as $nbdevice)
            {
                print "Generating DHCP Reservations for device {$nbdevice->name} ..." . PHP_EOL;
                unset($res);
                $res = $nbdevice->generateDhcpReservation();
                if($res)
                {
                    print "Success!" . PHP_EOL;
                    $reservations[] = $res;
                }
            }
            print count($reservations) . PHP_EOL;
            $this->generated = collect($reservations);
        }
        return $this->generated;
    }

    public function getDhcpScopes()
    {
        if(!$this->scopes)
        {
            $this->scopes = Dhcp::all();
        }
        return $this->scopes;
    }

    public function findDhcpScopeCached($scopeid)
    {
        return $this->getDhcpScopes()->where('scopeID', $scopeid)->first();
    }

    public function getAllReservations()
    {
        if(!$this->reservations)
        {
            print "Fetching ALL DHCP Reservations from Gizmo..." . PHP_EOL;
            $scopes = $this->getDhcpScopes();
            $allres = [];
            foreach($scopes as $scope)
            {
                print "PROCESSING SCOPE {$scope->scopeID}" . PHP_EOL;
                $reservations = $scope->getReservations();
                foreach($reservations as $res)
                {
                    $allres[] = $res;
                }
            }
            $this->reservations = collect($allres);
        }
        return $this->reservations;
    }

/*       [
        "ipAddress" => "10.13.177.139",
        "scopeId" => "10.13.176.0",
        "clientId" => "00-0f-e5-0f-bb-29",
        "name" => "MAC000FE50FBB29.kiewitplaza.com",
        "description" => "NETMAN-KHONEFDFPDU0101",
        "supportedType" => "Both",
      ],
 */
    public function getAllNetmanReservations()
    {
        if(!$this->nmreservations)
        {
            print "Fetching NETMAN-managed DHCP Reservations from Gizmo..." . PHP_EOL;
            $nmres = [];
            foreach($this->getAllReservations() as $res)
            {
                if(str_starts_with($res['description'], "NETMAN-"))
                {
                    $nmres[] = $res;
                }
            }
            $this->nmreservations = collect($nmres);
        }
        return $this->nmreservations;
    }

    public function reservationsToAdd()
    {
        $add = [];
        foreach($this->generateReservations() as $gres)
        {
            $match = null;
            foreach($this->getAllNetmanReservations() as $nmres)
            {
                if($nmres['clientId'] == $gres['clientId'])
                {
                    $match = $nmres;
                    break;
                }
            }
            if(!$match)
            {
                $add[] = $gres;
            }
        }
        return collect($add);
    }

    public function addReservations()
    {
        print "ADDING reservations..." . PHP_EOL;
        foreach($this->reservationsToAdd() as $res)
        {
            print "Processing ADD Reservation {$res['clientId']} - {$res['ipAddress']} - {$res['description']}" . PHP_EOL;
            $scope = null;
            $scope = $this->findDhcpScopeCached($res['scopeId']);
            if(!$scope)
            {
                print "No Scope found! skipping!" . PHP_EOL;
                continue;
            }
            try{
                $res = $scope->addReservation($res['clientId'], $res['ipAddress'], $res['description']);
            } catch (\Exception $e) {
                print $e->getMessage() . PHP_EOL;
                continue;
            }
            print_r($res);
        }
    }

    public function reservationsToDelete()
    {
        $delete = [];
        foreach($this->getAllNetmanReservations() as $nmres)
        {
            $match = null;
            foreach($this->generateReservations() as $gres)
            {
                if($nmres['clientId'] == $gres['clientId'])
                {
                    if($nmres['ipAddress'] == $gres['ipAddress'] && $nmres['description'] == $gres['description'])
                    {
                        $match = $gres;
                    }
                    break;
                }
            }
            if(!$match)
            {
                $delete[] = $nmres;
            }
        }
        return collect($delete);
    }

    public function deleteReservations()
    {
        print "DELETING reservations..." . PHP_EOL;
        foreach($this->reservationsToDelete() as $res)
        {
            print "Processing DELETE Reservation {$res['clientId']} - {$res['ipAddress']} - {$res['description']}" . PHP_EOL;
            try{
                print_r(Dhcp::deleteReservation($res['ipAddress']));
            } catch (\Exception $e) {
                print "FAILED to delete reservation!" . PHP_EOL;
                continue;
            }
        }
    }

}
