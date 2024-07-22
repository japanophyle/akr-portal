<?php

class slimLanguage_dates{
	var $SLIM;
	var $LANG='en';
	var $DAYS=array(
		'en'=>'s,m,t,w,t,f,s',
		'de'=>'s,m,d,m,d,f,s',
		'fr'=>'d,l,m,m,j,v,s'
	);
	var $DAYNAMES=array(
		'en'=>'sunday,monday,tuesday,wednesday,thursday,friday,saturday',
		'de'=>'sonntag,montag,dienstag,mittwoch,donnerstag,freitag,samstag',
		'fr'=>'dimanche,lundi,mardi,mercredi,jeudi,vendredi,samedi'
	);
	var $DAYNAMES3=array(
		'en'=>'sun,mon,tue,wed,thu,fri,sat',
		'de'=>'so,mo,di,mi,do,fr,sa',
		'fr'=>'dim,luni,mar,mer,jeu,ven,sam'
	);
	var $MONTHNAMES=array(
		'en'=>'january,february,march,april,may,june,july,august,september,october,november,december',
		'de'=>'januar,februar,märz,april,mai,juni,juli,august,september,oktober,november,dezember',
		'fr'=>'janvier,février,mars,avril,mai,juin,juillet,août,septembre,octobre,novembre,décembre'
	);
	var $MONTHS3=array(
		'en'=>'jan,feb,mar,apr,may,jun,jul,aug,sep,oct,nov,dec',
		'de'=>'jan,feb,mar,apr,mai,jun,jul,aug,sep,okt,nov,dez',
		'fr'=>'jan,fév,mar,avr,mai,jui,jul,aoû,sep,oct,nov,déc'
	);
	
	var $FORMATS=array(
		'dy'=>'DAYS',
		'd3'=>'DAYNAMES3',
		'dt'=>'date',
		'dn'=>'DAYNAMES',
		'mn'=>'MONTHNAMES',
		'm3'=>'MONTHS3',
		'y'=>'year'
	);
	function __construct($slim){
		if(!$slim) die(__METHOD__.': no slim object supplied.');
		$this->SLIM=$slim;
		$this->LANG=$slim->language->get('_LANG');
	}
	function langDate($date=false,$format='d3 dt m3 y',$join=' '){
		$metrics=$this->dateMetrics($date);
		$str=[];	
		if($metrics){
			$parts=explode(' ',$format);
			foreach($parts as $p){
				$f=issetCheck($this->FORMATS,$p);
				if($f==='year'){
					$str[]=$metrics[$f];
				}else if($f==='date'){
					$str[]=$metrics['dt'];
				}else{
					$tmps=$this->$f;
					$tmp=explode(',',$tmps[$this->LANG]);
					$k=$metrics[$p];
					$val=$tmp[$k];
					if($p==='d3') $val.=',';
					if($this->LANG!=='fr') $val=ucwords($val);
					$str[]=$val;
				}
			}
			if($str) $str=implode($join,$str);
		}
		return $str;	
	}
	
	function dateMetrics($date=false){
		if($date){
			$time=(is_numeric($date))?$date:strtotime($date);
			$metrics=array(
				'time'=>$time,
				'monthday'=>date('j',$time),
				'day'=>date('D',$time),
				'daynumber'=>date('N',$time),
				'monthnumber'=>date('n',$time),
				'year'=>date('Y',$time),
			);
			$metrics['d3']=($metrics['daynumber']==7)?0:$metrics['daynumber'];
			$metrics['dn']=$metrics['daynumber'];
			$metrics['dt']=($metrics['monthday']);
			$metrics['m3']=($metrics['monthnumber']-1);
			$metrics['mn']=($metrics['monthnumber']-1);
			$metrics['y']=$metrics['year'];
			return $metrics;
		}
		return false;
	}
	
}
