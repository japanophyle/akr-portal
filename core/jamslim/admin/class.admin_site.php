<?php 

class admin_site{
	private $SLIM;
	private $MLIB;
	private $DATA=[];
	private $OUTPUT;
	private $PERMLINK;
	private $PERMBACK;
	private $MEDIA_TYPE;
	public $PLUG;
	private $ID=0;
	private $DEFAULT_REC;
	private $OPTIONS;
	private $OPTIONS_MAP=array(
		'site_mailing_list'=>'yesno',
		'site_slider_data'=>'booklists',
		'site_url_type'=>'url_type',
		'site_ticker_status'=>'yesno',
		'site_ticker_transition'=>'slidefade',
		'site_offline'=>'yesno',
		'site_payment_postage_power'=>'yesno',
		'site_payment_gateway'=>'payment_gateways',
		'site_sidebar_booklist'=>'booklists',
		'site_footer_text'=>'editor',
		'site_team_contacts'=>'array',
		'site_email_smtp_power'=>'yesno',
		'site_email_smtp_config'=>'array',
		'site_social_icons'=>'array',
		'site_login_confetti'=>'yesno',
		'site_validate_new_users'=>'yesno',
		'offline'=>	'yesno',
	);
	private $SETS=array('site_banner'=>false,'site_ticker'=>false,'site_offline'=>false,'site_sidebar'=>false,'site_payment'=>false,'site_email'=>false);
	private $SKIP=array('site_intro_title','site_main_nav','site_slider_data');
	private $HIDE_STATUS=true;
	private $OPTION_SET;
	public $AJAX;
	public $REQUEST;
	public $USER;
	public $METHOD;
	public $SECTION;
	public $ACTION;
	public $ADMIN;
	public $LEADER;
	public $ROUTE;	
	
	function __construct($slim=null){
		if(!$slim) throw new Exception(__METHOD__.': no slim object!!');
		$this->SLIM=$slim;
		$this->DEFAULT_REC=array(
			'OptionID'=>0,
			'OptionGroup'=>'',
			'OptionName'=>'',
			'OptionDescription'=>'',
			'OptionValue'=>''
		);
		$this->SKIP;
	}
	
	function Process(){
		$this->init();
		if($this->METHOD==='POST'){
			$this->doPost();
		}
		switch($this->ACTION){
			case 'edit': case 'new':
				$this->renderEditItem();
				break;
			default:
				$this->renderListItems();
				break;				
		}
		return $this->renderOutput();
	}
	private function doPost(){
		$db=$this->SLIM->db->Options;
		$state=500;
		$mtype='alert';
		switch($this->REQUEST['action']){
			case 'update':
				$id=(int)$this->REQUEST['id'];
				if($id){
					if(is_array($this->REQUEST['OptionValue'])){
						$val=compress($this->REQUEST['OptionValue']);
					}else{
						$val=$this->REQUEST['OptionValue'];
					}
					$update=array('OptionValue'=>$val);
					$rec=$db->where('id',$id);
					if(count($rec)){
						$chk=$rec->update($update);
						if($chk){
							$msg='Okay, the option has been updated.';
							$state=200;
							$mtype='success';
						}else if($this->SLIM->db_error){
							$msg='Sorry, there was a problem updating the option ...';
						}else{
							$msg='Okay, but it looks like no changes have been made.';
							$state=200;
							$mtype='primary';
						}
					}else{
						$msg='Sorry, I can\'t find an option with that ID:'.$id;
					}
				}else{
					$msg='Sorry, I can\'t find an option to update...';
				}
				break;
			default:
				$msg='Sorry, I don\'t think you can do that...';
		}
		if($this->AJAX){
			$close=($state==200)?1:0;
			$out=array('status'=>$state,'message'=>$msg,'message_type'=>$mtype,'close'=>$close);
			echo jsonResponse($out);
		}else{
			setSystemResponse($this->PERMLINK,$msg);
		}
		die($msg);
	}
	private function init(){
		$this->METHOD=$this->SLIM->router->get('method');
		if(!$this->METHOD) $this->METHOD='GET';
		$this->REQUEST=($this->METHOD==='POST')?$this->SLIM->router->get('post'):$this->SLIM->router->get('get');
		$this->ROUTE=$this->SLIM->router->get('route');
		$this->SECTION=issetCheck($this->ROUTE,1);
		$this->OPTION_SET=issetCheck($this->ROUTE,2,'general');
		$this->ACTION=issetCheck($this->ROUTE,3,'list');
		$this->ID=issetCheck($this->ROUTE,4);
		$this->AJAX=$this->SLIM->router->get('ajax');
		$this->USER=$this->SLIM->user;			
		$this->PERMBACK=URL.'admin/site/';
		$this->PERMLINK=$this->PERMBACK.$this->OPTION_SET.'/';
		$this->PLUG=issetCheck($this->SLIM->AdminPlugins,$this->SECTION);
		//init data
		if(!$this->ACTION && !$this->METHOD==='POST'){
			$this->DATA=$this->getSiteOptions('all');
			$this->makeDataSets();
		}else{
			if($this->ID==='new'){
				$this->DATA=$this->getSiteOptions('new');
			}else if((int)$this->ID){
				$this->DATA=$this->getSiteOptions('id');
			}else if(in_array($this->ACTION,array('list','select'))){
				$this->DATA=$this->getSiteOptions('all');
				$this->makeDataSets();
			}
		}		
	}
	private function renderOutput(){
		if(is_array($this->OUTPUT)){
			$out=$this->OUTPUT;
		}else if(!$this->OUTPUT||$this->OUTPUT===''){
			$out=msgHandler('Sorry, no output was generated...',false,false);
		}else{
			$out=$this->OUTPUT;
		}
		if($this->AJAX){
			if(is_array($out)){
				jsonResponse($out);
			}else{
				echo $out;
			}
			die;
		}
		return $out;
	}
	
	private function renderListItems(){
		$count=0;
		$set_name=($this->OPTION_SET==='general')?$this->OPTION_SET:'site_'.$this->OPTION_SET;
		if(isset($this->DATA[$set_name])){
			$tbl=[];
			foreach($this->DATA[$set_name] as $i=>$dat){
				$dat=$this->formatData($dat);
				$tbl[$i]=array(
					'ID'=>$i,
					'Name'=>$dat['OptionName'],
					'Value'=>$dat['OptionValue']
				);
				if(!$this->HIDE_STATUS) $tbl[$i]['Status']=$dat['OptionActive'];
				$tbl[$i]['Controls']='<button class="button button-dark-purple small loadME" data-ref="'.$this->PERMLINK.'edit/'.$i.'"><i class="fi-pencil"></i> Edit</button>';
				$count++;
			}
			$args['data']['data']=$tbl;
			$args['before']='filter';
			$list=dataTable($args);
		}else{
			$list=msgHandler('No user records found...',false,false);
		}
		$icon='<i class="fi-'.$this->PLUG['icon'].' icon-x1b"></i>';
		$this->OUTPUT=array(
			'title'=>'Site - '.ucMe(str_replace('site_','',$this->OPTION_SET)).': <span class="subheader">('.$count.')</span>',
			'content'=>$list,
			'icon'=>$icon,
			'menu'=>array('right'=>$this->renderContextMenu()),
		);
	}
	private function getSiteOptions($type=false){
		switch($type){
			case 'id':
				$db=$this->SLIM->db->Options();
				$rez=$db->where('id',$this->ID);
				$rez=renderResultsORM($rez,'id');
				break;
			case 'all':
				$rez=$this->SLIM->Options->get('site');
				break;
			case 'new':
				return $this->DEFAULT_REC;
				break;
			default:
				$rez=false;
		}
		if($rez){
			if($type==='id') $rez=current($rez);
		}else{
			$rez=[];
		}
		return $rez;
	}
	private function renderEditItem(){
		$id=$this->ID;
		if($this->DATA){
			$data=$this->formatData($this->DATA,'edit');
			if(!$this->AJAX) $data['js']='';
			$tpl=file_get_contents(TEMPLATES.'parts/tpl.'.$this->SECTION.'-edit.html');
			$sidebar='';
			$data['submit']='<i class="fi-check"></i> Update';
			$data['action']='update';
			$data['id']=$this->ID;
			$data['form_url_site']=$this->PERMLINK;
			$tpl=str_replace('{sidebar}',$sidebar,$tpl);
			$form=replaceME($data,$tpl);
		}else{
			$list=msgHandler('Sorry, I could not find the record...',false,false);	
		}	
		$icon='<i class="fi-'.$this->PLUG['icon'].' icon-x1b"></i>';
		$this->OUTPUT=array(
			'title'=>'Edit '.ucME($this->SECTION).': <span class="subheader">#'.$id.' - '.fixHTML($data['OptionName']).'</span>',
			'content'=>$form,
			'icon'=>$icon,
			'menu'=>array('right'=>$this->renderContextMenu()),		
		);
		if($this->AJAX){
			echo renderCard_active($this->OUTPUT['title'],$this->OUTPUT['content'],$this->SLIM->closer);
			die;
		}
	}
	private function renderEditOptions(){
		$frm['title']='Options';
		$content=msgHandler('No options found...',false,false);
		switch($this->MEDIA_TYPE){
			case 'page':
				$content='<label>Status<select name="itm_Active">{status}</select></label>';
				$content.='<label>Date<input placeholder="item date" type="date" name="itm_DAte" value="{itm_Date}" /></label>';
				break;
			case 'book':
				$content='<label>Author<input type="text" placeholder="author" name="meta[book_author]" value="{book_author}"/></label>';
				$content.='<label>Binding<select name="meta[book_binding]">{binding}</select></label>';
				$content.='<label>Publisher<input type="text" placeholder="publisher" name="meta[book_publisher]" value="{book_publisher}"/></label>';
				$content.='<label>Pub. Date<input type="date" placeholder="date published" name="itm_DAte" value="{itm_DAte}"/></label>';
				$content.='<label>Price<input type="number" min="0.01" step="0.01" placeholder="price" name="meta[book_price]" value="{book_price}"/></label>';
				$content.='<label>Status<select name="itm_Active">{status}</select></label>';
				break;
			default:
				$content='<label>Status<select name="itm_Active">{status}</select></label>';
		}
		$frm['content']=$content;	
		return renderCard($frm);		
	}
	private function renderStatus($state=0){
		$o=$this->SLIM->Options->get('yesno');
		$opts='';
		foreach($o as $i=>$v){
			$sel=($state==$i)?'selected':'';
			$opts.='<option value="'.$i.'" '.$sel.'>'.$v.'</option>';
		}
		$out='<label>Status Value<select name="opt_Active" >'.$opts.'</select></label>';
		return $out;	
	}
	private function getSelect($what=false,$var=false,$map=false){
		$out='';
		if($map){
			$options=$this->SLIM->Options->get($map);
			if($options){
				 foreach($options as $i=>$v){
					$sel=($i==$var)?'selected':'';
					$val=(is_array($v))?$v['name']:$v;
					$out.='<option value="'.$i.'" '.$sel.'>'.$val.'</option>';
				 }				 
			}
		}
		return $out;
	}
	
	private function formatData($data,$mode='view'){
		$fix=array();
		$js=$map=false;
		foreach($data as $i=>$v){
			$val=$v;
			switch($i){
				case 'OptionName':
					$val=ucME(str_replace('site_','',$val));
					break;
				case 'OptionValue':
					$map=issetCheck($this->OPTIONS_MAP,$data['OptionName']);
					if($map){
						if($mode==='view'){
							switch($map){
								case 'array':
									$tmp=compress($v,false);
									if(!$tmp) $tmp=array();
									$val=count($tmp).' items. (click edit for details)';
									break;									
								case 'editor': case 'textarea':
									$val=html_entity_decode($v);
									break;
								default:
									$val=$this->getOption($data['OptionName'],$v,$map);
							}						
						}else if($mode==='edit'){
							switch($map){
								case 'editor': case 'textarea':
									$class='';
									if($map==='editor'){
										$class='modal_editor';
										$js='JQD.ext.initEditor(".modal_editor");';
									}
									$val='<textarea id="OptionValue" class="'.$class.'" name="OptionValue" rows="8">'.$v.'</textarea>';
									break;
								case 'array':
									$tmp=$this->renderArrayOptions($v,$data['id']);
									$val=$tmp['content'];
									$js=$tmp['js'];
									break;
								default:
									$val=$this->getSelect($data['OptionName'],$v,$map);
									$val='<select name="OptionValue" >'.$val.'</select>';
							}
						}
					}else{
						if($mode==='edit'){
							$val='<input placeholder="value" type="text" name="OptionValue" value="'.$val.'" />';
						}
					}
					break;
			}
			if($i==='OptionValue' && $mode==='edit'){
				if(in_array($map,array('editor','textarea','array'))){
					$val='<div style="margin:0 0.8rem;">'.$val.'</div>';
				}else{
					$val='<label>Option Value '.$val.'</label>';
				}
			}
			$fix[$i]=$val;
		}
		if($js!=='') $fix['js']='<script>'.$js.'</script>';
		return $fix;
	}
	private function renderArrayOptions($data,$id){
		$tmp=compress($data,false);
		$ops=[];
		if($tmp){
			foreach($tmp as $x=>$y){
				$form='<fieldset>';
				if(is_array($y)){
					$title=$y['name'];
					foreach($y as $i=>$v){
						$form.='<label>'.$i.'<input type="text" name="OptionValue['.$x.']['.$i.']" value="'.$v.'"/></label>';
					}
				}else{
					$title=ucME($x);
					$form.='<label>'.$title.'<input type="text" name="OptionValue['.$x.']" value="'.$y.'"/></label>';
				}
				$form.='<fieldset>';
				$ops[$x]=array('title'=>$title,'content'=>$form);
			}
			$out['content']=renderTabs($ops);
		}else{
			$out['content']=msgHandler('Sorry, the value is empty or invalid...',false,false);
		}
		$out['js']='$(".reveal .card").foundation();';
		return $out;
	}
	private function getOption($what=false,$val=false,$map=false){
		if($map){
			$options=$this->SLIM->Options->get($map);
			if($options) $val=issetCheck($options,$val,$val);
			switch($map){
				case 'booklists': 
					if(is_array($val)) $val=$val['name'];
				break;
			}
		}
		if(is_array($val)) preME(array($what,$val,$map),2);
		return $val;		
	}
	private function makeDataSets(){
		$sets=array();
		foreach($this->DATA as $i=>$v){
			$key=$v['OptionName'];
			if(in_array($key,$this->SKIP)){
				//ignore
			}else{
				$found=false;
				foreach($this->SETS as $s=>$_v){
					if(strpos($key,$s)!==false){
						$sets[$s][$i]=$v;
						$found=true;
						break;
					}
				}
				if(!$found){
					$sets['general'][$i]=$v;
				}
			}
		}
		$this->DATA=$sets;
	}
	private function inSet($fld=false){
		if($fld){
			$set='site_'.$this->OPTION_SET;
			foreach($this->DATA as $i=>$v){
				switch($this->OPTION_SET){
					case 'general':
						if(strpos($v['OptionName'],$set)!==false)	return true;
						break;
					default:
						if(strpos($v['OptionName'],$set)!==false)	return true;
				}
			}				
		}
		return false;		
	}
	private function renderContextMenu(){
		$libname=($this->MEDIA_TYPE==='image')?'document':'image';
		$but['back']='<button class="button small button-dark-purple backME" title="back to list" type="button"><i class="fi-arrow-left"></i> Back</button>';
		$but['save']='<button class="button small button-olive submitME" title="save changes" data-ref="ajaxform" type="button"><i class="fi-check"></i> Update</button>';
		$but['switch']='<button class="button small button-lavendar gotoME" title="'.$libname.' library" data-ref="'.$this->PERMBACK.$libname.'" type="button"><i class="fi-check"></i> '.ucwords($libname).'s</button>';
		$b=[];$out=false;
		switch($this->ACTION){
			case 'edit':
				$b=array('back','save');
				break;				
			default:
				$but['general']='<button class="button small button-lavendar gotoME" data-ref="'.$this->PERMBACK.'general" type="button" title="general options"><i class="fi-wrench"></i> General</button>';
				if($this->OPTION_SET!=='general') $b[]='general';
				foreach($this->SETS as $s=>$v){
					if($v){
						$s=str_replace('site_','',$s);
						$but[$s]='<button class="button small button-lavendar gotoME" data-ref="'.$this->PERMBACK.$s.'" type="button"><i class="fi-wrench" title="'.$s.' options"></i> '.ucME($s).'</button>';
						if($this->OPTION_SET!==$s) $b[]=$s;
					}
				}			
		}
		if($b){
			foreach($b as $i){
				$out.='<li>'.$but[$i].'</li>';
			}
		}
		return $out;
	}
	
	
}
