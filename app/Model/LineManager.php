<?php
declare(strict_types=1);

namespace App\Model;

use Nette;

/**
 * Class LineManager
 * manages database table for lines, handles selection, insertion,
 * update and deletion of lines, prepares line alerts and data for matrix overview.
 */
class LineManager
{
	use Nette\SmartObject;

	/** @var Nette\Database\Context */
    private $database;
    private $line_path;
    private $path;

	public function __construct($line_path,$path,Nette\Database\Context $database)
	{
        $this->database = $database;
        $this->line_path = $line_path;
        $this->path = $path;
	}

	public function getLines()
	{
		return $this->database->table('t_lines')
            ->order('line_id ASC')
            ->fetchAll();
    }
    public function getLine($source,$number){
        return $this->database->table('t_lines')
            ->where('device_source = ? AND sla_oper_number = ?', $source, $number)
            ->fetch();
    }
    public function getAsocLines($device_id)
	{
        return $this->database->table('t_lines')
            ->where('device_source = ? OR device_responder = ?', $device_id, $device_id)
            ->order('line_id ASC')
            ->fetchAll();
    }
    public function getLineDetail($line_id)
	{
		return $this->database->table('t_lines')
            ->where('line_id',$line_id)
            ->fetch();
    }
    public function getLineAlerts($line_id)
	{
        $line_log_path =$this->line_path['log'].$line_id.'alerts.log';
        $line_log = fopen($line_log_path,'r');
        $line_alerts_forward =[];
        while(!feof($line_log)){
            array_push($line_alerts_forward,fgets($line_log));
        }
        fclose($line_log);
        $line_alerts = array_reverse($line_alerts_forward);
        $allAlertsArray = [];
        for($i=1; $i<=count($line_alerts);$i+=5){
            $alerts = array_slice($line_alerts,$i,5);
            array_push($allAlertsArray,$alerts);
        }
        return $allAlertsArray;
       
    }
    public function getAlerts()
	{
        $log_path =$this->path.'alerts.log';
        $log = fopen($log_path,'r');
        $alerts_forward =[];
        while(!feof($log)){
            array_push($alerts_forward,fgets($log));
        }
        fclose($log);
        $allAlerts = array_reverse($alerts_forward);
        $allAlertsArray = [];
        for($i=1; $i<=count($allAlerts);$i+=5){
            $alerts = array_slice($allAlerts,$i,5);
            array_push($allAlertsArray,$alerts);
        }
        return $allAlertsArray;
    }
    public function getLinesDevices(){
        $devices = $this->database 
            ->query( 'SELECT * FROM (SELECT device_source FROM t_lines UNION SELECT device_responder FROM t_lines) AS ids')
            ->fetchAll();

        $device_ids =[];
        $i=0;
        foreach($devices as $device){
            $i++;
            $device_ids[$i] = $device->device_source;
        }
        return $device_ids;
    }
    public function getMatrix()
	{   
        $device_ids = $this->getLinesDevices(); 
        
        foreach(range(1,count($device_ids)) as $source){
            foreach(range(1,count($device_ids)) as $responder){
                $source_id = $device_ids[$source];
                $responder_id = $device_ids[$responder];
                if($source_id === $responder_id){
                        $matrix[$source][$responder]='x';
                }
                else{
                    $line = $this->database->table('t_lines')
                        ->where('device_source = ? AND device_responder = ?', $source_id, $responder_id)
                        ->fetchAll();

                    if($line){
                        $l_array = [];
                        foreach($line as $l){
                            array_push($l_array,$l['line_id']);
                        }
                        $matrix[$source][$responder]=$l_array;
                    }
                    else{
                        $matrix[$source][$responder]='none';
                    }
                }
            }
        }
    return $matrix;

    }
    
    public function addLine($values,$devAid,$devBid)
	{
        $line = $this->database->table('t_lines')->insert([
            'sla_oper_number' => $values->sla_oper_number,
            'line_description' => $values->line_description,
            'device_source' => $devAid,
            'device_responder' => $devBid,
            'sla_oper_type' => $values->sla_oper_type,
            'poll' =>$values->poll
    
        ]); 
        $line_log = fopen($this->line_path['log'].$line->line_id.'alerts.log','w');
        fclose($line_log);

        return $line;
		
    }
    public function editLine($line,$values): void
	{
        $line->update($values);
    }
    public function deleteLine($line_id): void
	{
        $this->database->table('t_lines')
            ->where('line_id',$line_id)
            ->delete();

        unlink($this->line_path['log'].$line_id.'alerts.log');
        unlink($this->line_path['rrd'].$line_id.'sla.rrd');
        foreach (glob($this->line_path['graph'].$line_id.'*.png') as $f) {
            unlink($f);
        }

    }
    
}