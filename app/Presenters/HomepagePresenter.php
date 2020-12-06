<?php

declare(strict_types=1);

namespace App\Presenters;

use Nette;
use App\Model\LineManager;
use App\Model\SlaManager;
use App\Model\DeviceManager;
use App\Model\ThresholdManager;
use App\Model\UserManager;
use Nette\Utils\Json;


/**
 * Class HomepagePresenter
 * prepares data for rendering lines overview page (homepage), matrix overview page and grid overview page,
 * handels regular refresh of grid and matrix overview page, mediates change of user settings for those pages 
 * and pagination of summary line alerts for both pages.
 */
class HomepagePresenter extends Nette\Application\UI\Presenter
{
    private $lineManager;
    private $slaManager;
    private $deviceManager;
    private $thresholdManager;
    private $userManager;


	public function __construct(LineManager $lineManager, SlaManager $slaManager, DeviceManager $deviceManager, ThresholdManager $thresholdManager, UserManager $userManager)
	{
        $this->lineManager = $lineManager;
        $this->slaManager = $slaManager;
        $this->deviceManager = $deviceManager;
        $this->thresholdManager = $thresholdManager;
        $this->userManager = $userManager;

	}
    
    public function renderDefault(): void
    {
		if (!$this->getUser()->isLoggedIn()) {
            $this->redirect('User:signIn');
        }
        $lines = $this->lineManager->getLines();
        $types = array('1'=>'icmp-echo', '2' => 'icmp-jitter');

        if($lines){
            $linesToDevies=[];
            foreach($lines as $line){
                $linesToDevices[$line->line_id]['source'] = $this->deviceManager->getDeviceDetailByID($line->device_source)->hostname;
                $linesToDevices[$line->line_id]['responder'] = $this->deviceManager->getDeviceDetailByID($line->device_responder)->hostname;
            }
            $this->template->linesToDevices = $linesToDevices;
            $this->template->types = $types;
            $this->template->lines = $lines;
        }
		
    }
    public function renderMatrix(int $lineAlertPage = 0): void
    {   
        $user_id = $this->getUser()->id;
        $devices = $this->template->devices = $this->deviceManager->getDevices();
        $lines = $this->lineManager->getLines();
        if($lines){
            foreach ($lines as $line){
                $line_thresholds = $this->thresholdManager->getThresholds($line->line_id);
                $linesToThresholds[$line->line_id] = $line_thresholds;
            }
            $this->template->lines = $lines;
            $this->template->linesToThresholds = $linesToThresholds;
            $this->template->lines_devices = $this->lineManager->getLinesDevices();
            
            $activeSlas = $this->template->activeSlasMatrix = $this->userManager->getActiveSlasMatrix($user_id);
            $matrix = $this->template->matrix = $this->lineManager->getMatrix();
            $all_slas = $this->template->slas = $this->slaManager->getAllSlas();
            $aliases = $this->template->aliases = $this->slaManager->getAliases();

            $this->payload->thresholds = $linesToThresholds;
            $this->payload->aliases = $aliases;
            $this->payload->slas = $all_slas;
            $this->payload->activeSlasMatrix = $activeSlas;
        }
        $alerts = $this->lineManager->getAlerts();
        $line_alerts_pages = $this->template->line_alerts_pages = count($alerts);
        $this->template->lineAlertsPaginator = $this->getPagination($lineAlertPage,$line_alerts_pages); 
        $this->template->line_alerts = $alerts[$lineAlertPage];
    }
    public function renderGrid(int $lineAlertPage = 0): void
    {
        $user_id = $this->getUser()->id;
		$devices = $this->template->devices = $this->deviceManager->getDevices();
        $lines =  $this->lineManager->getLines();
        if($lines){
            foreach ($lines as $line){
                $line_slas = $this->slaManager->getSlas($line->line_id);
                $linesToSlas[$line->line_id] = array_slice($line_slas,2);
                $line_thresholds = $this->thresholdManager->getThresholds($line->line_id);
                $linesToThresholds[$line->line_id] = $line_thresholds;
            }
            $this->template->linesToSlas = $linesToSlas;
            $this->template->linesToThresholds = $linesToThresholds;
            $this->template->lines = $lines;
            $activeSlas = $this->template->activeSlasGrid = $this->userManager->getActiveSlasGrid($user_id);
            $aliases = $this->template->aliases = $this->slaManager->getAliases();

            $this->payload->thresholds = $linesToThresholds;
            $this->payload->aliases = $aliases;
            $this->payload->activeSlasGrid = $activeSlas;
            $this->payload->linesToSlas = $linesToSlas;


        }
        $alerts = $this->lineManager->getAlerts();
        $line_alerts_pages = $this->template->line_alerts_pages = count($alerts);
        $this->template->lineAlertsPaginator = $this->getPagination($lineAlertPage,$line_alerts_pages); 
        $this->template->line_alerts = $alerts[$lineAlertPage];

    }
    public function getPagination($page,$pages)
    {
        $paginator = new Nette\Utils\Paginator;
        $paginator->setPage($page); 
        $paginator->setItemCount($pages-1); 
        return $paginator;
    }
    public function handleRefresh(): void
    {
	    if ($this->isAjax()) {
		    $this->redrawControl('sla');
	    } else {
		    $this->redirect('this');
	    }
    }
    public function handleSaveGrid($data): void
    {
	    if ($this->isAjax()) {
            $user_id = $this->getUser()->id;
            $this->userManager->setActiveSlasGrid($user_id,$data);


	    } else {
		    $this->redirect('this');
	    }
    }
    public function handleSaveMatrix($data): void
    {
	    if ($this->isAjax()) {
            $user_id = $this->getUser()->id;
            $this->userManager->setActiveSlasMatrix($user_id,$data);


	    } else {
		    $this->redirect('this');
	    }
    }
    public function handleRedrawLineAlerts($lineAlertPage): void{
        if ($this->isAjax()) {
            $this->redrawControl('lineAlerts');

	    } else {
		    $this->redirect('this');
	    }
    }
    

}
