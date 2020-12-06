<?php
/**
 * frequentDevicePoll script gathers cpu statistics from devices in regular intervals,
 * updates databases with new values and creates new graphs.
 */

$settings = require 'confParse.php';
$conn_string = $settings['local']['host']." ".$settings['local']['port']." ".$settings['local']['name']." user=".$settings['local']['user']." password=".$settings['local']['password']; 
$conn = pg_connect($conn_string);
$community = $settings['global']['community'];
$device_log = $settings['global']['device']['path']['log'];
$device_rrd = $settings['global']['device']['path']['rrd'];
$device_graph = $settings['global']['device']['path']['graph'];
$log_path = $settings['global']['path'];
$intervals = ['6h','24h','48h'];

function testDevice($device_ip,$log_path,$device_log,$community){
    $error = '';
    try{
        if(snmpwalk("{$device_ip}", $community, "1.3.6.1.2.1.1.5.0", 100000, 5)=== FALSE){
            throw new Exception("Could not contact device>: ");
        }
    }
    catch(Exception $exc){
        $t = time();
        $timestamp = (date("Y-m-d h:i:s",$t));
        $device = fopen($device_log.$device_ip."alerts.log",'a');
        $log = fopen($log_path."alerts.log",'a');
        $error =  'Could not contact device>'.$device_ip;
        fwrite($device,$timestamp.":Error: ".$error."\n");
        fwrite($log,$timestamp.":Error: ".$error."\n");
        fclose($device);
        fclose($log);
    }
    return $error;
}

function pollDevice($device_spec,$device_ip,$community,$device_info,$intervals,$device_rrd,$device_graph,$conn){
    foreach($device_spec as $spec){
        $value_walk =  snmpwalk("{$device_ip}", $community, $spec['oid'], 100000, 5);
        $value_segments =  explode( $spec['divider'],end($value_walk));
        if($spec['position'] === 'end'){
                $value = end($value_segments);
        }
        else{
                $value = $value_segments[$spec['position']];
        }
        $device_info[$spec['info']] = $value;
    }
    //db update
    $device_id_select = "SELECT device_id FROM t_devices WHERE device_ip = $1";
    $device_id = pg_fetch_result(pg_query_params($conn, $device_id_select,array($device_ip)),0,0);
    $update_stat = $device_info['cpu_last_min'];
    $q_update_stat = "UPDATE t_device_stat SET cpu_last_min = $1 WHERE device_id = $2";
    pg_query_params($conn,$q_update_stat,array($update_stat,$device_id));

    //rrd update
    $hostname = $device_info['hostname'];
    $rrd_name = $device_rrd.$hostname.'cpu.rrd';
    $t = time();
    $cpu = $device_info['cpu_last_min'];
    $update = rrd_update($rrd_name,array("$t:$cpu")); 
    if(!$update){
        echo rrd_error();
    }

    //create graph
    $ds = 'cpu';
    foreach ($intervals as $i){
        $options = array(
            "--slope-mode",
            "--start", "now-".$i,
            "--end", "now",
            "--title=".$ds,
            "--vertical-label=".$ds,
            "DEF:".$ds."=".$device_rrd.$hostname."cpu.rrd:".$ds.":AVERAGE",
            "AREA:".$ds."#474745:".$ds,
            "GPRINT:".$ds.":MIN: Minimum %6.2lf",
            "GPRINT:".$ds.":AVERAGE: Average %6.2lf",
            "GPRINT:".$ds.":MAX: Maximum %6.2lf",
        );
        $graph = rrd_graph($device_graph.$hostname.$ds.$i.'_graph.png', $options);
        if (!$graph) {
            echo rrd_error();
        }
    }  

}

$devices_select = "SELECT DISTINCT device_ip FROM t_devices";
$devices = pg_fetch_all(pg_query($conn, $devices_select));

$device_spec = array(
    array('info'=>'hostname','oid'=>'1.3.6.1.2.1.1.5.0','divider'=>'"','position'=>'1'),
    //array('info'=>'sys_description','oid'=>'1.3.6.1.2.1.1.1','divider'=>'"','position'=>'1'),
    //array('info'=>'uptime','oid'=>'1.3.6.1.2.1.1.3','divider'=>'.','position'=>'0'),
    //array('info'=>'device_type','oid'=>'1.3.6.1.4.1.9.9.25.1.1.1.2.3','divider'=>'$','position'=>'1'),
    //array('info'=>'image_info','oid'=>'1.3.6.1.4.1.9.9.25.1.1.1.2.2','divider'=>'$','position'=>'1'),
    //array('info'=>'image_ver','oid'=>'1.3.6.1.4.1.9.9.25.1.1.1.2.5','divider'=>'$','position'=>'1'),
    //array('info'=>'feature_set','oid'=>'1.3.6.1.4.1.9.9.25.1.1.1.2.4','divider'=>'$','position'=>'1'),
    array('info'=>'cpu_last_min','oid'=>'1.3.6.1.4.1.9.9.109.1.1.1.1.7','divider'=>':','position'=>'end')
);
$device_info =[];

foreach ($devices as $device){

    $pid = pcntl_fork();
    if ($pid === -1) {
        exit("Error forking...\n");
    }
    else if ($pid === 0) {
        $device_ip = $device['device_ip']; 
        $conn = pg_connect($conn_string,PGSQL_CONNECT_FORCE_NEW);
        $error = testDevice($device_ip,$log_path,$device_log,$community);
        if(strlen($error) !== 0){
            continue;
        }
        pollDevice($device_spec,$device_ip,$community,$device_info,$intervals,$device_rrd,$device_graph,$conn);
        exit();
    }
}
while(pcntl_waitpid(0, $status) !== -1);
pg_close($conn);



