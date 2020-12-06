<?php
declare(strict_types=1);

namespace App\Model;

use Nette;

/**
 * Class PollManager
 * validates poll interval, makes initial contact with devices and tests 
 * their availability, does intial retrieval of line IP SLA statistics and 
 * device cpu statistics, prepares rrd database files for lines and devices statistics, 
 * performs initial insert to those database files and creates first graphs for statistcs.
 */
class PollManager
{
	use Nette\SmartObject;

	/** @var Nette\Database\Context */
	private $database;
    private $poll;
    private $path_line;
    private $path_device;
    private $path;
    private $community;
    
	public function __construct($poll,$path_line,$path_device,$path,$community, Nette\Database\Context $database)
	{
        $this->database = $database;
        $this->poll = $poll;
        $this->path_line = $path_line;
        $this->path_device = $path_device;
        $this->path = $path;
        $this->community = $community;
    }

    public function initialSLAPoll($device,$line_id,$sla_oper_number,$sla_oper_type)
	{
        $ds_array = ['rtt_min','rtt_avg','rtt_max','rtt_sum','latency_ds_min','latency_ds_avg','latency_ds_max','latency_ds_sum','latency_sd_min','latency_sd_avg','latency_sd_max','latency_sd_sum','latency_numof_sam','packet_loss','packet_late','packet_outseq_bidi','packet_outof_seq_sd','packet_outof_seq_ds','packet_skipped','jitter_pos_sd_min','jitter_pos_sd_max','jitter_pos_sd_sum','jitter_pos_sd_sam','jitter_neg_sd_min','jitter_neg_sd_max','jitter_neg_sd_sum','jitter_neg_sd_sam','jitter_pos_ds_min','jitter_pos_ds_max','jitter_pos_ds_sum','jitter_pos_ds_sam','jitter_neg_ds_min','jitter_neg_ds_max','jitter_neg_ds_sum','jitter_neg_ds_sam','jitter_avg_sd','jitter_avg_ds','jitter_avg_bidir','jitter_intarr_resp','jitter_intarr_sour'];
        $intervals = ['6h','24h','48h'];
        $slas = [];
        switch($sla_oper_type){
            case 1:
                $device_snmp = snmpwalk("{$device}",$this->community, '1.3.6.1.4.1.9.9.42.1.2.10.1.1.'.$sla_oper_number, 100000, 5);
                $segments_device_snmp = explode (':', end($device_snmp));
                $device_sla = end($segments_device_snmp);
                $slas = array_fill (1,50,0);
                $slas['51'] = $device_sla;
                $slas['30'] = 0;       
        
                $rrd_name = $this->path_line['rrd'].$line_id.'sla.rrd';
                if(file_exists($rrd_name)===false){
                    $opts = array( "--step", $this->poll."s", 
                        "DS:rtt_avg:GAUGE:".($this->poll*3)."s:0:U",
                        "RRA:AVERAGE:0.5:".$this->poll."s:6h",
                        "RRA:AVERAGE:0.5:1h:2d",
                    );
                    $rrd = rrd_create($rrd_name,$opts);
                }
                rrd_update($rrd_name,array("N:".$slas['51']."")); 

                $ds = 'rtt_avg';
                foreach ($intervals as $i){
                    $options = array(
                        "--slope-mode",
                        "--start", "now-".$i,
                        "--end", "now",
                        "--title=".$ds,
                        "--vertical-label=".$ds,
                        "DEF:".$ds."=".$this->path_line['rrd'].$line_id."sla.rrd:".$ds.":AVERAGE",
                        "AREA:".$ds."#474745:".$ds,
                        "GPRINT:".$ds.":MIN: Minimum %6.2lf",
                        "GPRINT:".$ds.":AVERAGE: Average %6.2lf",
                        "GPRINT:".$ds.":MAX: Maximum %6.2lf",
                    );
                    $graph = rrd_graph($this->path_line['graph'].$line_id.$ds.$i.'_graph.png', $options);
                }   
                
                return $slas;
            
            case 2:
                
                $walk_rtt_avg = snmpwalk("{$device}",$this->community, '1.3.6.1.4.1.9.9.42.1.2.10.1.1.'.$sla_oper_number, 100000, 5);
                $segments_walk_rtt_avg = explode (':', end($walk_rtt_avg));
                $rtt_avg = end($segments_walk_rtt_avg);
                $slas['51'] = $rtt_avg;
                $slas['30'] = 0;


                $a = snmpwalkoid("{$device}",$this->community, '1.3.6.1.4.1.9.9.42.1.5.4.1', 100000, 5);
                for (reset($a); $i = key($a); next($a)) {
                    $segments_i = explode ('.', $i);
                    $oper = end($segments_i);
                    end($segments_i);
                    $sla_id = prev($segments_i);
                    if ($oper == $sla_oper_number){
                        $segments_sla = explode (':', $a[$i]);
                        $sla = end($segments_sla);
                        $slas[$sla_id] = $sla;
        
                    }

                }   
                $rrd_name = $this->path_line['rrd'].$line_id.'sla.rrd';
                
                if(file_exists($rrd_name)===false){
                    $opts = array( "--step", $this->poll."s", 
                        "DS:rtt_min:GAUGE:".($this->poll*3)."s:0:U",
                        "DS:rtt_avg:GAUGE:".($this->poll*3)."s:0:U",
                        "DS:rtt_max:GAUGE:".($this->poll*3)."s:0:U",
                        "DS:rtt_sum:GAUGE:".($this->poll*3)."s:0:U",
                        "DS:latency_ds_min:GAUGE:".($this->poll*3)."s:0:U",
                        "DS:latency_ds_avg:GAUGE:".($this->poll*3)."s:0:U",
                        "DS:latency_ds_max:GAUGE:".($this->poll*3)."s:0:U",
                        "DS:latency_ds_sum:GAUGE:".($this->poll*3)."s:0:U",
                        "DS:latency_sd_min:GAUGE:".($this->poll*3)."s:0:U",
                        "DS:latency_sd_avg:GAUGE:".($this->poll*3)."s:0:U",
                        "DS:latency_sd_max:GAUGE:".($this->poll*3)."s:0:U",
                        "DS:latency_sd_sum:GAUGE:".($this->poll*3)."s:0:U",
                        "DS:latency_numof_sam:GAUGE:".($this->poll*3)."s:0:U",
                        "DS:packet_loss:GAUGE:".($this->poll*3)."s:0:U",
                        "DS:packet_late:GAUGE:".($this->poll*3)."s:0:U",
                        "DS:packet_outseq_bidi:GAUGE:".($this->poll*3)."s:0:U",
                        "DS:packet_outof_seq_sd:GAUGE:".($this->poll*3)."s:0:U",
                        "DS:packet_outof_seq_ds:GAUGE:".($this->poll*3)."s:0:U",
                        "DS:packet_skipped:GAUGE:".($this->poll*3)."s:0:U",
                        "DS:jitter_pos_sd_min:GAUGE:".($this->poll*3)."s:0:U",
                        "DS:jitter_pos_sd_max:GAUGE:".($this->poll*3)."s:0:U",
                        "DS:jitter_pos_sd_sum:GAUGE:".($this->poll*3)."s:0:U",
                        "DS:jitter_pos_sd_sam:GAUGE:".($this->poll*3)."s:0:U",
                        "DS:jitter_neg_sd_min:GAUGE:".($this->poll*3)."s:0:U",
                        "DS:jitter_neg_sd_max:GAUGE:".($this->poll*3)."s:0:U",
                        "DS:jitter_neg_sd_sum:GAUGE:".($this->poll*3)."s:0:U",
                        "DS:jitter_neg_sd_sam:GAUGE:".($this->poll*3)."s:0:U",
                        "DS:jitter_pos_ds_min:GAUGE:".($this->poll*3)."s:0:U",
                        "DS:jitter_pos_ds_max:GAUGE:".($this->poll*3)."s:0:U",
                        "DS:jitter_pos_ds_sum:GAUGE:".($this->poll*3)."s:0:U",
                        "DS:jitter_pos_ds_sam:GAUGE:".($this->poll*3)."s:0:U",
                        "DS:jitter_neg_ds_min:GAUGE:".($this->poll*3)."s:0:U",
                        "DS:jitter_neg_ds_max:GAUGE:".($this->poll*3)."s:0:U",
                        "DS:jitter_neg_ds_sum:GAUGE:".($this->poll*3)."s:0:U",
                        "DS:jitter_neg_ds_sam:GAUGE:".($this->poll*3)."s:0:U",
                        "DS:jitter_avg_sd:GAUGE:".($this->poll*3)."s:0:U",
                        "DS:jitter_avg_ds:GAUGE:".($this->poll*3)."s:0:U",
                        "DS:jitter_avg_bidir:GAUGE:".($this->poll*3)."s:0:U",
                        "DS:jitter_intarr_resp:GAUGE:".($this->poll*3)."s:0:U",
                        "DS:jitter_intarr_sour:GAUGE:".($this->poll*3)."s:0:U",
                        "RRA:AVERAGE:0.5:".$this->poll."s:6h",
                        "RRA:AVERAGE:0.5:1h:2d",
                    );
                    $rrd = rrd_create($rrd_name,$opts);
                }
                $rrd_update = rrd_update($rrd_name,array("N:
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

                foreach ($ds_array as $ds){
                    foreach ($intervals as $i){
                        $options = array(
                            "--slope-mode",
                            "--start", "now-".$i,
                            "--end", "now",
                            "--title=".$ds,
                            "--vertical-label=".$ds,
                            "DEF:".$ds."=".$this->path_line['rrd'].$line_id."sla.rrd:".$ds.":AVERAGE",
                            "AREA:".$ds."#474745:".$ds,
                            "GPRINT:".$ds.":MIN: Minimum %6.2lf",
                            "GPRINT:".$ds.":AVERAGE: Average %6.2lf",
                            "GPRINT:".$ds.":MAX: Maximum %6.2lf",
                        );
                        $graph = rrd_graph($this->path_line['graph'].$line_id.$ds.$i.'_graph.png', $options);
                    }   
                }
     
                return $slas;
        }

    }
    public function pollTest(){
        $error = '';
        try{
            $errorHandler = set_error_handler(function($errno, $errstr, $errfile, $errline) {
            });
            if($this->poll < 60){
                throw new \Exception("Invalid poll time was configured. Minimum allowed poll time is 60s.");
            }
            restore_error_handler();
        }
        catch(\Exception $exc){
            if(file_exists($this->path."alerts.log")===true){
                $log = fopen($this->path."alerts.log",'a');
            }
            else{
                $log = fopen($this->path."alerts.log",'w');
            }
            $t = time();
            $timestamp = (date("Y-m-d h:i:s",$t));
            $error = $exc->getMessage();
            fwrite($log,$timestamp.":Error: ".$exc->getMessage()."\n");
            fclose($log);
        }
        return $error;

    }
    public function testOID($device,$oper_num){
        $error = '';
        try{
            $errorHandler = set_error_handler(function($errno, $errstr, $errfile, $errline) {
            });
            if(($value_walk = snmpwalk("{$device}",$this->community, '1.3.6.1.4.1.9.9.42.1.2.10.1.1.'.$oper_num, 100000, 5))=== FALSE){
                throw new \Exception("IP SLA operation of number: ".$oper_num." does not exist on device: ".$device);
            }
            restore_error_handler();

        }
        catch(\Exception $exc){
            if(file_exists($this->path."alerts.log")===true){
                $log = fopen($this->path."alerts.log",'a');
            }
            else{
                $log = fopen($this->path."alerts.log",'w');
            }
            $t = time();
            $timestamp = (date("Y-m-d h:i:s",$t));
            $error = $exc->getMessage();
            fwrite($log,$timestamp.":Error: ".$exc->getMessage()."\n");
            fclose($log);
        }
        return $error;

    }
    public function testDevice($device){
        $error = '';
        try{
            $errorHandler = set_error_handler(function($errno, $errstr, $errfile, $errline) {
            });
            if(($value_walk =  snmpwalk("{$device}",$this->community, "1.3.6.1.2.1.1.5.0", 100000, 5))=== FALSE){
                throw new \Exception("Could not contact device>: ");
            }
            restore_error_handler();

        }
        catch(\Exception $exc){
            $t = time();
            $timestamp = (date("Y-m-d h:i:s",$t));
            if(file_exists($this->path_device['log'].$device)===true){
                $device_log = fopen($this->path_device['log'].$device."alerts.log",'a');
            }
            else{
                $device_log = fopen($this->path_device['log'].$device."alerts.log",'w');
            }
            if(file_exists($this->path."alerts.log")===true){
                $log = fopen($this->path."alerts.log",'a');
            }
            else{
                $log = fopen($this->path."alerts.log",'w');
            }
            $error =  'Could not contact device>'.$device;
            fwrite($device_log,$timestamp.":Error: ".$error."\n");
            fwrite($log,$timestamp.":Error: ".$error."\n");
            fclose($device_log);
            fclose($log);
        }
        return $error;

    }
    public function initialDevicePoll($device){
        $intervals = ['6h','24h','48h'];
        $device_spec = array(
            array('info'=>'hostname','oid'=>'1.3.6.1.2.1.1.5.0','divider'=>'"','position'=>'1'),
            array('info'=>'sys_description','oid'=>'1.3.6.1.2.1.1.1','divider'=>'"','position'=>'1'),
            array('info'=>'uptime','oid'=>'1.3.6.1.2.1.1.3','divider'=>'.','position'=>'0'),
            array('info'=>'device_type','oid'=>'1.3.6.1.4.1.9.9.25.1.1.1.2.3','divider'=>'$','position'=>'1'),
            array('info'=>'image_info','oid'=>'1.3.6.1.4.1.9.9.25.1.1.1.2.2','divider'=>'$','position'=>'1'),
            array('info'=>'image_ver','oid'=>'1.3.6.1.4.1.9.9.25.1.1.1.2.5','divider'=>'$','position'=>'1'),
            array('info'=>'feature_set','oid'=>'1.3.6.1.4.1.9.9.25.1.1.1.2.4','divider'=>'$','position'=>'1'),
            array('info'=>'cpu_last_min','oid'=>'1.3.6.1.4.1.9.9.109.1.1.1.1.7','divider'=>':','position'=>'end')
        );

        $device_info =[];
        foreach($device_spec as $spec){
            $value_walk =  snmpwalk("{$device}",$this->community, $spec['oid'], 100000, 5);
            $value_segments =  explode( $spec['divider'],end($value_walk));
            if($spec['position'] === 'end'){
                $value = end($value_segments);
            }
            else{
                $value = $value_segments[$spec['position']];
            }
            $device_info[$spec['info']] = $value;
        }

        $hostname = $device_info['hostname'];
        $rrd_name = $this->path_device['rrd'].$hostname.'cpu.rrd';
        $opts = array( "--step", $this->poll."s", 
            "DS:cpu:GAUGE:".($this->poll*3)."s:0:U",
            "RRA:AVERAGE:0.5:".$this->poll."s:6h",
            "RRA:AVERAGE:0.5:1h:2d",
        );
        $rrd = rrd_create($rrd_name,$opts);
        rrd_update($rrd_name,array("N:".$device_info['cpu_last_min']."")); 

        $ds = 'cpu';
        foreach ($intervals as $i){
            $options = array(
                "--slope-mode",
                "--start", "now-".$i,
                "--end", "now",
                "--title=".$ds,
                "--vertical-label=".$ds,
                "DEF:".$ds."=".$this->path_device['rrd'].$hostname."cpu.rrd:".$ds.":AVERAGE",
                "AREA:".$ds."#474745:".$ds,
                "GPRINT:".$ds.":MIN: Minimum %6.2lf",
                "GPRINT:".$ds.":AVERAGE: Average %6.2lf",
                "GPRINT:".$ds.":MAX: Maximum %6.2lf",
            );
            $graph = rrd_graph($this->path_device['graph'].$hostname.$ds.$i.'_graph.png', $options);
        }      
        return $device_info;
        
    }  
}