<?php

class jamTable{
	
	var $ZEBRA=true;//turn stripe class on and off
	var $STRIPE=false;//holds the last stripe
	var $TH=false;
	var $HEADERS=false;
	var $VTABLE=false;//for vertical tables
	var $CONTROL_WIDTH='small-1';
	var $AUTO_SORT=true;//add sorting flags to THEAD & values to TD if needed
	var $DATE_KEYS=array('itm_DAte','itm_Last','Date');// add timestamp sort data
	var	$NUMBER_KEYS=[// sorting keys for numbers
		'id'=>'int',
		'ref'=>'int',
		'gsort'=>'int',
		'qty.'=>'int',
		'itm_last'=>'int',
		'grade'=>'int',
		'edit'=>false,
		'controls'=>false,
		//'price'=>'float',
		//'paid'=>'float',
		//'balance'=>'float',
		//'value'=>'float',
		
	];
	var $SORT_DATA=[];//holds custom sorting data
	var $SORT_DATA_KEYS=[];//holds keys of the custom sorting data
	var $DATA;
	var $DEFAULTS;
	
	function __construct(){
	    //do something
	}
	
	function setVars($args){		
		$table['table']=array('id'=>false,'class'=>false,'html'=>false);
		$table['tr']=array('id'=>false,'class'=>false,'html'=>false);
		$table['th']=array('id'=>false,'class'=>false,'html'=>false);
		$table['td']=array('id'=>false,'class'=>false,'html'=>false);
		$table['headers']=false;
		$data=$sort_data=[];
		foreach($args as $i=>$v){
			if(isset($table[$i])) $table[$i]=$v;
			if($i==='data') $data=$v;
			if($i==='sort_data') $sort_data=$v;
			if($i==='headers') $this->HEADERS=$v;
			if($i==='control_width') $this->CONTROL_WIDTH=$v;
			if($i==='date_keys') $this->DATE_KEYS=$v;
		}
		if(!$this->HEADERS)$this->HEADERS=$table['headers'];
		$this->DATA=$data;
		$this->SORT_DATA=$sort_data;
		if($sort_data){
			$this->SORT_DATA_KEYS=array_keys($sort_data);
		}
		$this->DEFAULTS=$table;
		if($this->ZEBRA && !$this->STRIPE) $this->STRIPE='odd';
	}
	
	
	function Process($args){
		$this->setVars($args);
		if(!$this->DATA) return false;
		$tbl_args['content']=$this->buildRows();
		if($this->DEFAULTS['table']['class']) $tbl_args['class']=$this->DEFAULTS['table']['class'];
		if($this->DEFAULTS['table']['id']) $tbl_args['id']=$this->DEFAULTS['table']['id'];
		if($this->DEFAULTS['table']['html']) $tbl_args['html']=$this->DEFAULTS['table']['html'];
		return $this->renderTable($tbl_args);		
	}
	
	function buildRows(){
		$rows=[];
		foreach($this->DATA as $key=>$rec){
			$td=$th=false;
			$td_args=$this->DEFAULTS['td'];
			$th_args=$this->DEFAULTS['th'];
			if($this->VTABLE){
				$td_args['content']=(isset($rec['value']))?$rec['value']:$rec[1];
				$th_args['content']=(isset($rec['item']))?$rec['item']:$rec[0];
				$th_args['class']='pvLabel';
				$td_args['class']='pvValue';
				$td.=$this->renderTD($th_args).$this->renderTD($td_args);					
			}else{
				foreach($rec as $i=>$v){
					$td_args['content']=$v;
					$th_args['content']=$i;	
					if($i==='controls') $th_args['class'].=' '.$this->CONTROL_WIDTH;
					if(!$this->TH){
						$th.=$this->renderTH($th_args,$i);
					}
					$td.=$this->renderTD($td_args,$i,$key);	
				}
				if(!$this->TH) $this->TH=$th;
			}
			$tr_args['content']=$td;
			$tr_args['class']=$this->STRIPE;	
			$rows[]=$this->renderTR($tr_args);
			if($this->ZEBRA) $this->STRIPE=($this->STRIPE=='odd')?'even':'odd';
		}
		return implode("\n",$rows);
	}
	
	function getSortType($txt=false){
		//set the sorting data type based on TH text
		//for 'stupid table sorter'
		//only flag integers, floats and none. dates are handled seperatley
		if(!$txt) return '';
		$txt=strtolower($txt);
		$type=issetcheck($this->NUMBER_KEYS,$txt,'string-ins');
		if($type) return 'data-sort="'.$type.'"';
		return '';
	}
	function getSortValue($idx,$key){
		$o='';
		$data=issetCheck($this->SORT_DATA,$key);
		if($data){
			$o=issetCheck($data,$idx,'');
		}
		return $o;
	}
	
	function renderTable($args=[]){
		$params=[];$content=false;
		foreach($args as $i=>$v){
			switch($i){
				case 'id':
					$params[]='id="'.$v.'"';
					break;
				case 'class':
					$params[]='class="'.$v.'"';
					break;
				case 'html':
					$params[]=$v;
					break;
				case 'content':
					$content=$v;
					break;
			}
		}
	    $headers=$this->renderHeaders();
	    $pram=($params)?$params=implode(' ',$params):'';
		return '<table '.$pram.'>'.$headers.$content.'</table>';
	}
	function renderTD($args=[],$key=false,$ref=false){
		$params=[];$content=false;
		foreach($args as $i=>$v){
			switch($i){
				case 'id':
					if($v && $v!=='') $params[]='id="'.$v.'"';
					break;
				case 'class':
					if($v && $v!=='') $params[]='class="'.$v.'"';
					break;
				case 'html':
					if($this->AUTO_SORT){
						if(in_array($key,$this->DATE_KEYS)){
							//convert date into time for sorting: may need to check format
							$v.=' data-sort-value="'.strtotime($args['content']).'"';
						}else if(in_array($key,$this->SORT_DATA_KEYS)){							
							$val=$this->getSortValue($ref,$key);
							if($val!==''){
								$v.=' data-sort-value="'.$val.'"';
							}
						}
					}
					if($v && $v!=='') $params[]=$v;
					break;
				case 'content':
					$content=$v;
					break;
			}
		}
		$pram=($params)?$params=implode(' ',$params):'';
		return '<td '.$pram.'>'.$content.'</td>';
	}
	private function addDateKey_if($k=''){
		$t=(strpos(strtolower($k),'date')!==false)?true:false;
		if($t && !issetCheck($this->DATE_KEYS,$k)){
			$this->DATE_KEYS[]=$k;
			return true;
		}
		return false;
	}
	function renderTH($args=false,$key=false){
		$params=[];$content=false;
		if($this->HEADERS && $key && $this->HEADERS[$key]){
			//get the header settings if any
			$args=$this->HEADERS[$key];
			if(!$args['content']||$args['content']=='') $args['content']=$key;
		}
		if($this->AUTO_SORT){
			$this->addDateKey_if($key);
			$sort=$this->getSortType($key);
			if($sort){
				$t=$args['html'];
				$args['html']=$t.' '.$sort;
			}				
		}
		foreach($args as $i=>$v){
			switch($i){
				case 'id':
					if($v && $v!=='') $params[]='id="'.$v.'"';
					break;
				case 'class':
					if($v && $v!=='') $params[]='class="'.$v.'"';
					break;
				case 'html':
					if($v && $v!=='') $params[]=$v;
					break;
				case 'content':
					$content=ucME($v);
					break;
			}
		}
		$pram=($params)?$params=implode(' ',$params):'';
		return '<th '.$pram.'>'.$content.'</th>';
	}
	
	function renderTR($args=[]){
		$params=[];$content=false;
		foreach($args as $i=>$v){
			switch($i){
				case 'id':
					$params[]='id="'.$v.'"';
					break;
				case 'class':
					$params[]='class="'.$v.'"';
					break;
				case 'html':
					$params[]=$v;
					break;
				case 'content':
					$content=$v;
					break;
			}
		}
		$pram=($params)?$params=implode(' ',$params):'';
		return '<tr '.$pram.'>'.$content.'</tr>';
	}
	
	function renderHeaders(){
		$headers=false;
		if($this->TH){
			$headers= '<thead>'.$this->TH.'</thead><tfoot>'.$this->TH.'</tfoot>';
		}
		return $headers;
	}	
}
/*
function testJamTable(){
	$generateRandomString = function($length = 10) {
    	return substr(str_shuffle("0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ"), 0, $length);
	};
	for($x=0;$x<20;$x++){
		$data[]=array('id'=>$x,'test1'=>$generateRandomString(),'test2'=>$generateRandomString(),'test3'=>$generateRandomString(),'test4'=>$generateRandomString(),'test5'=>$generateRandomString(),'controls'=>'<button>test</button>');
	}
	$args['data']=$data;
	$args['table']=array('class'=>'dataTable');

	$TBL=new jamTable;
	echo $TBL->Process($args);
}
testJamTable();
*/
