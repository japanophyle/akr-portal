<?php
class slimNavigation_menu{
	private $SLIM;
	private $MENU_DATA;
	private $MENU_REQUIRED=array('members','events');
	private $MENU_CONFIG;
	private $CONFIG_FILE;
	private $USER;
	
	function __construct($slim){
		$this->SLIM=$slim;
		$this->USER=$slim->user;
		$this->CONFIG_FILE=APP.'config/cfg.'.SLIM_SITE_NAME.'.menu_data.php';
		$this->initMenu();
	}
	
	public function get($what=false,$vars=false){
		switch($what){
			case 'all':
				return $this->MENU_DATA;
				break;
			case 'config_all':
				return $this->MENU_CONFIG;
				break;
			case 'config':
				return issetCheck($this->MENU_CONFIG,$vars);
				break;
			default:
				$chk=issetCheck($this->MENU_DATA,$what);
				if($chk && $vars){
					return issetCheck($chk,$vars);
				}
				return $chk;
		}
	}	
	public function setConfig($what=false,$vars=false){
		if($what){
			if($what==='save'){
				return $this->saveMenuData();
			}else{
				$this->MENU_CONFIG[$what]=$vars;
				return true;
			}
		}
		return false;
	}
	private function initMenu(){
		$this->resetMenu();
		$this->loadMenuData();
		$this->renderMenu();
	}
	private function resetMenu(){
		$this->MENU_DATA['dashboard']=array(
			'dashboard'=>array('href'=>'dashboard','label'=>'Dashboard','class'=>false,'submenu'=>false)
		);		
	}
	private function loadMenuData(){
		if(file_exists($this->CONFIG_FILE)){
			$data=file_get_contents($this->CONFIG_FILE);
			try{
				$data=json_decode($data,1);
			}catch(Exception $e){
				if($this->USER['access']>=30){
					throw new Exception($e);
				}
				$data=false;
			}
			if($data){
				$data['members']['members']['submenu'][]=array('href'=>'members/list/former','label'=>'Former Members','class'=>false,'submenu'=>false);
				$data['admin']['applications']['applications']=array('href'=>'signup','label'=>'Application Forms','class'=>false,'submenu'=>false);
				$data['members']['subscriptions']=array('href'=>'subscriptions','label'=>'Subscriptions','class'=>false,'submenu'=>false);
				$this->MENU_CONFIG=$data;
			}
		}
	}
	private function saveMenuData(){
		if(!empty($this->MENU_CONFIG)){
			$data=json_encode($this->MENU_CONFIG);
			return file_put_contents($this->CONFIG_FILE,$data);
		}
		return false;
	}
	private function renderMenu(){
		if($this->MENU_CONFIG){
			foreach($this->MENU_CONFIG as $i=>$v){
				if($i!=='admin') $this->MENU_DATA[$i]=$v;
			}
			//ensure required items are set
			foreach($this->MENU_REQUIRED as $m) if(!isset($this->MENU_DATA[$m])) $this->MENU_DATA[$m]=array();
			
			//add member items
			$this->membersMenu();
			
			//add dojo items
			$this->dojoMenu();
			
			//add event items
			$this->eventsMenu();
			
			//add sales items
			$this->salesMenu();

			//add admin items
			if($this->USER['access']>=25){
				$this->MENU_DATA['mailer']['mailer']=array('href'=>'mailer/','label'=>'Emailer','class'=>false,'submenu'=>false);
				foreach($this->MENU_CONFIG['admin'] as $i=>$v){
					switch($i){
						case 'members':
							if(isset($v['new'])){
								foreach($v['submenu'] as $x=>$y){
									$this->MENU_DATA[$i][$i]['submenu'][]=$y;
								}
							}
							if(isset($v['new'])){
								$this->MENU_DATA[$i]['new']=$v['new'];
							}
							break;
						default:
							if($i==='bookings' && $this->USER['access']<26){
								//super only
							}else{
								foreach($v as $x=>$y){
									$this->MENU_DATA[$i][$x]=$y;
								}
							}
					}
				}
			}
		}
	}
	private function dojoMenu(){
		if($this->USER['access']>20){
			if(hasAccess($this->USER,'clubs','read')){
				$rec=false;
				$dojos=$this->SLIM->options->get('dojos');
				$clubs=$this->SLIM->options->get('clubs');
				$club_keys=array_keys($clubs);
				$dojolock=issetCheck($this->USER,'dojo_lock',array());
				$this->MENU_DATA['dojo']=array(
					'dojo'=>array('href'=>'dojo','label'=>'Dojos','class'=>false,'submenu'=>false),
				);
				if($this->USER['access']>=25){//admin only
					unset($this->MENU_DATA['dojo']['new']);
					unset($this->MENU_CONFIG['admin']['dojo']['new']);
				}
			}
		}		
	}
	
	private function membersMenu(){
		$this->MENU_DATA['members']=false;//reset
		$members['members']=array('href'=>'members/','label'=>'Members','class'=>'nogo','submenu'=>true);
		if($this->USER['access']>20){
			if(hasAccess($this->USER,'events','read')){
				$rec=false;
				$members['active']=array('href'=>'members/list/active','label'=>'Active Members','class'=>false);
				$members['inactive']=array('href'=>'members/list/inactive','label'=>'Inactive Members','class'=>false);
				$members['former']=array('href'=>'members/list/former','label'=>'Former Members','class'=>false);
				$this->MENU_DATA['subscriptions']['subscriptions']=array('href'=>'subscriptions','label'=>'Subscriptions','class'=>false);
				if($this->USER['access']>=25){//list links
					$members['new']=array('href'=>'members/new/','label'=>'New Member','class'=>false);
				}
				$this->MENU_DATA['members']=$members;
			}
		}
	}
	private function eventsMenu(){
		$this->MENU_DATA['events']=false;//reset
		if($this->USER['access']>20){
			if(hasAccess($this->USER,'events','read')){
				$rec=false;
				if($this->USER['access']>=25){//list links
					$events=$this->SLIM->options->get('events');
					foreach($events as $i=>$v){
						$rec[]=array('href'=>'events/list/'.$i,'label'=>$v['OptionName'],'class'=>false,'submenu'=>false);
					}
				}
				if($rec){
					$this->MENU_DATA['events']=array(
						'events'=>array('href'=>'#nogo','label'=>'Event Lists','class'=>'nogo','submenu'=>$rec),
					);
					if($this->USER['access']>=25){//admin only
						$this->MENU_DATA['events']['dashboard']=array('href'=>'events/dashboard/','label'=>'Dashboard','class'=>false);
						$this->MENU_DATA['events']['new']=array('href'=>'events/new/','label'=>'New Event','class'=>false);
					}
				}else{
					$this->MENU_DATA['events']['events']=array('href'=>'events/list/active','label'=>'Events','class'=>false,'submenu'=>false);
				}
			}
		}		
	}
	private function salesMenu(){
		if($this->USER['access']>20){
			if(hasAccess($this->USER,'members','read')){
				$this->MENU_DATA['sales']['sales']=array('href'=>'events/sales/','label'=>'Sales','class'=>false);
			}
		}		
	}

}
