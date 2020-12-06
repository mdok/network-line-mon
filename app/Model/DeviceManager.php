<?php
declare(strict_types=1);

namespace App\Model;

use Nette;
use Nette\Utils\Image;

/**
 * Class DeviceManager
 * manages database tables for devices and devices stats, handles selection, insertion,
 * update and deletion of device and device stats, prepares device stats graphs and alerts.
 */
class DeviceManager
{
	use Nette\SmartObject;

	/** @var Nette\Database\Context */
    private $database;
    private $path;

	public function __construct($path,Nette\Database\Context $database)
	{
        $this->database = $database;
        $this->path = $path;
	}

	public function getDevices()
	{
		return $this->database->table('t_devices')
			->order('device_id ASC');
    }
    public function getDeviceStats($device_id)
	{
		return $this->database->table('t_device_stat')
            ->where('device_id',$device_id)
            ->fetch();
    }
    public function getDeviceDetailByID($device_id)
	{
		return $this->database->table('t_devices')
            ->where('device_id',$device_id)
            ->fetch();
    }
    public function getDeviceIDByIP($deviceIP)
	{
		return $this->database->table('t_devices')
            ->where('device_ip',$deviceIP)
            ->fetch();
    }
    public function getDeviceAlerts($device_ip)
	{
        $device_log_path =$this->path['log'].$device_ip.'alerts.log';
        $device_log = fopen($device_log_path,'r');
        $device_alerts_forward =[];
        while(!feof($device_log)){
            array_push($device_alerts_forward,fgets($device_log));
        }
        fclose($device_log);
        $device_alerts = array_reverse($device_alerts_forward);
        $allAlertsArray = [];
        for($i=1; $i<=count($device_alerts);$i+=5){
            $alerts = array_slice($device_alerts,$i,5);
            array_push($allAlertsArray,$alerts);
        }
        return $allAlertsArray;
    }
    public function addDevice($deviceIP,$device_info)
	{
        $device = $this->database->table('t_devices')->insert([
            'hostname' => $device_info['hostname'],
            'device_ip' => $deviceIP,
            'sys_description' => $device_info['sys_description'],
            'image_info' => $device_info['image_info'],
            'image_ver' => $device_info['image_ver'],
            'device_type' => $device_info['device_type'],
            'feature_set' => $device_info['feature_set']
        ]);
        $device = $this->database->table('t_device_stat')->insert([
            'cpu_last_min' => $device_info['cpu_last_min'],
            'device_id' => $device->device_id
        ]);
        $device_log = fopen($this->path['log'].$deviceIP.'alerts.log','w');
        fclose($device_log);
        
        return $device->device_id;
		
    }
    public function deleteDevice($device_id,$hostname): void
	{
        $device_ip = $this->database->table('t_devices')
            ->select('device_ip')
            ->where('device_id',$device_id)
            ->fetchField();
        $this->database->table('t_devices')
            ->where('device_id',$device_id)
            ->delete();
       
        unlink($this->path['log'].$device_ip.'alerts.log');
        unlink($this->path['rrd'].$hostname.'cpu.rrd');
        foreach (glob($this->path['graph'].$hostname.'*.png') as $f) {
            unlink($f);
        }  
		
    }
    public function renderGraph($device_hostname,$stat,$interval)
	{
        try{
            $graph_stat = Image::fromFile($this->path['graph'].$device_hostname.$stat.$interval.'_graph.png');
            return $graph_stat;
        }
        catch(Nette\Utils\UnknownImageFileException $e){
            return false;
        }
	}
    
}