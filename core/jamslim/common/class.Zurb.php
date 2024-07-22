<?php

class Zurb {
	public $OUTPUT;
	private $PARTS;
	function __construct(){
	
	}
	
	function card($title=false,$content=false,$image=false){
		$card=false;
		if($title){
			if(is_array($title)){
				$title=HTML::tag($title['tag'], $title['text'], $title['atts']);
			}
			$card['header']=HTML_utils::div($title,array('class'=>'card-divider'));
		}
		if($image){
			if(is_array($image)){
				$card['image']=HTML_utils::img($image['src'],$image['atts']);
			}else{
				$card['image']=HTML_utils::img($image);
			}
		}
		if($content){
			if(is_array($content)){
				$content=HTML::tag($content['tag'],$content['text'],$content['atts']);
			}
			$card['section']=HTML_utils::div($content,array('class'=>'card-section'));
		}
		if($card){
			$card=implode("\n",$card);
			$card=HTML_utils::div($card,array('class'=>'card'));
		}
		return $card;
	}
	
	function callout($title=false,$content=false,$atts=false,$close=false){
		$callout=false;
		$class=issetCheck($atts,'class');
		$atts['class']='callout '.$class;
		if($title){
			if(is_array($title)){
				$title=HTML::tag($title['tag'], $title['text'], $title['atts']);
			}
			$callout['header']=$title;
		}
		if($content){
			if(is_array($content)){
				$content=HTML::tag($content['tag'],$content['text'],$content['atts']);
			}
			$callout['body']=$content;
		}
		if($close){
			$callout['close']='<button class="close-button" aria-label="Dismiss alert" type="button" data-close><span aria-hidden="true">&times;</span></button>';
			$atts['data-closable']='true';
		}
		if($callout){
			$callout=implode("\n",$callout);
			$callout=HTML_utils::div($callout,$atts);
		}
		return $callout;
	}
	
	function mediaObject($title=false,$content=false,$image=false,$atts=false){
		$mob=false;
		$class=issetCheck($atts,'class');
		$atts['class']='media-object '.$class;
		if($title){
			if(is_array($title)){
				$title=HTML::tag($title['tag'], $title['text'], $title['atts']);
			}
			$mob['header']=$title;
		}
		if($image){
			if(is_array($image)){
				$image=HTML_utils::img($image['src'],$image['atts']);
			}else{
				$image=HTML_utils::img($image);
			}
			$image=HTML_utils::div($image,array('class'=>'thumbnail'));
			$mob['image']=HTML_utils::div($image,array('class'=>'media-object-section'));
		}
		if($content){
			if(is_array($content)){
				$content=HTML::tag($content['tag'],$content['text'],$content['atts']);
			}
			$mob['body']=HTML_utils::div($content,array('class'=>'media-object-section'));
		}
		if($mob){
			$mob=implode("\n",$mob);
			$mob=HTML_utils::div($mob,$atts);
		}
		return $mob;
	}
	
	function inlineLabel($label=false,$input=false,$button=false,$pos=false){
		$label=HTML::tag('span',$label,array('class'=>'input-group-label'));
		if($button){//should be html
			$button=HTML::tag('span',$button,array('class'=>'input-group-button'));
		}		
		if($pos==='right'){
			$out=$button.$input.$label;
		}else{
			$out=$label.$input.$button;
		}
		$out=HTML_utils::div($out,array('class'=>'input-group'));
		return $out;
	}
	function inlineButton($button=false,$input=false,$pos=false){
		if($button){//should be html
			$button=HTML::tag('span',$button,array('class'=>'input-group-button'));
		}		
		if($pos==='right'){
			$out=$button.$input;
		}else{
			$out=$input.$button;
		}
		$out=HTML_utils::div($out,array('class'=>'input-group'));
		return $out;
	}
	
	function iconButton($button=false,$contents=false){
		$caption=$icon=$content=false;
		if($icon=issetCheck($button,'icon')) $icon='<i class="icon-x3 icon-block fi-'.$icon.'"></i>';
		if($color=issetCheck($button,'color')) $color='  button-'.$color;
		if($caption=issetCheck($contents,'caption')) $caption='<span class="caption">'.$caption.'</span>';
		if($content=issetCheck($contents,'content')) $content='<span class="text">'.$content.'</span>';
		$load=(issetCheck($button,'load'))?'loadME':'gotoME';
		$out='<div class="grid-x icon-button wide '.$load.$color.'" data-ref="'.issetCheck($button,'href').'">';
		$out.='<div class="cell shrink">'.$icon.'</div>';
		$out.='<div class="cell auto">'.$caption.$content.'</div>';
		$out.='</div>';
		return $out;
	}
	
	function adminButton($button=false){
		$load=(issetCheck($button,'load'))?'loadME':'gotoME';
		if($caption=issetCheck($button,'caption')) $caption='<span class="caption">'.$caption.'</span>';
		$title=issetCheck($button,'title',false);
		if($title){
			$title='data-tooltip data-position="bottom" data-alignment="center" title="'.$title.'"';
		}
		if(isset($button['icon'])){
			$icon=$button['icon'];
			$color=issetCheck($button,'color','navy');
		    $color=' link-'.$color;
			if(is_numeric($icon)){
				$icon='<span class="icon-x3 icon-block '.$color.'">'.$icon.'</span>';
			}else{
				$icon='<i class="icon-x3 icon-block fi-'.$icon.$color.'"></i>';
			}
		}
		$out='<div '.$title.' class="button hollow secondary '.$load.'" data-ref="'.issetCheck($button,'href').'">';
		$out.='<div class="cell shrink">'.$icon.'</div>';
		$out.='<div class="cell auto text-navy">'.$caption.'</div>';
		$out.='</div>';
		return $out;
	}
	
	function switch_checkbox($name=false,$value=false,$checked=false,$title=false,$id=false){
		$switch=null;
		if(!$id) $id=HTML::random_id();
		if($name){
			$atts=array('value'=>$value,'id'=>$id,'type'=>'checkbox','class'=>'switch-input');
			if($checked) $atts['checked']='true';
			$switch['input']=HTML_utils::input_checkbox($name,$atts);
			$switch['label']=HTML_utils::label($id,array('class'=>'switch-paddle','content'=>'<span class="show-for-sr">'.$title.'</span>'));
			$switch=implode("\n",$switch);
			return HTML_utils::div($switch,array('class'=>'switch'));
		}else{
			$switch=msgHandler('Sorry, invalid values supplied in '.__METHOD__);
		}
		return $switch;
	}
	function switch_radio($name=false,$args=[]){
		$out=false;		
		if($name && $args){
			foreach($args as $i=>$v){
				$switch=null;
				$value=$checked=$title=$id=false;
				extract($v);
				if(!$id) $id=HTML::random_id();				
				$atts=array('value'=>$value,'id'=>$id,'class'=>'switch-input');
				if($checked) $atts['checked']='true';
				$switch['input']=HTML_utils::input_radio($name,$atts);
				$switch['label']=HTML_utils::label($id,array('class'=>'switch-paddle','content'=>'<span class="show-for-sr">'.$title.'</span>'));
				$switch=implode("\n",$switch);
				$out.=HTML_utils::div($switch,array('class'=>'switch'));
			}
		}else{
			$out=msgHandler('Sorry, invalid values supplied in '.__METHOD__);
		}
		return $out;
	}
	function search($name='findME',$url=false,$b_color=false){
		$input=HTML_utils::input_text($name,array('class'=>'input-group-field','placeholder'=>'Find members by name or email'));
		$button=HTML_utils::button('<i class="fi-magnifying-glass"></i>',array('type'=>'submit','class'=>'button '.$b_color));
		$button=HTML_utils::div($button,array('class'=>'input-group-button'));
		$wrap=HTML_utils::div($input.$button,array('class'=>'input-group'));
		$f_atts=array('id'=>'search','method'=>'get','action'=>$url);
		$form=HTML::tag('form',$wrap,$f_atts);
		return $form;
	}
	function tabs($data=[]){
		$renderTab=function($args){
			$active=$aria='';
			if($args['active']){
				$active='is-active';
				$aria='aria-selected="true"';
			}
			return [
				'nav'=>'<li class="tabs-title '.$active.'"><a href="#'.$args['ref'].'">'.$args['label'].'</a></li>',
				'tab'=>'<div class="tabs-panel '.$active.'" id="'.$args['ref'].'" '.$aria.'>'.$args['content'].'</div>'
			];
		};
		$id=issetcheck($data,'id');
		if(!$id) $id='tabs_'.time();
		$nav=$tabs=$vert='';
		$ct=1;
		foreach($data['tabs'] as $i=>$v){
			$act=($ct==1)?true:false;
			$a=['label'=>$i,'content'=>$v,'ref'=>$id.'_'.$ct,'active'=>$act];
			$t=$renderTab($a);
			$nav.=$t['nav'];
			$tabs.=$t['tab'];
			$ct++;
		}
		if(isset($data['vertical']) && $data['vertical']) $vert='vertical ';
		$nav='<ul class="'.$vert.'tabs" data-tabs id="'.$data['id'].'">'.$nav.'</ul>';
		$tabs='<div class="'.$vert.' tabs-content" data-tabs-content="'.$data['id'].'">'.$tabs.'</div>';
		if($vert!==''){
			$output='<div class="grid-container"><div class="grid-x"><div class="cell medium-3">'.$nav.'</div><div class="cell medium-9">'.$tabs.'</div></div>';
		}else{
			$output=$nav.$tabs;
		}
		return $output;
	}
	
}
