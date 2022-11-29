#!/bin/bash
dir=$(dirname "$0")
parentdir="$(dirname "$dir")"
/usr/sbin/tcpdump -l -i any -n udp port 162 or udp port 514 | /usr/bin/php $parentdir/artisan netman:processTcpdump
