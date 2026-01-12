from netmiko import SSHDetect, NetMikoAuthenticationException
import logging
import argparse

parser = argparse.ArgumentParser(description="Script to test credentials.")
parser.add_argument("--host", help="Hostname or IP of device")
parser.add_argument("--username", help="Username to connect to the device with")
parser.add_argument("--password", help="Password to connect to the device with")
args = parser.parse_args()

device = {
        "device_type":"autodetect",
        "host":args.host,
        "username":args.username,
        "password":args.password,
        "secret":args.password,
}
try:
        guesser = SSHDetect(**device)
        print(1)
        guesser.connection.disconnect()
except (NetMikoAuthenticationException):
        print(0)
