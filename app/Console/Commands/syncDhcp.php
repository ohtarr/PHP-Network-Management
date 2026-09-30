<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Netbox\DCIM\Devices;
use App\Models\Netbox\DCIM\VirtualChassis;
use App\Models\Netbox\VIRTUALIZATION\VirtualMachines;
use App\Models\Dhcp\SubnetV4;
use App\Models\Dhcp\ReservationV4;
use App\Models\Dhcp\ReservationV4Collection;

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
    protected $description = 'Sync DHCP reservations from netbox to KEA';

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
        //print "Generated Reservations:" . PHP_EOL;
        //print_r($this->generateReservations());
        //print "Reservations to Delete:" . PHP_EOL;
        //print_r($this->reservationsToDelete());
        $this->deleteReservations();
        //print "Reservations to Add:" . PHP_EOL;
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
            $devices = Devices::where('exclude','config_context')->where('fields','id,name,serial,device_type,primary_ip,virtual_chassis,vc_position,vc_priority,custom_fields')->where('virtual_chassis_member', 'false')->where('name__empty','false')->where('limit','9999')->get();
            $vcs = VirtualChassis::where('fields','id,name,master,member_count,members')->where('limit','9999')->get();
            $merged = $devices->merge($vcs);
            print "Netbox Devices: " . count($merged) . PHP_EOL;
            $this->netboxdevices = $merged;
        }
        return $this->netboxdevices;
    }

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
                $res = $nbdevice->generateKeaReservation();
                if($res)
                {
                    print "Success!" . PHP_EOL;
                    $reservations[] = $res;
                }
            }
            print "Generated Reservations : " . count($reservations) . PHP_EOL;
            $this->generated = new ReservationV4Collection($reservations);
        }
        return $this->generated;
    }

    public function getDhcpScopes()
    {
        if(!$this->scopes)
        {
            $this->scopes = SubnetV4::all();
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
            print "Fetching ALL DHCP Reservations from KEA..." . PHP_EOL;
            $scopes = $this->getDhcpScopes();
            $allres = [];
            foreach($scopes as $scope)
            {
                print "PROCESSING SCOPE {$scope->subnet}" . PHP_EOL;
                try {
                    $reservations = $scope->getReservations();
                } catch (\Exception $e) {
                    print "FAILED to fetch reservations for scope {$scope->subnet}: {$e->getMessage()}" . PHP_EOL;
                    continue;
                }
                foreach($reservations as $res)
                {
                    $allres[] = $res;
                }
            }
            $this->reservations = new ReservationV4Collection($allres);
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
            print "Fetching NETMAN-managed DHCP Reservations from Kea..." . PHP_EOL;
            $nmres = [];
            foreach($this->getAllReservations() as $res)
            {
                if(str_starts_with($res->usercontext->description, "NETMAN-"))
                {
                    $nmres[] = $res;
                }
            }
            $this->nmreservations = new ReservationV4Collection($nmres);
        }
        return $this->nmreservations;
    }

    public function formatMacAddress($mac)
    {
        $hex = strtolower(preg_replace('/[^0-9a-fA-F]/', '', $mac));
        return implode(':', str_split($hex, 2));
    }

    public function reservationsToAdd()
    {
        $add = [];
        foreach($this->generateReservations() as $gres)
        {
            $ipres = null;
            $clientidres = null;
            $hwres = null;
            if($gres->ipAddress)
            {
                $ipres = $this->getAllNetmanReservations()->findByIpAddress($gres->ipAddress)->first();
            }
            if($gres->clientId)
            {
                $clientidres = $this->getAllNetmanReservations()->findByClientId($gres->clientId)->first();
            }
            if($gres->hwAddress)
            {
                $hwres = $this->getAllNetmanReservations()->findByHwAddress($gres->hwAddress)->first();
            }
            if(!$ipres && !$clientidres && !$hwres)
            {
                print "No ipres, clientidres, and hwres for {$gres->ipAddress}... need to add res" . PHP_EOL;
                $add[] = $gres;
            }
        }
        return new ReservationV4Collection($add);
    }

    public function addReservations()
    {
        print "ADDING reservations..." . PHP_EOL;
        foreach($this->reservationsToAdd() as $res)
        {
            print "Processing ADD Reservation {$res->hwAddress}{$res->clientId} - {$res->ipAddress} - {$res->usercontext->description}" . PHP_EOL;
            try{
                $result = ReservationV4::createRaw($res);
            } catch (\Exception $e) {
                print $e->getMessage() . PHP_EOL;
                continue;
            }
            print_r($result);
        }
    }

    public function reservationsToDelete()
    {
        $delete = [];
        foreach($this->getAllNetmanReservations() as $nmkey => $nmres)
        {
            $match = null;
            if($nmres->ipAddress)
            {
                $ipres = $this->generateReservations()->findByIpAddress($nmres->ipAddress)->first();
                if($ipres)
                {
                    if($ipres->hwAddress)
                    {
                        
                        if(!(
                            ($nmres->hwAddress !== null && $ipres->hwAddress !== null && strcasecmp(preg_replace('/[^a-zA-Z0-9]/', '', $nmres->hwAddress), preg_replace('/[^a-zA-Z0-9]/', '', $ipres->hwAddress)) === 0)
                            && ($nmres->usercontext->description !== null && $ipres->usercontext->description !== null && strcasecmp($nmres->usercontext->description, $ipres->usercontext->description) === 0)
                        ))
                        {
                            print_r($nmres);
                            print_r($ipres);
                            $delete[] = $nmres;
                            continue;
                        }
                    } elseif($ipres->clientId) {
                        if(!(
                            ($nmres->clientId !== null && $ipres->clientId !== null && strcasecmp(preg_replace('/[^a-zA-Z0-9]/', '', $nmres->clientId), preg_replace('/[^a-zA-Z0-9]/', '', $ipres->clientId)) === 0)
                            && ($nmres->usercontext->description !== null && $ipres->usercontext->description !== null && strcasecmp($nmres->usercontext->description, $ipres->usercontext->description) === 0)
                        ))
                        {
                            print_r($nmres);
                            print_r($ipres);
                            $delete[] = $nmres;
                            continue;
                        }
                    }
                }
            }
        }
        return new ReservationV4Collection($delete);
    }

    public function deleteReservations()
    {
        print "DELETING reservations..." . PHP_EOL;
        foreach($this->reservationsToDelete() as $res)
        {
            print "Processing DELETE Reservation {$res->hwAddress}{$res->clientId} - {$res->ipAddress} - {$res->usercontext->description}" . PHP_EOL;
            try{
                print_r(ReservationV4::deleteByIp($res->ipAddress));
            } catch (\Exception $e) {
                print "FAILED to delete reservation!" . PHP_EOL;
                continue;
            }
        }
    }

}
