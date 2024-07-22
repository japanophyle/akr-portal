<?php

class colors{
	private $COLORS;
	private $GROUPS=array('whites','yellows','oranges','blues','purples','reds','blacks','site');
	private $DEFAULT_REC=array('class'=>false,'hex'=>false,'group'=>false);
	var $MINIFY=true;
	
	function __construct(){
		$this->init();
	}
	
	function get($what=false,$vars=false){
		switch($what){
			case 'id':
				return issetCheck($this->COLORS,$vars);
				break;
			case 'class': case 'code': case 'group':
				return $this->getColorBy($what,$vars);
				break;
			case 'list':
				return $this->renderList($vars);
				break;
			case 'picker':
				return $this->renderColorPicker($vars);
				break;
			case 'css':
				return $this->renderCSS();
				break;
			case 'alt_color':
				return $this->getAltColor($vars);
				break;
			case 'default_rec':
				return $this->DEFAULT_REC;
			case 'groups':
				return $this->GROUPS;
				break;
		}
		return false;
	}
	function set($what=false,$vars=false){
		switch($what){
		
		}
	}
	private function getColorBy($what=false,$val=false){
		$fld=$out=false;
		switch($what){
			case 'class': $fld='class';break;
			case 'code': $fld='hex';break;
			case 'group': $fld='group';break;
		}
		if($fld){
			foreach($this->COLORS as $i=>$v){
				if($val===$v[$fld]){
					if($what==='group'){
						$out[$i]=$v;
					}else{
						return $v;
					}
				}			
			}
		}
		return $out;
	}
	private function renderList($type=false){
		if(!$type||$type===''){
			$val='id';
			$text='class';
		}else if($type==='all'){
			$val='class';
			$text='class';
		}else{
			$tmp=explode('_',$type);
			$val=issetCheck($tmp,0,'id');
			$text=issetCheck($tmp,1,'class');
		}
		$out=array();
		foreach($this->COLORS as $i=>$v){
			if(!isset($out[$v['group']]))$out[$v['group']]=array();
			$k=($val==='id')?$i:$v[$val];
			$out[$k]=$v[$text];
		}
		return $out;		
	}
	private function renderColorPicker($args=[]){
		$selected=$id=$ajax=$closer=$_pallet=false;
		extract($args);
		$pallet=array();
		foreach($this->COLORS as $i=>$v){
			if(!isset($pallet[$v['group']])) $pallet[$v['group']]=array();
			$sel=($v['class']===$selected)?'checked':'';
			$alt_color=$this->getAltColor($v['hex']);
			$real_class=$v['class'];
			if($v['group']==='site'){
				$real_class=$this->realSiteClassName($v['class']);
			}
			$pallet[$v['group']][]='<div class="pickbox text-'.$alt_color.'" title="'.$v['class'].'"><input class="pickval" type="radio" id="color-'.$v['class'].'" name="pickval" value="'.$v['class'].'" '.$sel.'><label class="bg-'.$real_class.'" for="color-'.$v['class'].'"></label></div>';
		}
		if($pallet){
			foreach($pallet as $g) $_pallet.=implode('',$g);
		}
		$html='<form id="slimPicker" data-inp="#clr_'.$id.'">'.$_pallet.'<button class="button button-olive expanded" id="pick"><i class="fi-check"></i> Select</button></form>';
        $html = renderCard_active('Color Picker',$html,$closer);
        if($ajax){
			$html.='<script>initSlimPicker();</script>';
		}
		return $html;		
	}
	private function realSiteClassName($str=false){
		switch($str){
			case 'site-main':
				$cls='gbm-blue';
				break;
			case 'site-dark':
				$cls='gbm-dark-blue';
				break;				
			default:
				$cls=$str;
		}
		return $cls;
	}
	private function renderColorPicker_JS($args=[]){
		$selected=$id=$ajax=$closer=$_pallet=false;
		extract($args);
		$pallet=array();
		foreach($this->COLORS as $i=>$v){
			if(!isset($pallet[$v['group']])) $pallet[$v['group']]=array();
			$sel=($v['class']===$selected)?'checked':'';
			$alt_color=$this->getAltColor($v['hex']);
			$pallet[$v['group']][]='<div class="pickbox text-'.$alt_color.'" title="'.$v['class'].'"><input class="pickval" type="radio" id="color-'.$v['class'].'" name="pickval" value="'.$v['class'].'" '.$sel.'><label class="bg-'.$v['class'].'" for="color-'.$v['class'].'"></label></div>';
		}
		if($pallet){
			foreach($pallet as $g) $_pallet.=implode('',$g);
		}
		$html='<form id="slimPicker" data-inp="#clr_'.$id.'">'.$_pallet.'<button class="button button-olive expanded" id="pick"><i class="fi-check"></i> Select</button></form>';
        $html = renderCard_active('Color Picker',$html,$closer);
        if($ajax){
			$html.='<script>initSlimPicker();</script>';
		}
		return $html;		
	}
	private function renderCSS(){
		$blocks=array(
			'bg'=>array('.bg-[::class::] { background-color: [::hex::] !important;  }'),
			'text'=>array('.text-[::class::] { color: [::hex::] !important;  }'),
			'button'=>array(
				'.button-[::class::] {background-color: [::hex::] !important; opacity:0.8; cursor:pointer; transition: opacity .25s ease-out;  }',
				'.button-[::class::]:hover {opacity:1;}'
			),
			'link'=>array(
				'.link-[::class::] {color: [::hex::] !important; opacity:0.8; cursor:pointer; transition: opacity .25s ease-out;  }',
				'.link-[::class::]:hover {opacity:1;}'
			),
			'border'=>array('.border-[::class::] { border-color: [::hex::] !important;  }')
		);
		$css=array();
		foreach($this->COLORS as $v){
			foreach($blocks as $block){
				foreach($block as $tpl){
					$tmp=str_replace('[::class::]',$v['class'],$tpl);
					$tmp=str_replace('[::hex::]',$v['hex'],$tmp);
					$css[]=$tmp;
				}
			}
		}
		if($css){
			$glue=($this->MINIFY)?'':"\n";
			$out=implode($glue,$css);
			if($this->MINIFY)$out=str_replace(' ','',$out);
		}
		return $out;
	}

	private function init(){
		$colors=array(			
			0=>array('class'=>'black','hex'=>'#222A38','group'=>'blacks'),
			1=>array('class'=>'black-solid','hex'=>'#000000','group'=>'blacks'),
			2=>array('class'=>'amber','hex'=>'#ffbf00','group'=>'yellows'),
			3=>array('class'=>'amber-opaque','hex'=>'rgba(255,191,0,0.3)','group'=>'yellows'),
			4=>array('class'=>'aqua','hex'=>'#0ABAEF','group'=>'blues'),
			5=>array('class'=>'blue','hex'=>'#7398ff','group'=>'blues'),
			6=>array('class'=>'dark-blue','hex'=>'#0F5593','group'=>'blues'),
			7=>array('class'=>'dark-blue-opaque','hex'=>'rgba(15,85,147,0.3)','group'=>'blues'),
			8=>array('class'=>'dark-green','hex'=>'#008000','group'=>'greens'),
			9=>array('class'=>'dark-green-opaque','hex'=>'rgba(0,128,0,0.3)','group'=>'greens'),
			10=>array('class'=>'fuchsia','hex'=>'#f012be','group'=>'reds'),
			11=>array('class'=>'gray','hex'=>'#777777','group'=>'blacks'),
			12=>array('class'=>'gray-blue','hex'=>'#EEF1F7','group'=>'blues'),
			13=>array('class'=>'green','hex'=>'#92CD18','group'=>'greens'),
			14=>array('class'=>'lavendar','hex'=>'#B57EDC','group'=>'purples'),
			15=>array('class'=>'light-blue','hex'=>'#3c8dbc','group'=>'blues'),
			16=>array('class'=>'light-gray','hex'=>'#BFBFBF','group'=>'blacks'),
			17=>array('class'=>'light-green','hex'=>'#45B6B0','group'=>'greens'),
			18=>array('class'=>'light-purple','hex'=>'#BF00FF','group'=>'purples'),
			19=>array('class'=>'lime','hex'=>'#01ff70','group'=>'greens'),
			20=>array('class'=>'maroon','hex'=>'#85144b','group'=>'reds'),
			21=>array('class'=>'navy','hex'=>'#001f3f','group'=>'blues'),
			22=>array('class'=>'olive','hex'=>'#3d9970','group'=>'greens'),
			23=>array('class'=>'orange','hex'=>'#FF884D','group'=>'oranges'),
			24=>array('class'=>'purple','hex'=>'#932ab6','group'=>'purples'),
			25=>array('class'=>'red','hex'=>'#F20556','group'=>'reds'),
			26=>array('class'=>'red-orange','hex'=>'#e83922','group'=>'reds'),
			27=>array('class'=>'teal','hex'=>'#B5AB8A','group'=>'yellows'),
			28=>array('class'=>'yellow','hex'=>'#f39c12','group'=>'yellows'),
			29=>array('class'=>'white','hex'=>'#ffffff','group'=>'whites'),
			30=>array('class'=>'dark-purple','hex'=>'#5416B7','group'=>'purples'),

			31=>array('class'=>'dark-red','hex'=>'#8B0000','group'=>'reds'),
			32=>array('class'=>'brick-red','hex'=>'#B22222','group'=>'reds'),
			33=>array('class'=>'crimson','hex'=>'#DC143C','group'=>'reds'),
			34=>array('class'=>'salmon','hex'=>'#FA8072','group'=>'reds'),
			35=>array('class'=>'light-salmon','hex'=>'#FFA07A','group'=>'reds'),
			
			36=>array('class'=>'dark-orange','hex'=>'#FF8C00','group'=>'oranges'),
			37=>array('class'=>'light-orange','hex'=>'#FFA500','group'=>'oranges'),
			38=>array('class'=>'coral','hex'=>'#FF7F50','group'=>'oranges'),
			39=>array('class'=>'tangerine','hex'=>'#FFE4B5','group'=>'oranges'),

			40=>array('class'=>'lemon','hex'=>'#FFFF00','group'=>'yellows'),
			41=>array('class'=>'light-yellow','hex'=>'#FFFFE0','group'=>'yellows'),
			42=>array('class'=>'light-gold','hex'=>'#FAFAD2','group'=>'yellows'),
			43=>array('class'=>'gold','hex'=>'#FFD700','group'=>'yellows'),
			44=>array('class'=>'khaki','hex'=>'#F0E68C','group'=>'yellows'),
			
			45=>array('class'=>'grass','hex'=>'#228B22','group'=>'greens'),
			46=>array('class'=>'sea-green','hex'=>'#2E8B57','group'=>'greens'),
			47=>array('class'=>'dark-olive','hex'=>'#6B8E23','group'=>'greens'),
			48=>array('class'=>'dark-lime','hex'=>'#32CD32','group'=>'greens'),
			49=>array('class'=>'light-olive','hex'=>'#9ACD32','group'=>'greens'),
			
			50=>array('class'=>'royal-blue','hex'=>'#4169E1','group'=>'blues'),
			51=>array('class'=>'steel-blue','hex'=>'#4682B4','group'=>'blues'),
			52=>array('class'=>'sky-blue','hex'=>'#87CEEB','group'=>'blues'),
			53=>array('class'=>'deep-sky','hex'=>'#00BFFF','group'=>'blues'),
			54=>array('class'=>'powder-blue','hex'=>'#B0E0E6','group'=>'blues'),
			/*
			55=>array('class'=>'plum','hex'=>'#DDA0DD','group'=>'purples'),
			56=>array('class'=>'orchid','hex'=>'#DA70D6','group'=>'purples'),
			57=>array('class'=>'dark-orchid','hex'=>'#BA55D3','group'=>'purples'),
			58=>array('class'=>'indigo','hex'=>'#4B0082','group'=>'purples'),
			59=>array('class'=>'violet','hex'=>'#8A2BE2','group'=>'purples'),
			*/
			60=>array('class'=>'beige','hex'=>'#F5F5DC','group'=>'whites'),
			61=>array('class'=>'snow','hex'=>'#FFFAFA','group'=>'whites'),
			62=>array('class'=>'ghost','hex'=>'#F8F8FF','group'=>'whites'),
			63=>array('class'=>'smoke','hex'=>'#F5F5F5','group'=>'whites'),
			64=>array('class'=>'floral-white','hex'=>'#FFFAF0','group'=>'whites'),
			65=>array('class'=>'ivory','hex'=>'#FFFFF0','group'=>'whites'),	
			
			70=>array('class'=>'site-main','hex'=>'#d564cc','group'=>'site'),
			71=>array('class'=>'site-dark','hex'=>'#A52299','group'=>'site'),

		);
		
		$out=$done=array();
		foreach($colors as $i=>$v){
			if(!isset($out[$v['group']]))$out[$v['group']]=array();
			$out[$v['group']][$i]=$v;
		}
		foreach($out as $group=>$cols){
			$tmp=$this->sortColors($cols,'hex');
			$done+=$tmp;
		}
		$this->COLORS=$done;
	}
	private function sortColors($data=false,$key=false,$reverse=false){
		if(is_array($data) && $key){
			$sort=$tmp=array();
			$dir=($reverse)?SORT_DESC:SORT_ASC;
			foreach($data as $i=>$v) {
				$sort[$i]=$v[$key];
				$tmp[$i]=$v;
				$tmp[$i]['tmp_id']=$i;
			}
			array_multisort($sort,$dir,$tmp);
			$tmp=rekeyArray($tmp,'tmp_id');
			return $tmp;
		}
		return $data;
	}
	
	private function getAltColor($hex=false){
		if(is_string($hex) && strlen($hex)==7){
			list($red, $green, $blue) = sscanf($hex, "#%02x%02x%02x");
			$luma = ($red + $green + $blue)/3;
			return ($luma < 128)?'white':'black';
		}
		return 'gray';
	}
}
