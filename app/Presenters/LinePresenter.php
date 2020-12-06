<?php

declare(strict_types=1);


namespace App\Presenters;

use Nette;
use Nette\Application\UI\Form;
use App\Model\LineManager;
use App\Model\DeviceManager;
use App\Model\PollManager;
use App\Model\SlaManager;
use App\Model\ThresholdManager;

/**
 * Class LinePresenter
 * prepares data for rendering line detail page, creates components for lines and thresholds management,
 * handels regular refresh of line detail page, pagination of line alerts and associated device alerts,
 * mediates addition of line, associated devices and line thresholds, line and thresholds edit and deletion.
 */
class LinePresenter extends Nette\Application\UI\Presenter
{
    private $lineManager;
    private $deviceManager;
    private $pollManager;
    private $slaManager;
    private $thresholdManager;



	public function __construct(LineManager $lineManager, DeviceManager $deviceManager, PollManager $pollManager, SlaManager $slaManager, ThresholdManager $thresholdManager)
	{
        $this->lineManager = $lineManager;
        $this->deviceManager = $deviceManager;
        $this->pollManager = $pollManager;
        $this->slaManager = $slaManager;
        $this->thresholdManager = $thresholdManager;

        
    }
    
	public function renderShow(int $line_id, $deviceA, $deviceB, $interval, int $lineAlertPage = 0, int $deviceAlertPage = 0): void
	{   
        $types = $this->template->types = array('1'=>'icmp-echo', '2' => 'icmp-jitter');
        if (!$this->getUser()->isLoggedIn()) {
            $this->redirect('User:signIn');
        }
        $line = $this->template->line = $this->lineManager->getLineDetail($line_id);
        if (!$line) {
            $this->error('Line not found.');
        }
        $sla_oper_type = $line->sla_oper_type;
        $deviceA = $this->template->deviceA = $this->deviceManager->getDeviceDetailByID($line->device_source);
        $deviceB = $this->template->deviceB = $this->deviceManager->getDeviceDetailByID($line->device_responder);
        
        if($line->poll === 1){
            $slas = $this->slaManager->getSlas($line_id);
            $this->template->slas = array_slice($slas,2);
            $this->template->aliases = $this->slaManager->getAliases();
            $thresholds = $this->template->thresholds = $this->thresholdManager->getThresholds($line_id);
            
            $alerts = $this->lineManager->getLineAlerts($line_id);
            $line_alerts_pages = $this->template->line_alerts_pages = count($alerts);
            $this->template->lineAlertsPaginator = $this->getPagination($lineAlertPage,$line_alerts_pages); 
            $this->template->line_alerts = $alerts[$lineAlertPage];
            
            $deviceAlerts = $this->deviceManager->getDeviceAlerts($deviceA->device_ip);
            $device_alerts_pages = $this->template->device_alerts_pages = count($deviceAlerts);
            $this->template->deviceAlertsPaginator = $this->getPagination($deviceAlertPage,$device_alerts_pages); 
            $this->template->device_alerts = $deviceAlerts[$deviceAlertPage];

            if(!$interval){
                $interval = '6h';
            }
            if($sla_oper_type === 2){
                $sla_keys = array_keys(array_slice($slas,2));
                $lineGraphs = $this->slaManager->renderGraph($line_id,$sla_keys,$interval);
                $this->template->lineGraphs = $lineGraphs;
            }
            else{
                $sla = 'rtt_avg';
                $lineGraph = $this->slaManager->renderGraph($line_id,$sla,$interval);
                $this->template->lineGraph = $lineGraph;
            }
            $this->payload->thresholds = $thresholds;

        }
       

    }
    public function getPagination($page,$pages)
    {
        $paginator = new Nette\Utils\Paginator;
        $paginator->setBase(0);
        $paginator->setPage($page); 
        $paginator->setItemCount($pages-1); 
        return $paginator;
    }
    
    protected function createComponentAddLineForm(): Form
    {
        $form = new Form; 

        $type = array(
            '1'=>'icmp echo',
            '2'=>'icmp jitter'
        );
        $poll = array(
            '1'=>'poll enable',
            '0'=>'poll disable'
        );
	    $form->addInteger('sla_oper_number', 'IP SLA operation number:')
            ->setRequired('IP SLA number must be filled.')
            ->setAttribute('class','numeric-input');

        
        $form->addRadioList('sla_oper_type', 'SLA operation type:', $type)
            ->setRequired('You must select SLA operation type.');

        $form->addTextArea('line_description', 'Line description:')
            ->addRule($form::MAX_LENGTH, 'Max allowed length for description is 255 characters.', 255);


	    $form->addText('device_source', 'IP SLA source device IP:')
            ->setRequired('IP SLA initiator ip address must be filled.')
            ->addRule($form::PATTERN, 'Filled IP address is not valid','([0-9]{1,3}\.){3}[0-9]{1,3}$');

        
        $form->addText('device_responder', 'IP SLA responder device IP:')
            ->setRequired('IP SLA responder ip address must be filled.')
            ->addRule($form::PATTERN, 'Filled IP address is not valid','([0-9]{1,3}\.){3}[0-9]{1,3}$');

        
        $form->addRadioList('poll', 'Poll on/off:', $poll)
            ->setDefaultValue('1');

        
        $form->addSubmit('cancel', 'Cancel')
			->setValidationScope([])
			->onClick[] = function () {$this->redirect('Homepage:default');};	

        $form->addSubmit('send', 'Add line')
            ->setAttribute('id','submit-form');
    
        $form->onSuccess[] = [$this, 'addFormSucceeded'];

	    return $form;
    }
    protected function createComponentAddThresholdForm(): Form
    {
        $form = new Form;
        
        $form->addInteger('min', 'Minimum:')
            ->setAttribute('class','numeric-input');

        $form->addInteger('exact', 'Exact:')
            ->setAttribute('class','numeric-input');

        $form->addInteger('max', 'Maximum:')
            ->setAttribute('class','numeric-input');


        $form->addSubmit('cancel', 'Cancel')
            ->setValidationScope([])
            ->onClick[] = function () {$this->redirect('Line:show',$this->getParameter('line_id'));};	

	    $form->addSubmit('send', 'Add threshold');
    
        $form->onSuccess[] = [$this, 'addThreshSucceeded'];

	    return $form;
    }

    protected function createComponentEditLineForm(): Form
    {
        $form = new Form;
        $poll = array(
            '1'=>'poll enable',
            '0'=>'poll disable'
        );
	    $form->addInteger('sla_oper_number', 'IP SLA operation number:')
            ->setRequired('IP SLA number must be filled.')
            ->setAttribute('class','numeric-input');

        $form->addTextArea('line_description', 'Line description:')
            ->addRule($form::MAX_LENGTH, 'Max allowed length for description is 255 characters.', 255);

        
        $form->addRadioList('poll', 'Poll on/off:', $poll);

        $form->addSubmit('cancel', 'Cancel')
			->setValidationScope([])
			->onClick[] = function () {$this->redirect('Line:show',$this->getParameter('line_id'));};	

	    $form->addSubmit('send', 'Edit line');
    
        $form->onSuccess[] = [$this, 'editFormSucceeded'];

	    return $form;
    }

    protected function createComponentEditThresholdForm(): Form
    {
        $form = new Form;
        
        $form->addInteger('min', 'Minimum:')
            ->setAttribute('class','numeric-input');

        $form->addInteger('exact', 'Exact:')
            ->setAttribute('class','numeric-input');

        $form->addInteger('max', 'Maximum:')
            ->setAttribute('class','numeric-input');


        $form->addSubmit('cancel', 'Cancel')
        ->setValidationScope([])
        ->onClick[] = function () {$this->redirect('Line:show',$this->getParameter('line_id'));};	


        $form->addSubmit('send', 'Edit threshold');
    
        $form->onSuccess[] = [$this, 'editThreshSucceeded'];

	    return $form;
    }
    public function addFormSucceeded(Form $form, \stdClass $values): void
    {   
        $error = 0;
        $poll_error = $this->pollManager->pollTest();
        if(strlen($poll_error)!==0){
            $this->flashMessage($poll_error, 'fail');
            $error = 1;
            $form->setDefaults($values);

        }
        
        else{
            $line_id = $this->getParameter('line_id');
            $devA = $this->deviceManager->getDeviceIDByIP($values->device_source);
            if(!$devA){
                $deviceTest = $this->pollManager->testDevice($values->device_source);
                if(strlen($deviceTest) !== 0){
                    $this->flashMessage('Could not contact device: '.$values->device_source, 'fail');
                    $error = 1;
                    $form->setDefaults($values);
                }
                else{
                    $device_info = $this->pollManager->initialDevicePoll($values->device_source);
                    $devAid = $this->deviceManager->addDevice($values->device_source,$device_info);
                } 
            }
            else{
                $devAid = $devA->device_id;
                
                $line_exists = $this->lineManager->getLine($devAid, $values->sla_oper_number);
                if($line_exists){
                    $this->flashMessage('SLA operation of number: '.$values->sla_oper_number.' already exists for device: '.$values->device_source, 'fail');
                    $error= 1;
                    $form->setDefaults($values);
                }
            }
            $devB = $this->deviceManager->getDeviceIDByIP($values->device_responder);
            if(!$devB){
                $deviceTest = $this->pollManager->testDevice($values->device_responder);
                if(strlen($deviceTest) !== 0){
                    $this->flashMessage('Could not contact device: '.$values->device_responder, 'fail');
                    $error = 1;
                    $form->setDefaults($values);
                }
                else{
                    $device_info = $this->pollManager->initialDevicePoll($values->device_responder);
                    $devBid = $this->deviceManager->addDevice($values->device_responder,$device_info);
                }
            }
            else{
                $devBid = $devB->device_id;
            }

            $oid_error = $this->pollManager->testOID($values->device_source,$values->sla_oper_number);
            if(strlen($oid_error)!==0){
                $this->flashMessage($oid_error, 'fail');
                $error = 1;
                $form->setDefaults($values);
            }

            if($error === 0){
                $line = $this->lineManager->addLine($values,$devAid,$devBid);
                if($values->poll===1){
                    $device_sla = $this->pollManager->initialSLAPoll($values->device_source,$line->line_id,$values->sla_oper_number,$values->sla_oper_type);
                    $this->slaManager->addSla($line->line_id,$device_sla);
                }            
                $this->flashMessage('Line succesfully added', 'success');
                $this->redirect('show', $line->line_id);    
            }
	       
        }
    }

    public function addThreshSucceeded(Form $form, \stdClass $values): void
    {
        $line_id = $this->getParameter('line_id');
        $sla_type = $this->getParameter('sla_type');
        $error = 0;
        if (!$this->getUser()->isLoggedIn()) {
            $this->error('You need to sign in to be able to edit thresholds.');
        }
        if (!$this->getUser()->isInRole('admin')){
            $this->flashMessage('Only admin user can add threshold.', 'fail');
        }
        if ( !$values->min and !$values->max and !$values->exact){
            $this->flashMessage('No threshold filled.', 'fail');
            $error = 1;
        }
       
        if ($error === 0){
            if ( (!$values->min and !$values->max) or (!$values->min and !$values->exact) or (!$values->max and !$values->exact)){
                $this->thresholdManager->addThreshold($line_id,$sla_type,$values);
                $this->flashMessage('Threshold added.', 'success');
                $this->redirect('show', $line_id);
            } 
            if (($values->min !== $values->exact) and !$values->max){
                $this->thresholdManager->addThreshold($line_id,$sla_type,$values);
                $this->flashMessage('Threshold added.', 'success');
                $this->redirect('show', $line_id);
            }
            if (($values->max !== $values->exact) and !$values->min){
                $this->thresholdManager->addThreshold($line_id,$sla_type,$values);
                $this->flashMessage('Threshold added.', 'success');
                $this->redirect('show', $line_id);
            }
            if ( $values->min < $values->max and $values->min !== $values->exact and $values->max !== $values->exact){
                $this->thresholdManager->addThreshold($line_id,$sla_type,$values);
                $this->flashMessage('Threshold added.', 'success');
                $this->redirect('show', $line_id);
            }   
            
            else{
                $this->flashMessage('Thresholds not valid. Thresholds must follow rules: min < max, min != exact, max != exact.', 'fail');
            }

        }
    }

    public function editFormSucceeded(Form $form, array $values): void
    {
        if (!$this->getUser()->isLoggedIn()) {
            $this->error('You need to sign in to be able to edit line.');
        }
        if (!$this->getUser()->isInRole('admin')){
            $this->flashMessage('Only admin user can edit line', 'fail');
        }
        else{
            $error = 0;
            $line_id = $this->getParameter('line_id');
            $line = $this->lineManager->getLineDetail($line_id);
            $device_source = $this->deviceManager->getDeviceDetailByID($line->device_source);
            $deviceIP = $device_source->device_ip;
            if($line->sla_oper_number!==$values['sla_oper_number'] or ($line->poll===0 and $values['poll']!==0)){ 
                $oid_error = $this->pollManager->testOID($deviceIP,$values['sla_oper_number']);
                if(strlen($oid_error)!==0){
                    $this->flashMessage($oid_error, 'fail');
                    $error = 1;
                    $form->setDefaults($values);
                }
                if($error === 0){
                    $device_sla = $this->pollManager->initialSLAPoll($deviceIP,$line_id,$values['sla_oper_number'],$line->sla_oper_type);
                    $line_sla = $this->slaManager->getSlas($line_id);
                
                    if(!$line_sla) 
                    {
                        $this->slaManager->addSla($line_id,$device_sla);
                    }
                    else{
                        $this->slaManager->editSla($line_id,$device_sla);
                    }
                }
            }
            if($error === 0){
                $this->lineManager->editLine($line,$values);
                $this->flashMessage('Line succesfully updated.', 'success');
                $this->redirect('show', $line->line_id);
            }
            
        }
    }
    public function editThreshSucceeded(Form $form, \stdClass $values): void
    {
        $line_id = $this->getParameter('line_id');
        $sla_type = $this->getParameter('sla_type');
        $error = 0;
        if (!$this->getUser()->isLoggedIn()) {
            $this->error('You need to sign in to be able to edit thresholds.');
        }
        if (!$this->getUser()->isInRole('admin')){
            $this->flashMessage('Only admin user can edit threshold', 'fail');
        }
       
        if ( !$values->min and !$values->max and !$values->exact){
            $this->flashMessage('No threshold filled.', 'fail');
            $error = 1;
        }
        if ($error == 0){
            if ( (!$values->min and !$values->max) or (!$values->min and !$values->exact) or (!$values->max and !$values->exact)){
                $this->thresholdManager->editThreshold($line_id,$sla_type,$values);
                $this->flashMessage('Threshold added.', 'success');
                $this->redirect('show', $line_id);
            } 
            if (($values->min !== $values->exact) and !$values->max){
                $this->thresholdManager->editThreshold($line_id,$sla_type,$values);
                $this->flashMessage('Threshold added.', 'success');
                $this->redirect('show', $line_id);
            }
            if (($values->max !== $values->exact) and !$values->min){
                $this->thresholdManager->editThreshold($line_id,$sla_type,$values);
                $this->flashMessage('Threshold added.', 'success');
                $this->redirect('show', $line_id);
            }
            if ( $values->min < $values->max and $values->min !== $values->exact and $values->max !== $values->exact){
                $this->thresholdManager->editThreshold($line_id,$sla_type,$values);
                $this->flashMessage('Threshold edited.', 'success');
                $this->redirect('show', $line_id);
            }   
            else{
                $this->flashMessage('Thresholds not valid. Thresholds must follow rules: min < max, min != exact, max != exact.', 'fail');    
            }
        }
    }
    public function actionAdd(): void
    {
	    if (!$this->getUser()->isLoggedIn()) {
	 	    $this->redirect('User:signIn');
        } 
        if (!$this->getUser()->isInRole('admin')){
            $this->flashMessage('Only admin user can add line.', 'fail');
        }  
        else{
            $this['addLineForm'];
        }

    }

    public function actionEdit(int $line_id): void
    {
        if (!$this->getUser()->isLoggedIn()) {
            $this->redirect('User:signIn');
        }
        if (!$this->getUser()->isInRole('admin')){
            $this->flashMessage('Only admin user can edit line.', 'fail');
        }
        else{
            $line = $this->lineManager->getLineDetail($line_id);
	        if (!$line) {
		        $this->error('Line not found');
	        }
            $this['editLineForm']->setDefaults($line->toArray());
        }
    }
    public function actionEditThreshold(int $line_id, $sla_type): void
    {
        if (!$this->getUser()->isLoggedIn()) {
            $this->redirect('User:signIn');
        }
        if (!$this->getUser()->isInRole('admin')){
            $this->flashMessage('Only admin user can edit threshold.', 'fail');
        }
        else{
            $thresholds_all = $this->thresholdManager->getThresholds($line_id);
	        if (empty($thresholds_all[$sla_type])) {
                $this->redirect('addThreshold', $line_id, $sla_type);
            }
            $thresholds = $thresholds_all[$sla_type];
            $this['editThresholdForm']->setDefaults($thresholds);
        }
    }
    public function actionAddThreshold(int $line_id, $sla_type): void
    {
        if (!$this->getUser()->isLoggedIn()) {
            $this->redirect('User:signIn');
        }
        if (!$this->getUser()->isInRole('admin')){
            $this->flashMessage('Only admin user can add threshold.', 'fail');
        }
        else{
            $this['addThresholdForm'];
        }
    }
    public function actionDelete(int $line_id): void
    {
	    if (!$this->getUser()->isLoggedIn()) {
		    $this->redirect('User:signIn');
        }   
        if (!$this->getUser()->isInRole('admin')){
            $this->flashMessage('Only admin user can delete line.', 'fail');
        }
        else{
            $line = $this->lineManager->deleteLine($line_id);
            $this->flashMessage('Line succesfully deleted.', 'success');
        }
    }
    public function handleDeleteThreshold($line_id,$sla_type): void
    {
	    if (!$this->getUser()->isLoggedIn()) {
		    $this->redirect('User:signIn');
        } 
        if (!$this->getUser()->isInRole('admin')){
            $this->flashMessage('Only admin user can delete threshold.', 'fail');
        }  
        else{
			$this->thresholdManager->deleteThreshold ($line_id,$sla_type);
            $this->redirect('this');
        }
    }
    public function handleRefresh($interval): void
    {
	    if ($this->isAjax()) {
            $this->redrawControl('sla');
            $this->redrawControl('alerts');
	    } else {
		    $this->redirect('this');
	    }
    }
    public function handleRedrawGraphs($interval): void
    {
	    if ($this->isAjax()) {
            $this->redrawControl('sla');

	    } else {
		    $this->redirect('this');
	    }
    }
    public function handleRedrawLineAlerts($lineAlertPage): void
    {
        if ($this->isAjax()) {
            $this->redrawControl('lineAlerts');

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