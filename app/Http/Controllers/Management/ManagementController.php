<?php

namespace App\Http\Controllers\Management;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Netbox\DCIM\Devices;
use App\Models\Netbox\DCIM\Sites;
use App\Models\Device\Device;
use App\Models\Mist\Site;
use App\Models\Mist\Device as MistDevice;
use App\Models\Device\Output;
use App\Jobs\SyncDeviceDnsJob;
use App\Jobs\SyncDeviceLibreNMSJob;
use App\Jobs\SyncVirtualMachineDnsJob;
use App\Jobs\SyncVirtualMachineLibreNMSJob;
use App\Models\Netbox\VIRTUALIZATION\VirtualMachines;
use Illuminate\Support\Facades\Log;
use App\Models\Log\Log as DbLog;

class ManagementController extends Controller
{
    public $logs = [];
    
    public function __construct()
    {
        $this->middleware('auth:api')->except(['syncNetboxDevice', 'syncNetboxVirtualMachine']);
    }

    public function addLog($status, $msg)
    {
        $username = auth()->user()?->userPrincipalName;
        $class = class_basename($this);
        $msg1 = $class . ": " . debug_backtrace()[1]['function'] . ": " . $msg;
        $this->logs[] = [
            'status' => $status,
            'msg'    => $msg,
        ];

        DbLog::log($msg1, $username, 'provisioning');
    }

    /**
     * @OA\Get(
     *     path="/management/netbox/sites/",
     *     summary="Get all Netbox sites (brief)",
     *     tags={"Management"},
     *     security={{"oauth2":{"openid","profile","email","api://915c46fe-ee91-41c7-98ab-b257b04ea7ec/access_as_user"}}},
     *     @OA\Response(
     *         response=200,
     *         description="List of all Netbox sites in brief format",
     *         @OA\JsonContent(type="array", @OA\Items(type="object"))
     *     ),
     *     @OA\Response(response=401, description="Unauthorized")
     * )
     */
    public function getNetboxSites()
    {
        $return = Sites::where('brief', 1)->get();
        return response()->json($return);
    }

    /**
     * @OA\Get(
     *     path="/management/netbox/{sitecode}/devices/",
     *     summary="Get a site summary including Netbox devices and their Mist status",
     *     description="Returns the Netbox site, Mist site, and a list of devices with their corresponding Mist device records and site assignments.",
     *     tags={"Management"},
     *     security={{"oauth2":{"openid","profile","email","api://915c46fe-ee91-41c7-98ab-b257b04ea7ec/access_as_user"}}},
     *     @OA\Parameter(
     *         name="sitecode",
     *         in="path",
     *         required=true,
     *         description="The site code to retrieve the summary for",
     *         @OA\Schema(type="string", example="SITE01")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Site summary with devices",
     *         @OA\JsonContent(
     *             type="array",
     *             @OA\Items(
     *                 @OA\Property(property="status", type="integer", example=1),
     *                 @OA\Property(property="log", type="array", @OA\Items(type="object")),
     *                 @OA\Property(property="data", type="object",
     *                     @OA\Property(property="netboxsite", type="object"),
     *                     @OA\Property(property="mistsite", type="object"),
     *                     @OA\Property(property="devices", type="array", @OA\Items(type="object"))
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthorized")
     * )
     */
    public function getSiteSummary($sitecode)
    {
        $nbsite = Sites::where('name__ie', $sitecode)->first();
        if(!isset($nbsite->id))
        {
            $this->addLog(0, "Unable to find NETBOX SITE.");
        }
        $this->addLog(1, "Successfully retreived NETBOX SITE.");
        $nbdevices = Devices::where('site_id', $nbsite->id)->where('name__empty', 'false')->where('exclude','config_context')->get();

        $mistsite = $nbsite->getMistSite();
        if(!isset($mistsite->id))
        {
            $this->addLog(0, "Unable to find MIST SITE.");
        }
        $this->addLog(1, "Successfully retreived MIST SITE.");
        $allmistdevices = MistDevice::where('vc', 'true')->get();
        $devices = [];
        foreach($nbdevices as $nbdevice)
        {
            unset($vcmaster);
            unset($mistdevice);
            unset($devicecustom);
            $mistdevicesite = null;
            $nbdevice->custom['mistdevice'] = null;
            if(!$nbdevice->serial)
            {
                continue;
            }
            $mistdevice = $allmistdevices->where('serial', strtoupper($nbdevice->serial))->first();
            if(!isset($mistdevice->mac))
            {
                $mistdevice = $allmistdevices->where('serial', strtolower($nbdevice->serial))->first();
            }
            if(isset($mistdevice->mac))
            {
                if(isset($mistdevice->site_id))
                {
                    $mistdevicesite = $mistdevice->site_id;
                } else {
                    if(isset($mistdevice->vc_mac))
                    {
                        $vcmaster = $allmistdevices->where('mac', $mistdevice->vc_mac)->first();
                        if(isset($vcmaster->site_id))
                        {
                            $mistdevicesite = $vcmaster->site_id;
                        }
                    }
                }
                $nbdevice->custom['mistdevice'] = $mistdevice;
                $nbdevice->custom['mistdevicesite'] = $mistdevicesite;
            }
            $devices[] = $nbdevice;
        }

        $return = [
            [
                'status'    =>  1,
                'data'  =>  [
                    'netboxsite'    =>  $nbsite,
                    'mistsite'  =>  $mistsite,
                    'devices'   =>  $devices,
                ],
                'log'   =>   $this->logs,
            ]

        ];
        return response()->json($return);
    }

    /**
     * @OA\Post(
     *     path="/management/netbox/webhook/device",
     *     summary="Receive a Netbox webhook for device changes and dispatch sync jobs",
     *     description="Accepts a Netbox webhook payload for device create/update/delete events. Dispatches SyncDeviceDnsJob and SyncDeviceLibreNMSJob. This endpoint does not require authentication.",
     *     tags={"Management"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="model", type="string", example="device", description="Must be 'device' to be processed"),
     *             @OA\Property(property="event", type="string", example="updated", description="Event type: created, updated, or deleted"),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="id", type="integer", example=42, description="Netbox device ID"),
     *                 @OA\Property(property="name", type="string", example="SITE01-SW-1", description="Device name")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Jobs dispatched successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="integer", example=1),
     *             @OA\Property(property="message", type="string", example="SyncDeviceDnsJob and SyncDeviceLibreNMSJob dispatched for Netbox device ID 42 (event: updated)."),
     *             @OA\Property(property="netbox_device_id", type="integer", example=42),
     *             @OA\Property(property="event", type="string", example="updated"),
     *             @OA\Property(property="name", type="string", example="SITE01-SW-1")
     *         )
     *     ),
     *     @OA\Response(response=422, description="Missing device ID in payload"),
     *     @OA\Response(response=404, description="Netbox device not found")
     * )
     */
    public function syncNetboxDevice(Request $request)
    {
        $model = $request->input('model');
        if ($model !== 'device') {
            return response()->json([
                'status'  => 0,
                'message' => "Webhook model '{$model}' is not 'device', ignoring.",
            ], 200);
        }

        $netboxDeviceId = $request->input('data.id');
        if (!$netboxDeviceId) {
            return response()->json([
                'status'  => 0,
                'message' => 'No device ID found in webhook payload (data.id).',
            ], 422);
        }

        $netboxDeviceId = (int) $netboxDeviceId;

        $event      = $request->input('event', 'updated');
        $deviceName = $request->input('data.name');

        Log::info('ManagementController@syncNetboxDevice: webhook received', [
            'event'            => $event,
            'netbox_device_id' => $netboxDeviceId,
            'name'             => $deviceName,
        ]);

        if ($event !== 'deleted') {
            $device = Devices::find($netboxDeviceId);
            if (!isset($device->id)) {
                return response()->json([
                    'status'  => 0,
                    'message' => "Netbox device ID {$netboxDeviceId} not found.",
                ], 404);
            }
        }

        SyncDeviceDnsJob::dispatch($netboxDeviceId, $event, $deviceName);
        SyncDeviceLibreNMSJob::dispatch($netboxDeviceId, $event, $deviceName);
        SyncDeviceLibreNMSJob::dispatch($netboxDeviceId, $event, $deviceName)->delay(1800);

        return response()->json([
            'status'           => 1,
            'message'          => "SyncDeviceDnsJob and SyncDeviceLibreNMSJob dispatched for Netbox device ID {$netboxDeviceId} (event: {$event}).",
            'netbox_device_id' => $netboxDeviceId,
            'event'            => $event,
            'name'             => $deviceName ?? null,
        ]);
    }

    /**
     * @OA\Post(
     *     path="/management/netbox/webhook/virtual-machine",
     *     summary="Receive a Netbox webhook for virtual machine changes and dispatch sync jobs",
     *     description="Accepts a Netbox webhook payload for virtual-machine create/update/delete events. Dispatches SyncVirtualMachineDnsJob and SyncVirtualMachineLibreNMSJob. This endpoint does not require authentication.",
     *     tags={"Management"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="model", type="string", example="virtual-machine", description="Must be 'virtual-machine' to be processed"),
     *             @OA\Property(property="event", type="string", example="updated", description="Event type: created, updated, or deleted"),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="id", type="integer", example=42, description="Netbox virtual machine ID"),
     *                 @OA\Property(property="name", type="string", example="vm-site01-web01", description="Virtual machine name")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Jobs dispatched successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="integer", example=1),
     *             @OA\Property(property="message", type="string", example="SyncVirtualMachineDnsJob and SyncVirtualMachineLibreNMSJob dispatched for Netbox VM ID 42 (event: updated)."),
     *             @OA\Property(property="netbox_vm_id", type="integer", example=42),
     *             @OA\Property(property="event", type="string", example="updated"),
     *             @OA\Property(property="name", type="string", example="vm-site01-web01")
     *         )
     *     ),
     *     @OA\Response(response=422, description="Missing virtual machine ID in payload"),
     *     @OA\Response(response=404, description="Netbox virtual machine not found")
     * )
     */
    public function syncNetboxVirtualMachine(Request $request)
    {
        $model = $request->input('model');
        if ($model !== 'virtualmachine') {
            Log::warning('ManagementController@syncNetboxVirtualMachine: ignoring non-virtualmachine model', [
                'model' => $model,
            ]);
            return response()->json([
                'status'  => 0,
                'message' => "Webhook model '{$model}' is not 'virtualmachine', ignoring.",
            ], 200);
        }

        $netboxVmId = $request->input('data.id');
        if (!$netboxVmId) {
            Log::warning('ManagementController@syncNetboxVirtualMachine: no VM ID in payload, rejecting');
            return response()->json([
                'status'  => 0,
                'message' => 'No virtual machine ID found in webhook payload (data.id).',
            ], 422);
        }

        $netboxVmId = (int) $netboxVmId;

        $event  = $request->input('event', 'updated');
        $vmName = $request->input('data.name');

        Log::info('ManagementController@syncNetboxVirtualMachine: webhook received', [
            'event'       => $event,
            'netbox_vm_id' => $netboxVmId,
            'name'        => $vmName,
        ]);

        if ($event !== 'deleted') {
            $vm = VirtualMachines::find($netboxVmId);
            if (!isset($vm->id)) {
                Log::error('ManagementController@syncNetboxVirtualMachine: VM not found in Netbox', [
                    'netbox_vm_id' => $netboxVmId,
                ]);
                return response()->json([
                    'status'  => 0,
                    'message' => "Netbox virtual machine ID {$netboxVmId} not found.",
                ], 404);
            }
        }

        SyncVirtualMachineDnsJob::dispatch($netboxVmId, $event, $vmName);
        SyncVirtualMachineLibreNMSJob::dispatch($netboxVmId, $event, $vmName);
        SyncVirtualMachineLibreNMSJob::dispatch($netboxVmId, $event, $vmName)->delay(1800);

        Log::info('ManagementController@syncNetboxVirtualMachine: jobs dispatched', [
            'netbox_vm_id' => $netboxVmId,
            'event'        => $event,
            'name'         => $vmName ?? null,
        ]);

        return response()->json([
            'status'       => 1,
            'message'      => "SyncVirtualMachineDnsJob and SyncVirtualMachineLibreNMSJob dispatched for Netbox VM ID {$netboxVmId} (event: {$event}).",
            'netbox_vm_id' => $netboxVmId,
            'event'        => $event,
            'name'         => $vmName ?? null,
        ]);
    }

    /**
     * @OA\Get(
     *     path="/management/search",
     *     summary="Search device command outputs",
     *     description="Searches stored device command outputs for a given string and returns matching devices with their output snippets.",
     *     tags={"Management"},
     *     security={{"oauth2":{"openid","profile","email","api://915c46fe-ee91-41c7-98ab-b257b04ea7ec/access_as_user"}}},
     *     @OA\Parameter(
     *         name="search",
     *         in="query",
     *         required=true,
     *         description="The string to search for in device outputs",
     *         @OA\Schema(type="string", example="10.1.1.1")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="List of matching devices with output snippets, sorted by device name",
     *         @OA\JsonContent(
     *             type="array",
     *             @OA\Items(
     *                 @OA\Property(property="id", type="integer", example=42),
     *                 @OA\Property(property="name", type="string", example="SITE01-SW-1"),
     *                 @OA\Property(property="model", type="string", example="EX4300-48P"),
     *                 @OA\Property(property="role", type="string", example="Access Switch"),
     *                 @OA\Property(property="site", type="string", example="SITE01"),
     *                 @OA\Property(property="ip", type="string", example="10.1.1.1"),
     *                 @OA\Property(property="outputs", type="array", @OA\Items(
     *                     @OA\Property(property="type", type="string", example="show version"),
     *                     @OA\Property(property="data", type="string", example="Junos: 21.4R3")
     *                 ))
     *             )
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthorized")
     * )
     */
    public function searchOutputs(Request $request)
    {
        $return = [];
        $submitted = $request->collect();
        if(!isset($submitted['search']))
        {
            return null;
        }
        $search = $submitted['search'];
        $deviceids = [];
        $array = [];
        $outputs = Output::where('data', 'like', '%' . $search . '%')->get();
        foreach($outputs as $output)
        {
            $array[$output->device_id]['outputs'][] = $output;
        }
        foreach($array as $id => $value)
        {
            unset($tmp);
            unset($ip);
            unset($device);
            $device = Device::find($id);
            if(!$device)
            {
                continue;
            }
            $nbdevice = $device->getNetboxDevice();
            if(!$nbdevice)
            {
                continue;
            }
            $tmp['id'] = $nbdevice->id;
            $tmp['name'] = $nbdevice->name;
            if(isset($nbdevice->device_type->model))
            {
                $tmp['model'] = $nbdevice->device_type->model;
            }
            $tmp['role'] = $nbdevice->role->name;
            if(isset($nbdevice->site->name))
            {
                $tmp['site'] = $nbdevice->site->name;
            }
            if(isset($nbdevice->location->name))
            {
                $tmp['location'] = $nbdevice->location->name;
            }
            $ip = $nbdevice->getIpAddress();
            if(isset($ip))
            {
                $tmp['ip'] = $ip;
            }
            foreach($value['outputs'] as $output)
            {
                $tmp['outputs'][] = ['type'=>$output->type, 'data'=>$output->data];
            }
            $return[] = $tmp;
        }
        usort($return, function($a, $b) {
            return $a['name'] <=> $b['name'];
        });
        return response()->json($return);
    }
}
