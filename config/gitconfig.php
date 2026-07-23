<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Running Config Line Filters
    |--------------------------------------------------------------------------
    | An array of regex patterns. Any line in a device's running configuration
    | that matches one of these patterns will be removed before the config is
    | saved to the git repository file. Blank/whitespace-only lines are always
    | removed regardless of this list.
    |
    | The goal is to strip lines that change on every config save (timestamps,
    | checksums, clock-drift values, etc.) so that git diffs only reflect
    | meaningful configuration changes.
    */
    'line_filters' => [

        // ---------------------------------------------------------------
        // Cisco IOS / IOS-XE
        // ---------------------------------------------------------------
        // "! Last configuration change at 14:23:01 UTC Tue Jul 1 2026 by admin"
        '/^! Last configuration change at /',
        // "! NVRAM config last updated at 14:23:01 UTC Tue Jul 1 2026"
        '/^! NVRAM config last updated at /',
        // "ntp clock-period 17179869"  — recalculates on every reboot
        '/^ntp clock-period /',

        // ---------------------------------------------------------------
        // Cisco IOS-XR
        // ---------------------------------------------------------------
        // "!! Last configuration change at Tue Jul  1 14:23:01 2026 by admin"
        '/^!! Last configuration change at /',
        // "!! IOS XR Configuration 7.3.2"  — changes on software upgrade
        '/^!! IOS XR Configuration /',

        // ---------------------------------------------------------------
        // Cisco ASA
        // ---------------------------------------------------------------
        // ": Written by admin at 14:23:01.123 UTC Tue Jul 1 2026"
        '/^: Written by .+ at /',
        // ": Saved"  — appears at the top of every saved config
        '/^: Saved\s*$/',
        // "Cryptochecksum: a1b2c3d4e5f6..."  — recalculated on every write mem
        '/^Cryptochecksum:/',

        // ---------------------------------------------------------------
        // Juniper JunOS
        // ---------------------------------------------------------------
        // "## Last changed: 2026-07-01 14:23:01 UTC"
        '/^\s*## Last changed: /',
        // "## Anchors used: 2"  — internal counter, changes frequently
        '/^\s*## Anchors used: /',
        // JSON/NETCONF output: "junos:commit-seconds" : "1781960161"
        '/^\s*"junos:commit-seconds"\s*:/',
        // JSON/NETCONF output: "junos:commit-localtime" : "2026-06-20 12:56:01 UTC"
        '/^\s*"junos:commit-localtime"\s*:/',
        // JSON/NETCONF output: "sha-256" : "a1e456..."  — commit checksum
        '/^\s*"sha-256"\s*:/',

        // ---------------------------------------------------------------
        // Cisco NX-OS
        // ---------------------------------------------------------------
        // "!Running configuration last done at: Mon Jun 29 12:17:08 2026"
        '/^!Running configuration last done at: /',
        // "!Time: Mon Jun 29 12:41:29 2026"
        '/^!Time: /',

        // ---------------------------------------------------------------
        // Opengear
        // ---------------------------------------------------------------
        // "# Generated: Tue Jul  1 14:23:01 2026"
        '/^# Generated: /',
        // "# Last modified: Tue Jul  1 14:23:01 2026"
        '/^# Last modified: /',

    ],

];
