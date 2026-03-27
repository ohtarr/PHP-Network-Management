<?php

namespace App\Models\Log;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log as FileLog;

class Log extends Model
{
    use HasFactory;

    protected $table = 'logs';

    // Only track created_at — logs are immutable, no updated_at needed
    const UPDATED_AT = null;

    protected $fillable = [
        'message',
        'username',
    ];

    /**
     * Create a new log entry in the database.
     *
     * Usage:
     *   Log::createEntry('Device synced successfully.');
     *   Log::createEntry('Failed to connect.', 'jsmith@kiewit.com');
     *
     * @param  string       $message   Required log message.
     * @param  string|null  $username  The authenticated user's principal name, or null for system/queue processes.
     * @return static
     */
    public static function createEntry(
        string $message,
        ?string $username = null
    ): static {
        return static::create([
            'message'  => $message,
            'username' => $username,
        ]);
    }

    /**
     * Write to both the file log and the database log simultaneously.
     *
     * Usage:
     *   // From a job (no authenticated user):
     *   Log::log('SyncDeviceDnsJob completed for device 123.');
     *
     *   // From a controller (authenticated user, custom channel):
     *   Log::log('Deployed Mist site XYZ.', 'jsmith@kiewit.com', 'provisioning');
     *
     * @param  string       $message   Required log message.
     * @param  string|null  $username  The authenticated user's principal name, or null.
     * @param  string|null  $channel   Optional log channel (e.g. 'provisioning'). Defaults to the default channel.
     * @return static
     */
    public static function log(
        string $message,
        ?string $username = null,
        ?string $channel = null
    ): static {
        // Write to file log (on the specified channel, or the default channel)
        $context = ['username' => $username];

        $logger = $channel ? FileLog::channel($channel) : FileLog::getFacadeRoot();
        $logger->info($message, $context);

        // Write to database log
        return static::createEntry($message, $username);
    }
}
