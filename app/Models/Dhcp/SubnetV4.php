<?php

namespace App\Models\Dhcp;

use App\Models\Dhcp\BaseModel;
use GuzzleHttp\Client as GuzzleHttpClient;
use App\Models\Azure\Azure;
use IPv4\Subnet as SubnetCalculator;
use App\Models\Dhcp\ReservationV4;

#[\AllowDynamicProperties]
class SubnetV4 extends BaseModel
{
    protected static $model = "subnetv4";

    public function network()
    {
        return SubnetCalculator::fromCidr($this->subnet)->networkAddress()->asQuads();
    }

    public function length()
    {
        return SubnetCalculator::fromCidr($this->subnet)->networkSize();
    }

    public function netmask()
    {
        return SubnetCalculator::fromCidr($this->subnet)->mask()->asQuads();
    }

    public static function all()
    {
        $path = static::getPath();
        $response = static::getQuery()->get($path);
        return static::hydrateMany($response[0]->arguments->subnets);
    }

    public static function find(int $id)
    {
        if(!$id)
        {
            return null;
        }
        $path = 'subnetv4/id/' . $id;
        $response = static::getQuery()->get($path);
        if(isset($response[0]->arguments->subnets[0]))
        {
            return static::hydrateOne($response[0]->arguments->subnets[0]);
        } else {
            return null;
        }

    }

    public function fresh()
    {
        return static::find($this->id);
    }

    public static function allBySite($sitecode)
    {
        if(!$sitecode)
        {
            return null;
        }
        $path = 'subnetv4/site/' . $sitecode;
        $response = static::getQuery()->get($path);
        return static::hydrateMany($response);
    }

    public static function findBySubnet(string $subnet, int $mask)
    {
        if(!$subnet || !$mask)
        {
            return null;
        }
        $path = 'subnetv4/' . $subnet . '/' . $mask;
        $response = static::getQuery()->get($path);
        if(isset($response[0]->arguments->subnets[0]))
        {
            return static::hydrateOne($response[0]->arguments->subnets[0]);
        } else {
            return null;
        }

    }

    public static function findByIp(string $ip)
    {
        if(!$ip)
        {
            return null;
        }
        $path = 'subnetv4/' . $ip;
        $response = static::getQuery()->get($path);
        if(isset($response[0]->arguments->subnets[0]))
        {
            return static::hydrateOne($response[0]->arguments->subnets[0]);
        } else {
            return null;
        }

    }

    /**
     * Create a new DHCPv4 subnet.
     * POST /api/dhcp/subnetv4
     *
     * @param  array  $params   Array properly formatted for POST to KEA DHCP api.
     */
    public static function create(array $params) {
        $response = static::getQuery()->post(static::getPath(), $params);
        if(isset($response[0]->arguments->subnets[0]))
        {
            return static::hydrateOne($response[0]->arguments->subnets[0]);
        } else {
            return null;
        }
    }

    /**
     * Delete a DHCPv4 subnet by its Kea subnet ID.
     * DELETE /api/dhcp/subnetv4/id/{id}
     *
     * @param  int  $id  The Kea subnet ID (e.g. 10120).
     */
    public static function deleteById(int $id)
    {
        if (!$id) {
            return null;
        }
        $path = static::getPath() . "/" . $id;
        return static::getQuery()->delete($path);
    }

    /**
     * Delete a DHCPv4 subnet by its network address and prefix length.
     * DELETE /api/dhcp/subnetv4/{subnet}/{length}
     *
     * @param  string  $subnet  The network address (e.g. "10.1.2.0").
     * @param  int     $length    The prefix length (e.g. 24).
     */
    public static function deleteBySubnet(string $subnet, int $length)
    {
        if (!$subnet || !$length) {
            return null;
        }
        $path = static::getPath() . '/' . $subnet . '/' . $length;
        return static::getQuery()->delete($path);
    }

    public function delete()
    {
        if(isset($this->subnet))
        {
            return static::deleteBySubnet($this->network(), $this->length());
        }
    }

    public static function findParent($network)
    {
        return static::findByIp($network);
    }


    public static function findChildren($network, $bitmask)
    {
        $overlaps = [];
        $ipcalc = new SubnetCalculator($network, $bitmask);
        $longstart = ip2long($ipcalc->networkAddress()->asQuads());
        $longend = ip2long($ipcalc->broadcastAddress()->asQuads());
        $scopes = self::all();
        foreach($scopes as $scope){
            if(ip2long($scope->network()) >= $longstart && ip2long($scope->network()) <= $longend){
                $overlaps[] = $scope;
            }
        }
        return collect($overlaps);
    }

    public function getReservations()
    {
        return ReservationV4::allBySubnet($this->network());
    }

    public function findOption($optionid)
    {
        if(isset($this->optionData))
        {
            foreach($this->optionData as $option)
            {
                if($option->code == $optionid)
                {
                    return $option;
                }
            }
        }
    }

}
