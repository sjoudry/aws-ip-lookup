#!/usr/bin/php
<?php
/*
 * AWS IP Lookup
 *
 * This file does the IP lookup based on the args passed in
 * through the lookup.sh wrapper script. This file should
 * not be called directly (but it could be).
 *
 * Author: Scott Joudry <sj@slydevil.com>
 * Created: May 22, 2019
 **********************************************************/

include_once __DIR__ . '/AwsIp.php';

$aws = ((isset($argv[1])) ? $argv[1] : null);
$domain = ((isset($argv[2])) ? $argv[2] : null);
$subdomain = ((isset($argv[3])) ? $argv[3] : null);

$lookup = new AwsIp\Lookup($aws, $domain, $subdomain);
$lookup->run();
