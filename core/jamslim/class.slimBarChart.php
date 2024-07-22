<?php

class slimBarChart{
	var $DATA;
	var $CHART;
	var $TYPE='vertical';
	var $BARS;
	var $AUTO_AXIS=true;
	var $BAR_LABELS=true;
	var $SHOW_LEGENDS=false;
	var $AXIS_STEPS=5;
	var $LEGENDS;
	var $TITLE;
	var $ICON;
	var $TOOLTIP=true;
	/*
		data sample;
		$data=array(
			'axis_max'=>30,
			'axis_step'=>5,
			'bars'=>array(
				0=>array('count'=>12,'color'=>'bg-purple','ref'=>1,'title'=>'this value','label'=>'test1'),
				1=>array('count'=>23,'color'=>'bg-red','ref'=>2,'title'=>'this is red','label'=>'testtest test2'),
				2=>array('count'=>8,'color'=>'bg-green','ref'=>2,'title'=>'great','label'=>'test 3'),
			),
		);
	*/
	var $CSS='<link rel="stylesheet" type="text/css" href="assets/css/barchart_v.css">';
	function __construct(){
		$this->DATA=array(
			'axis_max'=>30,
			'axis_step'=>5,
			'bars'=>array(
				0=>array('count'=>12,'color'=>'bg-purple','ref'=>1,'title'=>'this value','label'=>'test1'),
				1=>array('count'=>23,'color'=>'bg-red','ref'=>2,'title'=>'this is red','label'=>'testtest test2'),
				2=>array('count'=>8,'color'=>'bg-green','ref'=>2,'title'=>'great','label'=>'test 3'),
			),
		);
	}
	function render($what=false,$vars=false){
		if($vars) $this->set('DATA',$vars);
		if($this->DATA){
			switch($what){
				case 'horizontal':
					$this->TYPE='horizontal';
					$this->renderChart_h();
					break;
				default:
					$this->TYPE='vertical';
					$this->renderChart_v();
					break;
			}
		}else{
			$this->BARS=msgHandler('Sorry, not chart data found...',false,false);
			$this->renderChart_v();
		}
		return $this->CHART;		
	}
	function set($what=false,$vars=false){
		if($what && $vars){
			if(property_exists($this,$what)) $this->$what=$vars;
		}
	}
	private function renderBars_v(){
		$bar='';
		foreach($this->DATA['bars'] as $opt){
			if(is_array($opt)){
				$cnt=(int)$opt['count'];
				$div=($cnt>50)?100:10;
				$height=round(($cnt/$this->DATA['axis_max'])* 100);
				$height_class=($opt['count']<6)?'short-bar':'';
				$label=($this->BAR_LABELS)?'<div class="description">'.$opt['label'].'</div>':'';
				$tooltip=($this->TOOLTIP)?'data-tooltip':'';
				$bar.='<li class="bar '.$opt['color'].'  gotoME '.$height_class.'" style="height: '.$height.'%;" '.$tooltip.' title="'.$opt['title'].' - click to view" data-ref="'.$opt['ref'].'"><div class="percent">'.$cnt.'</div>'.$label.'</li>';
				$this->renderLegend($opt);
			}else{
				if($opt && $opt!=='') $bar.=$opt;
			}
		}

		$this->BARS=$bar;	
	}
	private function renderAxis_v(){
		$axis='';
		for($x=$this->DATA['axis_max'];$x>0;$x=$x-$this->DATA['axis_step']){
			$axis.='<div class="bar-chart-v-label">'.$x.'</div>';
		}
		if($axis) $axis='<li class="bar-chart-v-axis">'.$axis.'</li>';
		return $axis;
	}
	private function renderChart_v(){
		$this->setVars();
		$this->renderBars_v();
		if($this->TYPE==='buttons'){
			$chart='<div class="button-group stacked-for-small">'.$this->BARS.'</div>';
		}else{
			$chart='<ul class="bar-chart-v">'.$this->renderAxis_v();
			$chart.=$this->BARS;
			$chart.='</ul>';
		}
		if($this->TITLE){
			$icon=($this->ICON)?'<i class="fi-'.$this->ICON.'"></i> ':'';
			$this->TITLE='<h5>'.$icon.$this->TITLE.'</h5>';
		}
		if($this->SHOW_LEGENDS) $chart.='<dl class="bar-chart-legend">'.$this->LEGENDS.'</dl>';
		if($this->TYPE==='buttons'){
			$this->CHART='<div class="panel">'.$this->TITLE.$chart.'</div>';	
		}else{
			$this->CHART='<div class="bar-chart-wrapper">'.$this->TITLE.$chart.'</div>';	
		}
	}
	private function renderChart_h(){
		$this->setVars();
		$this->renderBars_v();
	}
	private function setVars(){
		$this->LEGENDS='';
		if($this->AUTO_AXIS){
			$chk=0;
			foreach($this->DATA['bars'] as $v){
				$v=(isset($v['count']))?(int)$v['count']:0;
				if($v>$chk)$chk=$v;
			}
			if($chk>0){
				$times=($chk>100)?100:10;
				$this->DATA['axis_max']=ceil((int)$chk / $times) * $times;//$chk;
				$this->DATA['axis_step']=round($this->DATA['axis_max']/$this->AXIS_STEPS);
			}
		}else{
			$max=(int)issetCheck($this->DATA,'axis_max');
			$step=(int)issetCheck($this->DATA,'axis_step');
			if(!$max) $this->DATA['axis_max']=50;
			if(!$step) $this->DATA['axis_step']=10;
		}
		$this->TYPE=($this->TITLE==='General')?$this->TYPE='buttons':$this->TYPE;			
	}
	private function renderLegend($rec){
		if($rec){
			$this->LEGENDS.='<dd class="'.$rec['color'].' text-white">'.$rec['label'].'</dd>';
		}
	}
}
