<?php

class shortcode_google_practice_calendar extends slimShortCoder{
	function __construct($slim=false){
		if(!$slim) throw new Exception(__METHOD__.': no slim object!!');
		parent::__construct($slim);
	}
	
	//required function
	function getReplace($args=false){
		$app= new slimSignupPublic($this->SLIM);
		$content['cnt']=$this->renderBlock();
		$content['script']=false;
		$content['jqd']=false;
		$content['js']=false;
		$content['rp'] = $content['cnt'];
		$content['find'] = '<p>' . $this->FIND . '</p>';
		return $content;
	}	
	private function renderBlock(){
		$cal=$this->renderCalendar();
		$loc=$this->renderLocations();
		return '<div class="grid-x grid-padding-x block"><div class="cell medium-4">'.$loc.'</div><div class="cell medium-8">'.$cal.'</div></div>';
	}
	private function renderCalendar(){
		$src='https://www.google.com/calendar/embed?deb=-&amp;embed_style=WyJhdDplbWI6c3QiLCIjZTBlMGUwIiwiI2VkZWRlZCIsIiM0MTg0ZjMiLCJyb2JvdG8iLCIjNjM2MzYzIiw1MDAsIiNmZmYiXQo&amp;eopt=0&amp;mode=month&amp;showCalendars=1&amp;showPrint=0&amp;showTz=0&amp;src=mnkyudo.org_oc8sv6hsb4o8nrm1lshr1pprhc@group.calendar.google.com';
		return '<iframe style="height:100%; width:100%;" sandbox="allow-scripts allow-popups allow-forms allow-same-origin allow-popups-to-escape-sandbox allow-downloads allow-modals allow-storage-access-by-user-activation" frameborder="0" aria-label="Calendar, Practice Calendar" src="'.$src.'" allowfullscreen=""></iframe>';
	}
	
	private function renderLocations(){
		$locs=[
			1=>['name'=>'Robinsdale','aria'=>'Map, Sandburg Middle School','url'=>'https://maps-api-ssl.google.com/maps?hl=en-US&amp;ll=45.004553,-93.366474&amp;output=embed&amp;q=2400+Sandburg+Ln,+Golden+Valley,+MN+55427,+United+States+(Sandburg+Middle+School)&amp;z=16'],
			2=>['name'=>'St. Louis Park','aria'=>'Map, 6300 Walker St','url'=>'https://maps-api-ssl.google.com/maps?hl=en-US&ll=44.939248,-93.359159&output=embed&q=6300+Walker+St,+Minneapolis,+MN+55416,+USA+(6300+Walker+St)&z=16'],
			3=>['name'=>'Northfield','aria'=>'Map, Northfield High School','url'=>'https://maps-api-ssl.google.com/maps?hl=en-US&ll=44.444717,-93.16357&output=embed&q=1400+Division+St+S,+Northfield,+MN+55057,+United+States+(Northfield+High+School)&z=16'],
		];
		$out='';
		foreach($locs as $i=>$v){
		     $out.='<h3>'.$v['name'].'</h3><iframe style="width:100%" sandbox="allow-scripts allow-popups allow-forms allow-same-origin allow-popups-to-escape-sandbox allow-downloads allow-modals allow-storage-access-by-user-activation" frameborder="0" aria-label="'.$v['aria'].'" src="'.$v['url'].'" allowfullscreen=""></iframe>';
		}
		return $out;
	}

}



