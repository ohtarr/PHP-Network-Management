from netmiko import SSHDetect
import logging
import argparse

parser = argparse.ArgumentParser(description="Script to attempt to detect Netmiko type from device CLI.")
parser.add_argument("--host", help="Hostname or IP of device")
parser.add_argument("--username", help="Username to connect to the device with")
parser.add_argument("--password", help="Password to connect to the device with")
args = parser.parse_args()

#print(f"Arguments received: host={args.host}, username={args.username}, password={args.password}")

device = {
        "device_type":"autodetect",
        "host":args.host,
        "username":args.username,
        "password":args.password,
        "secret":args.password,
#        "session_log":"../storage/logs/netmiko_autodetect.log",
}
#with SSHDetect(**device) as guesser:
guesser = SSHDetect(**device)
best_match = guesser.autodetect()
print(best_match)
