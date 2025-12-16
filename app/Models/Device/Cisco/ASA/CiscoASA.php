<?php

namespace App\Models\Device\Cisco\ASA;

use App\Models\Device\Cisco\Cisco;
use phpseclib3\Net\SSH2;

class CiscoASA extends Cisco
{
    protected static $singleTableSubclasses = [
    ];

    protected static $singleTableType = __CLASS__;

    public $promptreg = '/.*\S*[#|>].*/';

    public $precli = [
        'terminal pager 0',
    ];

    public $discover_commands = [
    ];

    public $discover_regex = [
    ];

    //List of commands to run during a scan of this device.
    public $scan_cmds = [
        'run'           => 'sh run',
        'version'       => 'sh version',
    ];

	public function exec_cmds($cmds, $timeout = null)
    {
        return $this->exec_cmds_netmiko($cmds);
    }

/* 	public function getSSH2($ip, $username, $password, $timeout = 4)
	{
		$cli = new SSH2($ip);
		
        if (!$cli->login($username, $password)) {
			throw new \Exception('Login Failed');
        }
        $cli->setTimeout($timeout);
        $OUTPUT = $cli->read($this->promptreg , SSH2::READ_REGEX);
        $cli->write("\n");
        $cli->write("enable\n");
        //sleep(1);
        $cli->write($password . "\n");
        sleep(2);
        $cli->read($this->promptreg , SSH2::READ_REGEX);
        $cli->write("\n");
        sleep(1);
        //$cli->read($this->promptreg , SSH2::READ_REGEX);
        $prompt = trim($cli->read($this->promptreg , SSH2::READ_REGEX));
        $cli->prompt = $prompt;
        print "prompt: " . '"' . $prompt . '"' . PHP_EOL;
        print "cli prompt: " . '"' . $cli->prompt . '"' . PHP_EOL;
        print PHP_EOL;
        print "OUTPUT2".PHP_EOL;
        print_r($OUTPUT);
        foreach($this->precli as $precli)
        {
            print $precli . PHP_EOL;
            $cli->write($precli . "\n");
            $OUTPUT = $cli->read($prompt);
            print "OUTPUT3".PHP_EOL;
            print_r($OUTPUT);
        }
        //$OUTPUT = $cli->read($prompt);
        //print_r($OUTPUT);
        //print "CHECK3".PHP_EOL;
        return $cli;
	}

	public function exec_cmds_1($cmds, $timeout = null)
	{
        if(!$timeout)
        {
            $timeout = $this->cli_timeout;
        }
		$cli = $this->getCli($timeout);
        if(!$cli)
        {
			throw new \Exception('Unable to establish CLI!');
        }
		if(is_array($cmds))
		{
			foreach($cmds as $key => $cmd)
			{
				$cli->write($cmd . "\n");
				$output[$key] = $cli->read($cli->prompt);
			}
		} elseif (is_string($cmds)) {
			$LINES = explode("\n", $cmds);
			$output = "";
			foreach($LINES as $LINE)
			{
				if(!$LINE)
				{
					continue;
				}
				$cli->write($LINE . "\n");
				$output .= $cli->read($cli->prompt);
			}
		}
		$cli->disconnect();
		return $output;
	} */

}
