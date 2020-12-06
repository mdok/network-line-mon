<?php
/**
 * frequentSlaPoll script gathers IP SLA statistics from devices in regular intervals,
 * updates databases with new values, creates new graphs and checks thresholds.
 */

$settings = require 'confParse.php';
$conn_string = $settings['local']['host']." ".$settings['local']['port']." ".$settings['local']['name']." user=".$settings['local']['user']." password=".$settings['local']['password'];
$conn = pg_connect($conn_string);
$community = $settings['global']['community'];
$line_log = $settings['global']['line']['path']['log'];
$line_rrd = $settings['global']['line']['path']['rrd'];
$line_graph = $settings['global']['line']['path']['graph'];
$device_log = $settings['global']['device']['path']['log'];
$log_path = $settings['global']['path'];
$intervals = ['6h','24h','48h'];
$ds_array = ['rtt_min','rtt_avg','rtt_max','rtt_sum','latency_ds_min','latency_ds_avg','latency_ds_max','latency_ds_sum','latency_sd_min','latency_sd_avg','latency_sd_max','latency_sd_sum','latency_numof_sam','packet_loss','packet_late','packet_outseq_bidi','packet_outof_seq_sd','packet_outof_seq_ds','packet_skipped','jitter_pos_sd_min','jitter_pos_sd_max','jitter_pos_sd_sum','jitter_pos_sd_sam','jitter_neg_sd_min','jitter_neg_sd_max','jitter_neg_sd_sum','jitter_neg_sd_sam','jitter_pos_ds_min','jitter_pos_ds_max','jitter_pos_ds_sum','jitter_pos_ds_sam','jitter_neg_ds_min','jitter_neg_ds_max','jitter_neg_ds_sum','jitter_neg_ds_sam','jitter_avg_sd','jitter_avg_ds','jitter_avg_bidir','jitter_intarr_resp','jitter_intarr_sour'];

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

function pollSlas($device_source,$device_ip,$conn, $ds_array,$intervals,$community,$line_log,$line_rrd,$line_graph,$log_path ){
    $oper_types_select = "SELECT DISTINCT sla_oper_type FROM t_lines WHERE device_source = $1";
    $oper_types_array = pg_fetch_all(pg_query_params($conn, $oper_types_select,array($device_source)));
    $oper_types = [];

    $oper_numbers_select = "SELECT sla_oper_number FROM t_lines WHERE device_source = $1";
    $oper_numbers_array = pg_fetch_all(pg_query_params($conn, $oper_numbers_select,array($device_source)));
    $oper_numbers = [];

    foreach($oper_types_array as $oper_type){
        $oper = $oper_type['sla_oper_type'];
        array_push($oper_types,$oper);
    }
    foreach($oper_numbers_array as $oper_number){
        $oper = $oper_number['sla_oper_number'];
        array_push($oper_numbers,$oper);
    }
   
    //if jitter operation exists for device poll whole subtree for jitter for all oper numbers at once
    if (in_array('2',$oper_types)){
        echo "WALKING JITTER \n";
        $a = snmpwalkoid("{$device_ip}", $community, '1.3.6.1.4.1.9.9.42.1.5.4.1', 100000, 5);
    }
    // poll subtree of rtt_avg from separate table for all oper numbers at once
    $walk_rtt_avg = snmpwalkoid("{$device_ip}", $community, '1.3.6.1.4.1.9.9.42.1.2.10.1.1', 100000, 5);
       
    //for each operation number on device
    foreach ($oper_numbers as $oper_number){
        $slas = [];
        $slas['30'] = 0; 
        //go through subtree, find current oper number and assign sla value
        for (reset($walk_rtt_avg); $k = key($walk_rtt_avg); next($walk_rtt_avg)) {
            $segments_k = explode ('.', $k);
            $oper = end($segments_k);
            if ($oper == $oper_number){
                $segments_sla = explode (':', $walk_rtt_avg[$k]);
                $sla = end($segments_sla);
                $slas[51] = $sla;
            }
        }
        // if jitter exists on device go through subtree, find current oper nuber, get all sla values for oper number
        if(isset($a)===true){
            for (reset($a); $i = key($a); next($a)) {
                $segments_i = explode ('.', $i);
                $oper = end($segments_i);
                end($segments_i);
                $sla_id = prev($segments_i);
                if ($oper == $oper_number){
                    $segments_sla = explode (':', $a[$i]);
                    $sla = end($segments_sla);
                    $slas[$sla_id] = $sla;
                }
            }   
        }
        $line_id_select = "SELECT line_id FROM t_lines WHERE device_source = $1 and sla_oper_number = $2";
        $line_id = pg_fetch_result(pg_query_params($conn, $line_id_select, array($device_source,$oper_number)),0,0);

        $type_select = "SELECT sla_oper_type FROM t_lines WHERE device_source = $1 and sla_oper_number = $2";
        $type = pg_fetch_result(pg_query_params($conn, $type_select,array($device_source,$oper_number)),0,0);
    
        //update sla table according to oper type 
        if ($type == 2){
            $update_sla = " (
                '{$slas['4']}',
                '{$slas['51']}',
                '{$slas['5']}',
                '{$slas['1']}',
                '{$slas['41']}',
                '{$slas['48']}',
                '{$slas['42']}',
                '{$slas['39']}',
                '{$slas['37']}',
                '{$slas['47']}',
                '{$slas['38']}',
                '{$slas['35']}',
                '{$slas['43']}',
                '{$slas['26']}',
                '{$slas['32']}',
                '{$slas['27']}',
                '{$slas['28']}',
                '{$slas['29']}',
                '{$slas['30']}',
                '{$slas['6']}',
                '{$slas['7']}',
                '{$slas['8']}',
                '{$slas['9']}',
                '{$slas['11']}',
                '{$slas['12']}',
                '{$slas['13']}',
                '{$slas['14']}',
                '{$slas['16']}',
                '{$slas['17']}',
                '{$slas['18']}',
                '{$slas['19']}',
                '{$slas['21']}',
                '{$slas['22']}',
                '{$slas['23']}',
                '{$slas['24']}',
                '{$slas['45']}',
                '{$slas['46']}',
                '{$slas['44']}',
                '{$slas['49']}',
                '{$slas['50']}'
        
            ) ";
            $q_update_sla = "UPDATE t_sla SET (
                rtt_min,
                rtt_avg, 
                rtt_max, 
                rtt_sum, 
                latency_ds_min,
                latency_ds_avg,
                latency_ds_max,
                latency_ds_sum,
                latency_sd_min,
                latency_sd_avg,
                latency_sd_max,
                latency_sd_sum,
                latency_numof_sam,
                packet_loss,
                packet_late,
                packet_outseq_bidi,
                packet_outof_seq_sd,
                packet_outof_seq_ds,
                packet_skipped,
                jitter_pos_sd_min,
                jitter_pos_sd_max,
                jitter_pos_sd_sum,
                jitter_pos_sd_sam,
                jitter_neg_sd_min,
                jitter_neg_sd_max,
                jitter_neg_sd_sum,
                jitter_neg_sd_sam,
                jitter_pos_ds_min,
                jitter_pos_ds_max,
                jitter_pos_ds_sum,
                jitter_pos_ds_sam,
                jitter_neg_ds_min,
                jitter_neg_ds_max,
                jitter_neg_ds_sum,
                jitter_neg_ds_sam,
                jitter_avg_sd,
                jitter_avg_ds,
                jitter_avg_bidir,
                jitter_intarr_resp,
                jitter_intarr_sour
            ) = $update_sla WHERE line_id = '$line_id'";
            pg_query($conn,$q_update_sla);
        }
        if ($type == 1){
            $update_sla = "{$slas['51']}";
            $q_update_sla = "UPDATE t_sla SET rtt_avg = $1 WHERE line_id = $2";
            pg_query_params($conn,$q_update_sla,array($update_sla,$line_id));
        }
        //update rrd for line
        updateRRD($line_rrd,$line_id,$slas,$type);
        //create graphs for line
        createGraphs($line_id,$ds_array, $intervals, $type,$line_graph,$line_rrd);
        //check line thresholds
        checkThreshold($line_id,$conn,$line_log,$log_path);    

    }
    echo "Completed task ip :".$device_ip."\n";
}

function updateRRD($line_rrd,$line_id,$slas,$type){
    $rrd_name = $line_rrd.$line_id.'sla.rrd';
    $t = time();
    
    if ($type == 2){
        $rrd_update = rrd_update($rrd_name,array("".$t.":
        ".$slas['4'].":
        ".$slas['51'].":
        ".$slas['5'].":
        ".$slas['1'].":
        ".$slas['41'].":
        ".$slas['48'].":
        ".$slas['42'].":
        ".$slas['39'].":
        ".$slas['37'].":
        ".$slas['47'].":
        ".$slas['38'].":
        ".$slas['35'].":
        ".$slas['43'].":
        ".$slas['26'].":
        ".$slas['32'].":
        ".$slas['27'].":
        ".$slas['28'].":
        ".$slas['29'].":
        ".$slas['30'].":
        ".$slas['6'].":
        ".$slas['7'].":
        ".$slas['8'].":
        ".$slas['9'].":
        ".$slas['11'].":
        ".$slas['12'].":
        ".$slas['13'].":
        ".$slas['14'].":
        ".$slas['16'].":
        ".$slas['17'].":
        ".$slas['18'].":
        ".$slas['19'].":
        ".$slas['21'].":
        ".$slas['22'].":
        ".$slas['23'].":
        ".$slas['24'].":
        ".$slas['45'].":
        ".$slas['46'].":
        ".$slas['44'].":
        ".$slas['49'].":
        ".$slas['50'].
        "")); 

        if ($rrd_update === FALSE) {
            echo "Update error: ".rrd_error()."\n";
        }
    }
    else{
        $device_sla = $slas['51'];
        $t = time();
        $rrd_update = rrd_update($rrd_name,array("$t:$device_sla"));  
        if ($rrd_update === FALSE) {
            echo "Update error: ".rrd_error()."\n";
        }
    }


}
function createGraphs($line_id,$ds_array, $intervals, $type,$line_graph,$line_rrd){
    
    foreach ($ds_array as $ds){
        if ($type == 1){
            $ds = 'rtt_avg';
    
        }
        foreach ($intervals as $i){
            $options = array(
                 "--slope-mode",
                 "--start", "now-".$i,
                 "--end", "now",
                "--title=".$ds,
                "--vertical-label=".$ds,
                "DEF:".$ds."=".$line_rrd.$line_id."sla.rrd:".$ds.":AVERAGE",
                "AREA:".$ds."#474745:".$ds,
                "GPRINT:".$ds.":MIN: Minimum %6.2lf",
                "GPRINT:".$ds.":AVERAGE: Average %6.2lf",
                "GPRINT:".$ds.":MAX: Maximum %6.2lf",
            );
            $graph = rrd_graph($line_graph.$line_id.$ds.$i.'_graph.png', $options);
            if (!$graph) {
                echo rrd_error();
            }
        }  

    }
    

}


function checkThreshold($line_id,$conn,$line_log_path,$log_path){
    if(file_exists($log_path.'alerts.log')===true){
        $log = fopen($log_path.'alerts.log','a');
    }
    else{
        $log = fopen($log_path.'alerts.log','w');
    }
    if(file_exists($line_log_path.$line_id.'alerts.log')===true){
        $line_log = fopen($line_log_path.$line_id.'alerts.log','a');
    }
    else{
        $line_log = fopen($line_log_path.$line_id.'alerts.log','w');
    }
    $slas_select = "SELECT * FROM t_sla WHERE line_id = $1";
    $thresholds_select = "SELECT * FROM t_threshold WHERE line_id = $1";
    $thresholds = pg_fetch_all(pg_query_params($conn, $thresholds_select,array($line_id)));
    $slas = pg_fetch_array(pg_query_params($conn, $slas_select,array($line_id)));
    
    if ($thresholds){
        
        foreach($thresholds as $threshold){
            $line_id = $threshold['line_id'];
            $sla_type = $threshold['sla_type'];
            $sla = $slas[$sla_type];
            $sla_min = $threshold['min'];
            $sla_max = $threshold['max'];
            $sla_exact = $threshold['exact'];
            $min = 'f';
            $max = 'f';
            $exact = 'f';
            $threshold_type = '';
                if($sla < $sla_min and $sla_min != null ){
                    $threshold_type = 'below min('.$sla_min.')';
                    $min = 't';
                }
                if($sla > $sla_max and $sla_max != null){
                    $threshold_type = 'above max('.$sla_max.')';
                    $max = 't';
                }
                if($sla == $sla_exact and $sla_exact != null){
                    $threshold_type = 'is exact('.$sla_exact.')';
                    $exact = 't';
                }
                if($threshold_type !== ''){ 
                    $t = time();
                    $timestamp = (date("Y-m-d h:i:s",$t));
                    fwrite($log,$timestamp.": sla: ".$sla_type." for line: ".$line_id." is ".$threshold_type." with value: ".$sla."\n");
                    fwrite($line_log,$timestamp.": sla ".$sla_type." is ".$threshold_type." with value: ".$sla."\n");
                    $update_threshold = 't';
                    $q_update = "UPDATE t_threshold SET over_threshold = $1, over_min = $2, over_max = $3, over_exact = $4 WHERE line_id = $5 AND sla_type = $6";
                    pg_query_params($conn,$q_update,array($update_threshold,$min,$max,$exact,$line_id,$sla_type));
                }
           
            else{
                $update_threshold = 'f';
                $q_update = "UPDATE t_threshold SET over_threshold = $1 WHERE line_id = $2 AND sla_type = $3";
                pg_query_params($conn,$q_update,array($update_threshold,$line_id,$sla_type));
            }

        }
    }
    fclose($line_log);
    fclose($log);


}

$source_devices_select = "SELECT DISTINCT device_source, device_ip FROM t_lines INNER JOIN t_devices ON device_source=device_id WHERE poll='1'";
$source_devices = pg_fetch_all(pg_query($conn, $source_devices_select));

foreach ($source_devices as $source_device){

    $pid = pcntl_fork();
    if ($pid === -1) {
        exit("Error forking...\n");
    }
    else if ($pid === 0) {
        $conn = pg_connect($conn_string,PGSQL_CONNECT_FORCE_NEW);
        echo "source>";
        print_r($source_device);
        $device_source = $source_device['device_source'];
        $device_ip = $source_device['device_ip'];
        echo "IP> ".$device_ip."\n";
        $error = testDevice($device_ip,$log_path,$device_log,$community);
        if(strlen($error) !== 0){
            continue;
        }
        echo "CALL TO POLL for ".$device_ip."\n";
        pollSlas($device_source,$device_ip,$conn, $ds_array,$intervals,$community,$line_log,$line_rrd,$line_graph,$log_path);
        exit();
    }
}
while(pcntl_waitpid(0, $status) !== -1);
pg_close($conn);
