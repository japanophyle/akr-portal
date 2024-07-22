<?php

class jamForm{
	var $XHTML=true;
	var $LABEL_WRAP=true;
	var $EDITOR_CLASS;
	var $SELECT_NOHEAD=array();
	var $FIELDS;
	
	//form args
	private $PARTS;
	private $READY;
	private $action;
	private $method;
	private $type;
	private $id;
	private $value;
	private $attr_ar;
	private $name;
	private $label;
	private $cols;
	private $rows;
	private $button;
	private $date;
	private $token;
	
	function __construct(){
		$this->PARTS=array('action','method','type','id','value','attr_ar','name','label','cols','rows','button','date');		
	}
	public function get_old($what=false,$vars=false){
		$what=ucwords($what);
		if(issetVar($what)){
			$what='get'.$what;
			if(method_exists($this,$what)){
				if(in_array($what,array('getSelect','getLabelFor','getLabelWrap'))){
					return $this->$what($vars);
				}else{
					$this->setVars($vars);
					if($this->READY){
						return $this->$what();
					}else{
						return '[:: no form options found for "'.$what.'" ::]';
					}
				}
			}else{
				return '[:: unknown element "'.$what.'" ::]';
			}
		}
		return false;			
	}
	public function get($what=false,$vars=false){
		$what=ucwords($what);
		if(issetVar($what)){
			$what='get'.$what;
			if(method_exists($this,$what)){
				$this->setVars($vars);
				if(in_array($what,array('getSelect','getLabelFor','getLabelWrap'))){
					if(!$vars) return '[:: no select options found for "'.$what.'" ::]';
					return $this->$what($vars);
				}else{
					return $this->$what();
				}
			}else{
				return '[:: unknown element "'.$what.'" ::]';
			}
		}
		return false;			
	}
	public function set($what=false,$vars=false){
		switch($what){
			case 'action': case 'method': case 'id': case 'class': case 'data':
				$this->$what=$vars;
				break;
		}
	}
	private function setVars($args=false){
		$ready=false;
		$this->attr_ar=array();
		if(is_array($args)){
			foreach($args as $i=>$v){
				if(in_array($i,$this->PARTS)){
					$this->$i=$v;
					$ready=true;
				}
			}
		}
		$this->READY=$ready;
	}
    private function getStart() {
		$action =($this->action)?$this->action:'#'; 
		$method = ($this->method)?$this->method:'post';
        $str = "<form action=\"$action\" method=\"$method\"";
        if ( $this->id ) {
            $str .= " id=\"{$this->id}";
        }
        $str .= $this->addAttributes() . '>';
        return $str;
    }

	private function getToken(){
	    $id=($this->name)?"_".$this->name:"";
	    $token = md5(uniqid(rand(), true));
        $_SESSION['token'.$id] = $token;
		$this->token=$token;
		return $token;
	}

	private function getTickbox(){
		$str ='<input type="checkbox"  name="'.$this->name.'" ';
        $str .= $this->addAttributes();
        $str .= '/><label for="'.$this->attr_ar['id'].'"></label>';
        return '<div class="checkboxTick">'.$str.'</div>';
	}
 	private function getSelect($args){
		$s_options='';
		$header=$selected=false;
		$attr_ar = $value = array();
		$name='???';
		$header = '- not set -';
		$bVal = true;
		$textVal=0;
		extract($args);
		if(in_array($name,$this->SELECT_NOHEAD)) $header=false;
		$multi=issetCheck($attr_ar,'multi');
		$s_options=$this->renderSelectOptions($value,$name,$selected,$header,$multi,$textVal);
		$str ='<select name="'.$name.'" ';
        if ($attr_ar) {
            $str .= $this->addAttributes( $attr_ar );
        }        
        $str .= '>'.$s_options.'</select>';
        return $str;
	}
	private function renderSelectOptions($option_list=null,$name=false,$value=false,$header=false,$multi=false,$textVal){
        $option_list=$this->formatOptions($option_list,$name);
 		$str='';
        if(is_string($header) && $header<>'') {
            $str .= "  <option value=\"null\">$header</option>\n";
        }
        if(is_array($option_list)){
			foreach ( $option_list as $val => $text ) {
				$str .= ($textVal==1)? "  <option":"  <option value=\"$val\"";
				$compare=($textVal==1)?$text:$val;
				$str.= $this->optionSelected($value,$compare,$multi);
				$str .= ">$text</option>\n";
			}
		}
        return $str; 		
	}
    private function getDate(){
		$type=($this->type)?$this->type:'text';
		$value=issetCheck($this->attr_ar,'value');
		$button=($this->button)?'input-group-field':false;
		$link_id='date_'.$this->name;
		$this->attr_ar['value']=validDate($value);
        $str = "<input type=\"date\" name=\"{$this->name}\" id=\"$link_id\" class=\"$button\" ";
        $str .= $this->addAttributes();
        $str .= $this->XHTML? ' />': '>';
        if($button){
			$str='<div class="input-group">'.$str.'<div class="input-group-button"><button class="button small bg-aqua" data-ref="'.$link_id.'" data-date="'.$$this->date.'">Select</button></div></div>';
		}		
        return $str;		
	}
    
    private function getTime(){
		$type=($this->type)?$this->type:'text';
		$button=($this->button)?'input-group-field':false;
		$link_id='time_'.$this->name;
        $str = "<input type=\"text\" name=\"{$this->name}\" id=\"$link_id\" class=\"timeME $button\"";
        $str .= $this->addAttributes();
        $str .= $this->XHTML? ' />': '>';
        if($button){
			$str='<div class="input-group">'.$str.'<div class="input-group-button"><button class="button small bg-blue" data-ref="'.$link_id.'" data-date="'.$this->value.'">Select</button></div></div>';
        }
        return $str;		
	}
    
    private function getInput($args=false){
		$type=($this->type)?$this->type:'text';
        $str = "<input type=\"{$this->type}\" name=\"{$this->name}\" ";
        if($this->value) $str.='value="'.$this->value.'" ';
        $str .= $this->addAttributes();
        $str .= $this->XHTML? ' />': '>';
        return $str;
    }
  
    private function getHidden(){
        $str = "<input type=\"hidden\" name=\"{$this->name}\" value=\"{$this->value}\"";
        $str .= $this->XHTML? ' />': '>';
        return $str;
    }
    
    private function getText($args=false){
		$type=($this->type)?$this->type:'text';
		$value=$this->value;
		$str='';
		switch($this->type){
			case 'text':
			case 'input':
				$value=$this->attr_ar['value'];
				break;
			case 'textarea':
				$value=$this->formatTextarea($this->attr_ar['value']);
				break;
			case 'select':
				//skip
				break;
		}
		if($value){
			$str = '<div class="faux-input '.$this->type.'">';
			$str .= $value;
			$str.='</div>';
		}
		return $str;
    }

    private function getButton(){
		$type=($this->type)?$this->type:'button';
        $str = "<button type=\"{$type}\" ";
        $str .= $this->addAttributes();
        $str .= '>'.$this->label.'</button>';
        return $str;
    }
   
    private function getTextarea($args=false) {
		$rows = ($this->rows)?$this->rows:4;
		$cols = ($this->cols)?$this->cols:30;
        $str = "<textarea name=\"{$this->name}\" rows=\"{$rows}\" cols=\"{$cols}\"";
        $str .= $this->addAttributes();
        $str .= '>'.$this->formatTextarea($this->value).'</textarea>';
        return $str;
    }
    private function getEditor($args=false){
		$rows = ($this->rows)?$this->rows:4;
		$cols = ($this->cols)?$this->cols:30;
		$str = "<textarea name=\"{$this->name}\" class=\"{$this->EDITOR_CLASS}\" ";
		$str .= $this->addAttributes();
        $str .= '>'.$this->value.'</textarea>';
        return $str;
	}
    // for attribute refers to id of associated form element
    private function getLabelFor($args=[]) {
		$attr_ar = array();
		extract($args);
        $str = "<label for=\"$forID\"";
        if ($attr_ar) {
            $str .= $this->addAttributes( $attr_ar );
        }
        $str .= ">$text</label>";
        return $str;
    }
    private function getLabelWrap($args=[]) {
		$attr_ar = array();
		extract($args);
        $str = "<label ";
        if ($attr_ar) {
            $str .= $this->addAttributes( $attr_ar );
        }
        $str .= ">$label $input</label>";
        return $str;
    }
 
// helpers
    private function optionSelected($selected=false,$compare=false,$multi=false){
		if($multi && is_array($selected)){
			if(in_array($compare,$selected)) return $this->XHTML? ' selected="selected"': ' selected';
		}else if(isset($selected)){
			if(is_numeric($compare)){
				if( (int)$selected == (int)$compare){
					return $this->XHTML? ' selected="selected"': ' selected';
				}
			}else{
				if( trim($selected) === trim($compare)){
					return $this->XHTML? ' selected="selected"': ' selected';
				}
			}
		}
		return '';
	}
	
    private function addAttributes( $attr_ar=false ) {
		if(!$attr_ar) $attr_ar=$this->attr_ar;
        $str = '';
        if(!is_array($attr_ar)) return $str;
        // check minimized (boolean) attributes
        $min_atts = array('checked', 'disabled', 'readonly', 'multiple','required', 'autofocus', 'novalidate', 'formnovalidate'); // html5
        foreach( $attr_ar as $key=>$val ) {
            if ( in_array($key, $min_atts) ) {
                if ( !empty($val) ) { 
                    $str .= $this->XHTML? " $key=\"$key\"": " $key";
                }
            } else {
				if(!is_array($val)) $str .= " $key=\"$val\"";
            }
        }
        return $str;
    }	
    private function formatTextarea($val=false){
		if($val){
			$val=strip_tags($val,'a,br');
			$val=str_replace('\r','',$val);
			if(strpos($val,'<br')!==false){
				$val=str_replace('\n','',$val);
				$breaks = array("<br />","<br>","<br/>");  
				$val = str_ireplace($breaks, PHP_EOL, $val); 				
			}else{
				$val = str_ireplace('\n', PHP_EOL, $val); 
			}
		}
		return $val;
	}

    private function formatOptions($data=false,$field=false){
		//SPECIFIC TO MemberME
		//creates key=>value array
		$out=false;
		$field_type='int';
		if($this->FIELDS){
			if(issetCheck($this->FIELDS,$field)){
				$field_type=$this->FIELDS[$field]['type'];
			}
		}
		if(is_array($data)){
			foreach($data as $id=>$val){
				if(is_array($val)){
					if(isset($val['OptionID'])){// for data from the Options DB
						$i=issetCheck($val,'OptionID',$id);
						$ov=issetCheck($val,'OptionValue');
						$v=$val['OptionName'];
						if($field==='GradeSet') $i=$ov;
						if($field!=='GradeSet' && $ov && $ov!=='') $v.=": $ov";						
					}else if(isset($val['LocationID'])){// for data from the Locations DB
						if($field==='DojoLock'){
							$i=issetCheck($val,'LocationID',$id);
						}else{
							$i=(in_array($field_type,array('int','tinyint')))?issetCheck($val,'LocationID',$id):$val['LocationName'];
						}
						$ov=false;
						$v=$val['LocationName'].', '.$val['LocationCountry'];
					}else if(isset($val['MemberID']) && isset($val['Name'])){
						$i=$id;
						$v=$val['Name'];
					}else{
						$i=$id;
						$v=$val['label'];
					}
					$out[$i]=$v;
				}else{
					if(in_array($field_type,array('int','tinyint'))){
						$out[$id]=$val;
					}else{
						$out[$val]=$val;
					}
				}
			}
		}
		return $out;
	}    
}
