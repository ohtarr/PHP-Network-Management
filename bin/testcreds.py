from netmiko import ConnectHandler, NetMikoAuthenticationException
import logging
import argparse

parser = argparse.ArgumentParser(description="Script to attempt to detect Netmiko type from device CLI.")
parser.add_argument("--host", help="Hostname or IP of device")
parser.add_argument("--username", help="Username to connect to the device with")
parser.add_argument("--password", help="Password to connect to the device with")
args = parser.parse_args()

device = {
        "device_type":"cisco_ios",
        "host":args.host,
        "username":args.username,
        "password":args.password,
        "secret":args.password,
#        "session_log":"../storage/logs/netmiko_autodetect.log",
}

try:
        with ConnectHandler(**device) as net_connect:
                net_connect.disconnect()
                print(1)
except (NetMikoAuthenticationException):
        print(0)
