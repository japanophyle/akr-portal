<?php

class slimAdminDashboard{
	private $SLIM;
	private $DASH_DATA=[];
	private $AJAX;
	private $ROUTE;
	private $USER;
	private $MODE='charts';
	private $CSS;
	private $CHART;
	private $PERMLINK;
	
	function __construct($slim=null){
		if(!$slim){
		  throw new Exception(__METHOD__.': no slim object!!');
		}
		$this->SLIM=$slim;
		$this->AJAX=$this->SLIM->router->get('ajax');
		$this->ROUTE=$this->SLIM->router->get('route');
		$this->USER=$this->SLIM->user;
		$this->PERMLINK=URL.'admin/';
	}

	function render(){
		$notice=$this->renderUserNotify();
		$this->setDashData();
		$panel=($this->MODE==='charts')?$this->renderBarCharts():$this->renderDashLinks();
		$mix=$panel['Member Count'].$panel['Gender Count'];
		$panel['Member Count']=$mix;
		unset($panel['Gender Count']);
		$panel_class=($this->MODE==='charts')?'':'medium-4 large-3';
		$panel='<div class="cell '.$panel_class.'"><div>'.implode('</div></div><div class="cell '.$panel_class.'"><div>',$panel).'</div></div>';
		$dash['content']=$this->CSS.'<div class="grid-x">'.$panel.$notice.'</div>';
		$dash['title']=lang('Main Dashboard');
		$txt=($this->MODE==='charts')?'Classic View':'Chart View';
		$mde=($this->MODE==='charts')?'links':'charts';
		$controls='<button class="button button-navy gotoME" data-ref="?dashmode='.$mde.'">'.$txt.'</button>';
		//$controls.='<button class="button button-aqua" data-toggle="offCanvas_menu"><i class="fi-list"></i> Main Menu</button>';
		$controls='<div class="button-group float-right small">'.$controls.'</div>';
		$this->SLIM->topbar->setInfoBarControls('right',array($controls),true);
		$this->SLIM->assets->add('js','notify','$("#notice_once").foundation("open");');
		return $dash;		
	}
	private function renderUserNotify(){
		$notice=false;
		$notice_end=strtotime('2020-01-31');
		$now=time();
		if($now<$notice_end){
			if(!slimSession('get','dashboard_notification')){
				$notice=file_get_contents(TEMPLATES.'app/tpl.admin_notification.html');
				setMySession('dashboard_notification',1);
				$this->SLIM->assets->add('js','$("#notice_once").foundation("open");','notify');			
			}
		}
		return $notice;
	}
	private function renderDashLinks(){
		$panel=array();		
		foreach($this->DASH_DATA as $title=>$rec){
			$dashlinks='<h5>'.$title.'</h5>';
			foreach($rec['links'] as $i=>$v){
				$color=issetCheck($v,'color',$rec['color']);
				$icon=issetCheck($v,'icon',$rec['icon']);
				$href=issetCheck($v,'href','#nogo');
				$caption=issetCheck($v,'caption',$title);
				$content=issetCheck($v,'content','???');
				$but=array('color'=>$color,'icon'=>$icon,'href'=>$href);
				$cont=array('caption'=>$v['caption'],'content'=>$content);
				$dashlinks.=$this->SLIM->zurb->iconButton($but,$cont);
			}
			$panel[$title]=$dashlinks;
		}
		return $panel;
	}
	
	private function renderBarCharts(){
		if(!$this->CHART) $this->CHART=new slimBarChart();
		$this->CSS=$this->CHART->CSS;
		$panel=array();	
		foreach($this->DASH_DATA as $title=>$data){
			$cd=array('axis_max'=>0,'axis_step'=>0,'bars'=>[]);			
			$this->CHART->TITLE=($title==='Active Members')?$title.' (by dojo)':$title;
			$type=($title==='General')?'buttons':'vertical';
			foreach($data['links'] as $i=>$v){
				if($title==='General'){
					$buts=$this->formatData('but',$v,$data);
					$cd['bars'][]='<button class="button button-'.$buts[0]['color'].' gotoME" data-ref="'.$buts[0]['href'].'"><i class="fi-'.$buts[0]['icon'].' icon-x2"></i><br/><span style="font-size:1rem;">'.$buts[1]['content'].'<br/>'.$buts[1]['caption'].'</span></button>';
				}else{
					$cd['bars'][]=$this->formatData('chart',$v,$data);
				}
			}
			$panel[$title]=$this->CHART->render($type,$cd);	
		}
		return $panel;
	}
	private function formatData($type,$data,$set){	
		$color=issetCheck($data,'color',$set['color']);
		$icon=issetCheck($data,'icon',$set['icon']);
		$href=issetCheck($data,'href','#nogo');
		$caption=issetCheck($data,'caption','???');
		$content=issetCheck($data,'content','???');
		if($type==='chart'){
			$r=array(
				'count'=>(int)$content,
				'color'=>'bg-'.$color,
				'ref'=>$href,
				'title'=>$caption,
				'label'=>$caption
			);
			$this->CHART->ICON=$icon;
		}else{
			$but=array('color'=>$color,'icon'=>$icon,'href'=>$href);
			$cont=array('caption'=>$data['caption'],'content'=>$content);
			$r=array($but,$cont);
		}
		return $r;
	}
	
	private function setDashData(){
		$this->MODE=issetCheck($_GET,'dashmode',$this->MODE);
		if($this->DASH_DATA) return;
		$grades=$this->SLIM->Options->get('grades');
		$grades=reKeyArray($grades,'OptionValue');
		$gcolor=array('gray','aqua','blue','dark-blue','navy','orange','red-orange','olive','dark-green','purple','maroon','black');
		$parts=array(
			'General'=>array('get'=>false,'color'=>'light-blue','icon'=>false,'links'=>array()),
			'Active Members'=>array('get'=>'dojo_count_active','color'=>'navy','icon'=>'target','links'=>array()),
			'Member Count'=>array('get'=>'metrics_member_count','color'=>'blue','icon'=>'torsos','links'=>array()),
			'Gender Count'=>array('get'=>'metrics_gender_count','color'=>['purple','lavendar','dark-purple'],'icon'=>'torsos','links'=>array()),
			'Grade Count'=>array('get'=>'metrics_grade_count','color'=>'aqua','icon'=>'universal-access','links'=>array()),
		);
		foreach($parts as $i=>$v){
			$data=($v['get'])?$this->SLIM->Options->get($v['get']):false;
			$_data=[];
			if(!$data){
				switch($i){
					case 'General':
						if(issetCheck($this->USER['permissions'],'members')){
							$_data[]=array('caption'=>'Members','content'=>'Active','href'=>$this->PERMLINK.'member/list/active','icon'=>'torsos');
							$_data[]=array('caption'=>'Subscriptions','content'=>'Manage','href'=>$this->PERMLINK.'subscription','icon'=>'results-demographics');
							$_data[]=array('caption'=>'Forms','content'=>'Application','href'=>$this->PERMLINK.'appform','icon'=>'clipboard-pencil');
						}
						if(issetCheck($this->USER['permissions'],'events')){
							if($this->USER['access']>=25){
								$_data[]=array('caption'=>'Planner','content'=>'Events','href'=>$this->PERMLINK.'events/dashboard','icon'=>'calendar');
								$_data[]=array('caption'=>'List','content'=>'Events','href'=>$this->PERMLINK.'events/list/all','icon'=>'list-thumbnails');
								$_data[]=array('caption'=>'Sales','content'=>'Event','href'=>$this->PERMLINK.'sales','icon'=>'shopping-cart');
							}else{
								$_data[]=array('caption'=>'List','content'=>'Events','href'=>$this->PERMLINK.'events/list/active','icon'=>'list-thumbnails');
								$_data[]=array('caption'=>'Sales','content'=>'Event','href'=>$this->PERMLINK.'sales','icon'=>'shopping-cart');
							}
						}
						break;
				}
			}else{
				foreach($data as $_i=>$_v){
					switch($i){
						case 'Active Members':
							$caption=(!$_v['Dojo']||$_v['Dojo']==='')?'- No Dojo -':$_v['Dojo'];
							$_data[]=array('caption'=>$caption,'content'=>$_v['Members'],'color'=>$v['color'],'icon'=>$v['icon'],'href'=>$this->PERMLINK.'member/dojo/'.$_v['DojoID'].'/active');
							break;
						case 'Member Count':
							$caption='- not set -';
							$slug='nostatus';
							$color='blue';
							if($_v['Disable']==2){
								$caption='Pending';
								$slug='pending';
								$color='dark-blue';
							}else if($_v['Disable']==1){
								$caption='Inactive';
								$slug='inactive';
								$color='gray';
							}else if(is_numeric($_v['Disable']) && $_v['Disable']==0){
								$caption='Active';
								$slug='active';
								$color='olive';
							}
							$_data[]=array('caption'=>$caption,'content'=>$_v['Members'],'color'=>$color,'icon'=>$v['icon'],'href'=>$this->PERMLINK.'member/list/'.$slug);
							break;
						case 'Gender Count':
							$caption=issetCheck($_v,'Sex','- not set -');							
							if($caption==='0') $caption='- not set -';
							$color=$v['color'][$_i];
							$slug=($caption==='- not set -')?'nogender':strtolower($caption);		
							$_data[]=array('caption'=>$caption,'content'=>$_v['Genders'],'color'=>$color,'icon'=>$v['icon'],'href'=>$this->PERMLINK.'member/list/'.$slug.'/active');
							break;
						case 'Grade Count':
							$grade=issetCheck($grades,$_v['CurrentGrade']);
							$caption=($grade)?$grade['OptionName']:'ungraded';
							$slug=(!$_v['CurrentGrade'])?0:$grade['OptionValue'];
							$color=issetCheck($gcolor,$_v['CurrentGrade'],$v['color']);
							$_data[]=array('caption'=>$caption,'content'=>$_v['Grades'],'color'=>$color,'icon'=>$v['icon'],'href'=>$this->PERMLINK.'member/grades/'.$slug.'/active');
							break;
					}
				}
			}
			$v['links']=(!$_data)?array():$_data;
			$this->DASH_DATA[$i]=$v;
		}		
	}

}
