<?php
declare(strict_types=1);

namespace App\Model;

use Nette;
use Nette\Utils\Image;

/**
 * Class SlaManager
 * manages database table for SLA statistics, handles selection, insertion,
 * update and deletion of SLA statistics, prepares SLA statistics graphs and aliases.
 */
class SlaManager
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
	public function getAllSlas()
	{
		$slas =  $this->database->table('t_sla')
			->order('line_id ASC');
		$slas_array = [];
		
		foreach($slas as $sla){
			foreach($sla as $slaKey => $slaVal){
				$slas_array[$sla->line_id][$slaKey] = $slaVal;	
			}
		}
		return $slas_array;
	
	}
	public function getSlas($line_id)
	{
		$slas_select = $this->database->table('t_sla')
            ->where('line_id',$line_id)
			->fetch();
		if($slas_select !== null){ 
			$slas= [];
			foreach($slas_select as $slaKey => $slaVal){
				$slas[$slaKey] = $slaVal;
			}
			return $slas;
		}

		
	}
	public function addSla($line_id,$device_sla): void
	{
        $this->database->table('t_sla')->insert([
            'line_id' => $line_id,
            'rtt_min' => $device_sla['4'],
            'rtt_avg' => $device_sla['51'],
            'rtt_max' => $device_sla['5'],
			'rtt_sum' => $device_sla['1'],
			'latency_ds_min' => $device_sla['41'],
			'latency_ds_avg' => $device_sla['48'],
			'latency_ds_max' => $device_sla['42'],
			'latency_ds_sum' => $device_sla['39'],
			'latency_sd_min' => $device_sla['37'],
			'latency_sd_avg' => $device_sla['47'],
			'latency_sd_max' => $device_sla['38'],
			'latency_sd_sum' => $device_sla['35'],
			'latency_numof_sam' => $device_sla['43'],
			'packet_loss' => $device_sla['26'],
			'packet_late' => $device_sla['32'],
			'packet_outseq_bidi' => $device_sla['27'],
			'packet_outof_seq_sd' => $device_sla['28'],
			'packet_outof_seq_ds' => $device_sla['29'],
			'packet_skipped' => $device_sla['30'],
			'jitter_pos_sd_min' => $device_sla['6'],
			'jitter_pos_sd_max' => $device_sla['7'],
			'jitter_pos_sd_sum' => $device_sla['8'],
			'jitter_pos_sd_sam' => $device_sla['9'],
			'jitter_neg_sd_min' => $device_sla['11'],
			'jitter_neg_sd_max' => $device_sla['12'],
			'jitter_neg_sd_sum' => $device_sla['13'],
			'jitter_neg_sd_sam' => $device_sla['14'],
			'jitter_pos_ds_min' => $device_sla['16'],
			'jitter_pos_ds_max' => $device_sla['17'],
			'jitter_pos_ds_sum' => $device_sla['18'],
			'jitter_pos_ds_sam' => $device_sla['19'],
			'jitter_neg_ds_min' => $device_sla['21'],
			'jitter_neg_ds_max' => $device_sla['22'],
			'jitter_neg_ds_sum' => $device_sla['23'],
			'jitter_neg_ds_sam' => $device_sla['24'],
			'jitter_avg_sd' => $device_sla['45'],
			'jitter_avg_ds' => $device_sla['46'],
			'jitter_avg_bidir' => $device_sla['44'],
			'jitter_intarr_resp' => $device_sla['49'],
			'jitter_intarr_sour' => $device_sla['50']
        ]);
		
	}
	public function editSla($line_id,$device_sla): void
	{
        $this->database->table('t_sla')->where('line_id',$line_id)
			->update(array(
				'rtt_min' => $device_sla['4'],
            	'rtt_avg' => $device_sla['51'],
            	'rtt_max' => $device_sla['5'],
				'rtt_sum' => $device_sla['1'],
				'latency_ds_min' => $device_sla['41'],
				'latency_ds_avg' => $device_sla['48'],
				'latency_ds_max' => $device_sla['42'],
				'latency_ds_sum' => $device_sla['39'],
				'latency_sd_min' => $device_sla['37'],
				'latency_sd_avg' => $device_sla['47'],
				'latency_sd_max' => $device_sla['38'],
				'latency_sd_sum' => $device_sla['35'],
				'latency_numof_sam' => $device_sla['43'],
				'packet_loss' => $device_sla['26'],
				'packet_late' => $device_sla['32'],
				'packet_outseq_bidi' => $device_sla['27'],
				'packet_outof_seq_sd' => $device_sla['28'],
				'packet_outof_seq_ds' => $device_sla['29'],
				'packet_skipped' => $device_sla['30'],
				'jitter_pos_sd_min' => $device_sla['6'],
				'jitter_pos_sd_max' => $device_sla['7'],
				'jitter_pos_sd_sum' => $device_sla['8'],
				'jitter_pos_sd_sam' => $device_sla['9'],
				'jitter_neg_sd_min' => $device_sla['11'],
				'jitter_neg_sd_max' => $device_sla['12'],
				'jitter_neg_sd_sum' => $device_sla['13'],
				'jitter_neg_sd_sam' => $device_sla['14'],
				'jitter_pos_ds_min' => $device_sla['16'],
				'jitter_pos_ds_max' => $device_sla['17'],
				'jitter_pos_ds_sum' => $device_sla['18'],
				'jitter_pos_ds_sam' => $device_sla['19'],
				'jitter_neg_ds_min' => $device_sla['21'],
				'jitter_neg_ds_max' => $device_sla['22'],
				'jitter_neg_ds_sum' => $device_sla['23'],
				'jitter_neg_ds_sam' => $device_sla['24'],
				'jitter_avg_sd' => $device_sla['45'],
				'jitter_avg_ds' => $device_sla['46'],
				'jitter_avg_bidir' => $device_sla['44'],
				'jitter_intarr_resp' => $device_sla['49'],
				'jitter_intarr_sour' => $device_sla['50']
			));
	}
	public function getAliases(){
		$aliases = [
			'rtt_min' => 'RTT[min]',
            'rtt_avg' => 'RTT[avg]',
            'rtt_max' => 'RTT[max]',
			'rtt_sum' => 'RTT[sum]',
			'latency_ds_min' => 'Latency[min] d->s',
			'latency_ds_avg' => 'Latency[avg] d->s',
			'latency_ds_max' => 'Latency[max] d->s',
			'latency_ds_sum' => 'Latency[sum] d->s',
			'latency_sd_min' => 'Latency[min] s->d',
			'latency_sd_avg' => 'Latency[avg] s->d',
			'latency_sd_max' => 'Latency[max] s->d',
			'latency_sd_sum' => 'Latency[sum] s->d',
			'latency_numof_sam' => 'Latency[samples]',
			'packet_loss' => 'Packet loss',
			'packet_late' => 'Packet late',
			'packet_outseq_bidi' => 'Packet out of seq',
			'packet_outof_seq_sd' => 'Packet out of seq s->d',
			'packet_outof_seq_ds' => 'Packet out of seq d->s',
			'packet_skipped' => 'Packet skipped',
			'jitter_pos_sd_min' => 'Jitter[+][min] s->d',
			'jitter_pos_sd_max' => 'Jitter[+][max] s->d',
			'jitter_pos_sd_sum' => 'Jitter[+][sum] s->d',
			'jitter_pos_sd_sam' => 'Jitter[+][samples] s->d',
			'jitter_neg_sd_min' => 'Jitter[-][min] s->d',
			'jitter_neg_sd_max' => 'Jitter[-][max] s->d',
			'jitter_neg_sd_sum' => 'Jitter[-][sum] s->d',
			'jitter_neg_sd_sam' => 'Jitter[-][samples] s->d',
			'jitter_pos_ds_min' => 'Jitter[+][min] d->s',
			'jitter_pos_ds_max' => 'Jitter[+][max] d->s',
			'jitter_pos_ds_sum' => 'Jitter[+][sum] d->s',
			'jitter_pos_ds_sam' => 'Jitter[+][samples] d->s',
			'jitter_neg_ds_min' => 'Jitter[-][min] d->s',
			'jitter_neg_ds_max' => 'Jitter[-][max] d->s',
			'jitter_neg_ds_sum' => 'Jitter[-][sum] d->s',
			'jitter_neg_ds_sam' => 'Jitter[-][samples] d->s',
			'jitter_avg_sd' => 'Jitter[avg] s->d',
			'jitter_avg_ds' => 'Jitter[avg] d->s',
			'jitter_avg_bidir' => 'Jitter[avg]',
			'jitter_intarr_resp' => 'Jitter[interarrival] responder',
			'jitter_intarr_sour' => 'Jitter[interarrival] source',

		];
		return $aliases;
	}
	public function renderGraph($line_id,$slas,$interval)
	{
		if(is_array($slas)===true){
			$lineGraphs = [];
			foreach($slas as $sla){
				try{
					$graph_sla = Image::fromFile($this->path['graph'].$line_id.$sla.$interval.'_graph.png');
					$lineGraphs[$sla] = $graph_sla;
				}
				catch(Nette\Utils\UnknownImageFileException $e){
					return false; 
				}
			}
			return $lineGraphs;
		}
		else{
			try{
				$graph_sla = Image::fromFile($this->path['graph'].$line_id.$slas.$interval.'_graph.png');
				return $graph_sla;
			}
			catch(Nette\Utils\UnknownImageFileException $e){
				return false;
			}
		}
		
	}
	
}