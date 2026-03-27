<?php

namespace App\Models\Log;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log as FileLog;

class Log extends Model
{
    use HasFactory;

    protected $table = 'logs';

    protected $fillable = [
        'controller',
        'method',
        'message',
        'status',
    ];

    protected $casts = [
        'status' => 'boolean',
    ];

    /**
     * Create a new log entry.
     *
     * Usage:
     *   Log::createEntry('Device synced successfully.', true, 'SyncNetboxDevice', 'handle');
     *   Log::createEntry('Failed to connect to device.', false, 'DiscoverDeviceJob', 'handle');
     *
     * @param  string       $message     Required log message.
     * @param  bool         $status      true = success, false = failure. Defaults to true.
     * @param  string|null  $controller  The class/controller name originating the log.
     * @param  string|null  $method      The method name originating the log.
     * @return static
     */
    public static function createEntry(
        string $message,
        bool $status = true,
        ?string $controller = null,
        ?string $method = null
    ): static {
        return static::create([
            'controller' => $controller,
            'method'     => $method,
            'message'    => $message,
            'status'     => $status,
        ]);
    }

    /**
     * Write to both the file log and the database log simultaneously.
     *
     * Usage:
     *   Log::log('Device synced successfully.', true, 'SyncDeviceDnsJob', 'handle');
     *   Log::log('Failed to connect.', false, 'DiscoverDeviceJob', 'handle', [], 'provisioning');
     *
     * @param  string       $message     Required log message.
     * @param  bool         $status      true = success, false = failure. Defaults to true.
     * @param  string|null  $controller  The class/controller name originating the log.
     * @param  string|null  $method      The method name originating the log.
     * @param  array        $context     Optional extra context for the file log only.
     * @param  string|null  $channel     Optional log channel (e.g. 'provisioning'). Defaults to the default channel.
     * @return static
     */
    public static function log(
        string $message,
        bool $status = true,
        ?string $controller = null,
        ?string $method = null,
        array $context = [],
        ?string $channel = null
    ): static {
        // Write to file log (on the specified channel, or the default channel)
        $fileContext = array_merge([
            'controller' => $controller,
            'method'     => $method,
            'status'     => $status,
        ], $context);

        $logger = $channel ? FileLog::channel($channel) : FileLog::getFacadeRoot();

        if ($status) {
            $logger->info($message, $fileContext);
        } else {
            $logger->error($message, $fileContext);
        }

        // Write to database log
        return static::createEntry($message, $status, $controller, $method);
    }
}
