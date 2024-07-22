<?php

class shortcode_events_calendar extends slimShortCoder{
	private $ROUTE;
	private $URL_REQUEST=array('mn'=>false,'yr'=>false,'vw'=>false);
	private $DATE;
	function __construct($slim=null){
		if(!$slim) throw new Exception(__METHOD__.': no slim object!!');
		parent::__construct($slim);
		$this->ROUTE=$slim->router->get('route');
	}
	
	//required function
	public function getReplace($args){
		$html = $this->renderContents($args);
		if($this->AJAX){
			if(is_array($html)){
				echo renderCard_active($html['title'],$html['content'],$this->SLIM->closer);
			}else{
				echo renderCard_active('Events on: <span class="text-white">'.$this->DATE.'</span>',$html,$this->SLIM->closer);
			}
			die;
		}
		$out['rp']=$out['cnt']=$html;
		$out['js'] = false;
		$out['find'] = '<p>' . $this->FIND . '</p>';
		return $out;
	}
	
	private function renderContents($o=false){
		$R2 = issetCheck($this->ROUTE,2);
		if($this->AJAX && is_numeric($R2)){//render event
			$EVENT=new event_item($this->SLIM);
			$html=$EVENT->get('content',(int)$R2);
		}else{
			$CAL = new event_calendar($this->SLIM);
			$CAL->ADD_CONTROLS=true;
			$CAL->CALENDAR_SIZE='large';
			if($R2==='list'){
				$view='day_cards';
				$str_date=issetCheck($_GET,'date',date('Y-m-d'));
			}else{
				$str_date=$this->setDateString();
				$view=issetCheck($this->URL_REQUEST,'vw','calendar1');
				switch($view){
					case 'list': case 'cards':
						$view='event_'.$view;
						break;
					case 'calendar':
						$view='calendar1';
						break;
					default:
						$view=$CAL->DEFAULT_VIEW;
						if($view==='calendar'){
							$view='calendar1';
						}else{
							$view='event_'.$view;
						}
				}
			}
			$this->DATE=date('l jS, F Y',strtotime($str_date));
			$html=$CAL->render($view,$str_date);
			if($this->AJAX) $html='<div class="tabs-content" style="max-height:25rem;">'.$html.'</div>';
		}
		return $html;
	}
	
	private function setDateString(){		
		$keys=array_keys($this->URL_REQUEST);
		$req=false;
		foreach($keys as $k){
			$t=issetCheck($_GET,$k);
			$this->URL_REQUEST[$k]=$t;
			if($t) $req[$k]=$t;
		}
		$date['y']=issetCheck($req,'yr',date('Y'));
		$date['m']=issetCheck($req,'mn',date('m'));
		$date['d']=($date['y']==date('Y') && (int)$date['m']==(int)date('m'))?date('d'):'01';
		return implode('-',$date);
	}

}
