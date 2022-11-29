<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
//use Illuminate\Support\Facades\Redis as RedisManager;
use RedisManager;
use App\Jobs\DiscoverDeviceJob;
use App\Models\Device\Device;

class testredis extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'netman:testredis';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

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
        //print cache()->get();
        //Cache::put('discover10.123.123.123','0',24); 
        //Redis::set('test1','REDIS');

        //$var = Cache::get('discover10.123.123.124');
        //print $var;
        //print "\n";

        print "Change type test!\n";
        $device = Device::find(1);
        $device2 = $device->changeType('App\Models\Device\Cisco\IOSXE');
        print "Device: \n";
        print $device2->type . " " . get_class($device2) . "\n";
        exit();

        DiscoverDeviceJob::dispatch(['ip' => '10.139.217.1'])->onQueue('high');
        DiscoverDeviceJob::dispatch(['ip' => '10.139.217.1'])->onQueue('high');
        DiscoverDeviceJob::dispatch(['ip' => '10.139.217.1'])->onQueue('high');
        DiscoverDeviceJob::dispatch(['ip' => '10.139.217.1'])->onQueue('high');
        DiscoverDeviceJob::dispatch(['ip' => '10.139.217.1'])->onQueue('high');
        DiscoverDeviceJob::dispatch(['ip' => '10.139.217.201'])->onQueue('default');
        DiscoverDeviceJob::dispatch(['ip' => '10.139.217.201'])->onQueue('default');
        return null;

        Cache::store('cache_discovery')->put('10.240.48.1',2,30);
        Cache::store('cache_general')->put('balls',"yes",30);
        return null;
        //$var = Cache::store('discovery')->get('10.240.48.1');
        //var_dump($var);
        //print "\n";

        //$var = \Session::put('session1', 'testname');

        //Native Redis php Extension
        $redis = new \Redis();
        $redis->connect('127.0.0.1', 6379);
        //$redis->flushAll();
        $keys = $redis->keys("*");
        print_r($keys);
        //print_r($redis->get('c:test3'));
        print_r($redis->get('dis:networkautomation_cache_:10.240.48.1'));
        

        //Laravel Redis Facade
        //$var = \Illuminate\Support\Facades\Redis::get('c:test3');
        //print $var;
        //print "\n";

        //$var = Redis::get('networkautomation_cache:test1');
        
        //$var = Redis::get('horizon:23422');
        //print_r($var);
        //$var = \RedisManager::keys('*');
        //print_r($var);
        //print "\n";


    }
}
