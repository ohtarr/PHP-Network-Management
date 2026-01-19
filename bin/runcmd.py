from netmiko import SSHDetect, ConnectHandler
import re
#import logging
import argparse

parser = argparse.ArgumentParser(description="Script to attempt to run a CLI command on a device.")
parser.add_argument("--host", help="Hostname or IP of device")
parser.add_argument("--username", help="Username to connect to the device with")
parser.add_argument("--password", help="Password to connect to the device with")
parser.add_argument("--type", help="Netmiko device type")
parser.add_argument("--cmd", help="Command to attempt to run")
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
#net_connect = ConnectHandler(**device)
	output = net_connect.send_command(args.cmd, read_timeout=args.timeout)
	net_connect.disconnect()
	print(output)



#lines = output.splitlines()

'''
regs = [
        "^!Running configuration last done at:",
        "^!Time:",
	"^## Last commit:",
]
'''
'''
with open("test.txt", "w") as file:
    for line in lines:
        match = False
        for reg in regs:
            pattern = re.compile(reg)
            if pattern.search(line):
                match = True
                break;
        if match == False:
            file.write(line)
            file.write("\n")
'''

#with open(f'outputs/{sys.argv[2]}.txt', "w") as f:
#       print(output, file=f)
