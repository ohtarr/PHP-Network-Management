from netmiko import SSHDetect, ConnectHandler
import re
#import logging
import argparse

DELIMITER = "---NETMIKO_OUTPUT_DELIMITER---"

parser = argparse.ArgumentParser(description="Script to attempt to run a CLI command on a device.")
parser.add_argument("--host", help="Hostname or IP of device")
parser.add_argument("--username", help="Username to connect to the device with")
parser.add_argument("--password", help="Password to connect to the device with")
parser.add_argument("--type", help="Netmiko device type")
parser.add_argument("--cmd", action="append", help="Command to attempt to run (can be specified multiple times)")
parser.add_argument("--timeout", type=int, default=20, help="Amount of time (in secs) to wait for prompt to come back")
args = parser.parse_args()

device = {
        "device_type":args.type,
        "host":args.host,
        "username":args.username,
        "password":args.password,
        "secret":args.password,
#        "session_log":"../storage/logs/netmiko_autodetect.log",
}

with ConnectHandler(**device) as net_connect:
	outputs = []
	for cmd in args.cmd:
		output = net_connect.send_command(cmd, read_timeout=args.timeout)
		outputs.append(output)
	net_connect.disconnect()
	print(DELIMITER.join(outputs))
