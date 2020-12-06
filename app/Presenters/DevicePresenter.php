<?php

declare(strict_types=1);

namespace App\Presenters;

use Nette;
use App\Model\DeviceManager;
use App\Model\LineManager;


/**
 * Class DevicePresenter
 * prepares data for rendering devices overview page and device detail page,
 * handles regular refresh of device detail page, pagination of device alerts 
 * for device detail page and mediates deletion of device.
 */
class DevicePresenter extends Nette\Application\UI\Presenter
{
    private $deviceManager;
    private $lineManager;

	public function __construct(DeviceManager $deviceManager, LineManager $lineManager)
	{
        $this->deviceManager = $deviceManager;
        $this->lineManager = $lineManager;
	}
    
    public function renderDefault(): void
    {
        if (!$this->getUser()->isLoggedIn()) {
            $this->redirect('User:signIn');
        }
        $this->template->devices = $this->deviceManager->getDevices();
    }
    public function renderShow(int $device_id, $interval, int $deviceAlertPage = 0): void
	{   
        if (!$this->getUser()->isLoggedIn()) {
            $this->redirect('User:signIn');
        }
        
        $device = $this->template->device = $this->deviceManager->getDeviceDetailByID($device_id);
        if (!$device) {
            $this->error('Device not found.');
        } 
        if(!$interval){
            $interval = '6h';
        }
        $stat = 'cpu';
        $stat_graph = $stat.'_graph';
        $this->template->stat_graph = $this->deviceManager->renderGraph($device->hostname,$stat,$interval);
        $this->template->device_stats = $this->deviceManager->getDeviceStats($device_id);
        $this->template->lines = $this->lineManager->getAsocLines($device_id);
        
        $deviceAlerts = $this->deviceManager->getDeviceAlerts($device->device_ip);
        $device_alerts_pages = $this->template->device_alerts_pages = count($deviceAlerts);
        $this->template->deviceAlertsPaginator = $this->getPagination($deviceAlertPage,$device_alerts_pages); 
        $this->template->device_alerts = $deviceAlerts[$deviceAlertPage];
    }
    public function getPagination($page,$pages)
    {
        $paginator = new Nette\Utils\Paginator;
        $paginator->setBase(0);
        $paginator->setPage($page); 
        $paginator->setItemCount($pages-1); 
        return $paginator;
    }

    public function actionDelete(int $device_id, $hostname): void
    {   
        if (!$this->getUser()->isLoggedIn()) {
		    $this->redirect('User:signIn');
        } 
        if (!$this->getUser()->isInRole('admin')){
            $this->flashMessage('Only admin user can delete device.', 'fail');
        }
        
        $lines = $this->template->lines = $this->lineManager->getAsocLines($device_id);
        if($lines){
            $this->flashMessage('Device is still associated with existing lines, therefore can not be deleted.', 'fail');
        }
        else{
            $device = $this->deviceManager->deleteDevice($device_id,$hostname);
            $this->flashMessage('Device sucesfully deleted.', 'success');

        }
    }

    public function handleRefresh($interval): void
    {
	    if ($this->isAjax()) {
		    $this->redrawControl('cpu');
	    } else {
		    $this->redirect('this');
	    }
    }
    public function handleRedrawGraphs($interval): void
    {
	    if ($this->isAjax()) {
            $this->redrawControl('cpu');

	    } else {
		    $this->redirect('this');
	    }
    }
    public function handleRedrawDeviceAlerts($deviceAlertPage): void
    {
        if ($this->isAjax()) {
            $this->redrawControl('deviceAlerts');

	    } else {
		    $this->redirect('this');
	    }
    }



}
