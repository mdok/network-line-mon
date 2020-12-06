<?php
declare(strict_types=1);

namespace App\Model;

use Nette;

/**
 * Class ThresholdManager
 * manages database table for SLA statistics thresholds, handles selection, insertion,
 * update and deletion of thresholds.
 */
class ThresholdManager
{
	use Nette\SmartObject;

	/** @var Nette\Database\Context */
	private $database;

	public function __construct(Nette\Database\Context $database)
	{
		$this->database = $database;
	}

	public function getThresholds($line_id)
	{
        $thresholds_select = $this->database->table('t_threshold')
            ->where('line_id',$line_id)
            ->fetchAll();
        
        $thresholds =[];
        foreach($thresholds_select as $threshold){
            $thresholds[$threshold['sla_type']]=array('min'=>$threshold['min'],'max'=>$threshold['max'],'exact'=>$threshold['exact'],'over_threshold'=>$threshold['over_threshold'],'over_min'=>$threshold['over_min'],'over_exact'=>$threshold['over_exact'],'over_max'=>$threshold['over_max']);
        }
        return $thresholds;
            
    }
    public function addThreshold($line_id,$sla_type,$values): void
	{
        
        $this->database->table('t_threshold')->insert([
            'line_id'=>$line_id,
            'sla_type'=>$sla_type,
            'min'=>$values->min,
            'max'=>$values->max,
            'exact'=>$values->exact
        ]);
    }

    public function editThreshold($line_id,$sla_type,$values): void
	{
        $this->database->table('t_threshold')
            ->where('line_id = ? AND sla_type = ?',$line_id,$sla_type)
            ->update([
                'min'=>$values->min,
                'max'=>$values->max,
                'exact'=>$values->exact
        ]);   
    }
    public function deleteThreshold($line_id,$sla_type): void
    {
        $this->database->table('t_threshold')
            ->where('line_id = ? AND sla_type = ?',$line_id,$sla_type)
            ->delete();
    }
    
    
}