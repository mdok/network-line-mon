<?php
/**
 * confParse parses configuration files for cli scripts
 */
namespace App\bin;

use App;
use Dibi;
use Nette\Neon\Neon;

require '/var/www/network-line-mon/vendor/autoload.php';

$container = App\Bootstrap::bootForCli()
        ->createContainer();

$global = file_get_contents("/var/www/network-line-mon/app/config/common.neon");
$contents_global = Neon::decode($global);
$local = file_get_contents("/var/www/network-line-mon/app/config/local.neon");
$contents_local = Neon::decode($local);

$database = $contents_local['database'];
$dsn = $contents_local['database']['dsn'];
$db =  explode(":",$dsn,);
$db_parts = explode(";",$db[1]);
$config['local']['host'] = $db_parts[0];
$config['local']['port'] = $db_parts[1];
$config['local']['name'] = $db_parts[2];
$config['local']['user'] = $database['user'];
$config['local']['password'] = $database['password'];
$config['global'] = $contents_global['parameters']['config'];

return $config;

