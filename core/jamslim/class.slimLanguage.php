<?php

class slimLanguage {
	private $SLIM;
	private $USER;
	private $CAN_EDIT=false;
	private $SHOW_EDIT=true;
	private $LANGUAGE;
	private $LANGUAGES=array('fr'=>'French','de'=>'German','en'=>'English');
	private $LANGUAGE_NAMES=array(
		'fr'=>array('en'=>'Anglais','fr'=>'français','de'=>'Allemand'),
		'de'=>array('en'=>'Englisch','fr'=>'Französisch','de'=>'Deutsche'),
		'en'=>array('en'=>'English','fr'=>'French','de'=>'German'),
	);
	private $LANGUAGE_LOCK='en';
	private $DATA=[];
	private $READY=false;
	private $ERRORS=[];
	private $ROUTE=[];
	private $DEFAULT_REC;
	private $DEFAULT_LANG='en';
	private $MODE='translate';//translation or edit
	private $FILE_WORDS;
	private $FILE_PHRASES;
	private $FILE_CONTENTS;
	private $DATASET='words';//words, phrases or contents
	private $SHOW_CONTROLS;	
	
	public $STANDARDS;//standard/basic translations
	public $AJAX;
	public $PERMBACK;
	public $PERMLINK;
	
	
	function __construct($slim){
		$this->SLIM=$slim;
		$this->USER=$slim->user;
		$this->PERMBACK=URL.'admin/';
		$this->PERMLINK=$this->PERMBACK.'lang/';
		$this->FILE_WORDS=CACHE.'lang_standards_words.php';
		$this->FILE_PHRASES=CACHE.'lang_standards_phrases.php';
		$this->FILE_CONTENTS=CACHE.'lang_standards_contents.php';
		if($this->LANGUAGE_LOCK){
			$this->DEFAULT_LANG=$this->LANGUAGE_LOCK;
		}else{
			$tmp=$slim->Options->get('site','default_language','value');
			$this->DEFAULT_LANG=issetCheck($tmp,'OptionValue',$this->LANGUAGE);
		}
		
		$tmp=$slim->Options->get('site','language_options','value');
		$this->SHOW_CONTROLS=(int)issetCheck($tmp,'OptionValue');
		if($this->USER['access']>=25) $this->CAN_EDIT=true;
		$this->init();
	}
	
	private function init($lang=false){
		if($this->USER['id']){
			if(!$lang) $lang=issetCheck($this->USER,'Language');
			if(!$lang) $lang=issetCheck($this->USER,'language');
			if(!$lang) $lang=$this->DEFAULT_LANG;
		}else{
			$lang=$this->DEFAULT_LANG;
		}
		if(!issetCheck($this->LANGUAGES,$lang)) $lang=$this->DEFAULT_LANG;
		if($this->LANGUAGE!==$lang)	$this->LANGUAGE=$lang;
		//edit mode
		$lang_sesh=issetCheck($this->USER,'lang_mode');
		if($lang_sesh && $lang_sesh!=='')$this->set('MODE',$lang_sesh);
		$this->DEFAULT_REC=array(
			'words'=>array('fr'=>'','de'=>''),
			'phrases'=>array('en'=>'','fr'=>'','de'=>''),
			'contents'=>array('description'=>'','en'=>'','fr'=>'','de'=>'')
		);
		$this->loadData();
		$this->READY=true;
	}
	
	private function loadData(){
		foreach(array_keys($this->DEFAULT_REC) as $r){
			$file=CACHE.'lang_standards_'.$r.'.php';
			if(file_exists($file)){
				if($j=file_get_contents($file)){
					$t='FILE_'.strtoupper($r);
					$this->$t=$file;
					$j=json_decode($j,true);
					if(is_array($j)){
						ksort($j);
						$this->DATA[$r]=$j;
					}
				}
			}else{//create file
				$this->initStandards($r,true);//true to create cache
			}			
		}
	}
	private function saveData($set=false){
		$chk=0;
		if($set){
			$data=issetCheck($this->DATA,$set);
			if($data){
				$t='FILE_'.strtoupper($set);
				$f=$this->$t;
				if($f){
					$j=json_encode($data);
					$chk=file_put_contents($f,$j);
				}
			}
		}
		return $chk;			
	}
	private function log($str){
		$this->ERRORS[$str]=$this->LANGUAGE;
	}
	
	public function get($what=false,$vars=false){
		switch($what){
			case '_READY':
				return $this->READY;
				break;
			case '_LANG':
				return $this->LANGUAGE;
				break;
			case '_LANGS':
				return $this->LANGUAGES;
				break;
			case '_LANGNAMES':
				if(!$this->SHOW_CONTROLS){
					return ['en'=>'English'];
				}
				return $this->LANGUAGE_NAMES[$this->LANGUAGE];
				break;
			case '_ERRORS':
				return $this->ERRORS;
				break;
			case 'REC':
				return issetCheck($this->DATA[$this->DATASET],$vars);
				break;
			case '_MODE':
				return $this->MODE;
				break;
			case '_DEFREC':
				return $this->DEFAULT_REC;
				break;
			case '_POWER':
				return $this->SHOW_CONTROLS;
				break;
			default:// lookup translation
				if($this->LANGUAGE!=='en' && $what && $what!==''){
					if(!$vars) $vars='default';
					return $this->getTranslation($what,$vars);
				}else{
					return $what;
				}
		}
	}
	
	public function lang($str=false,$default=true){
		$chk=$this->getStandard($str,false);
		if(!$chk) $chk=$this->getStandardPhrase($str,false);
		if(!$chk && $default) $chk=ucME($str);
		return $chk;
	}
	public function getStandard($str=false,$default=true){
		$out=false;
		$state=false;
		if($str){
			$str=slugME($str,'_');
			if($this->LANGUAGE!=='en'){
				$out=issetCheck($this->DATA['words'],$str);
				if($out){
					$out=$out[$this->LANGUAGE];
					if($out) $state=true;
				}
				if((!$out || $out==='') && $default) {
					$out=ucME($str);
				}				
			}else{
				//usa hack!!
				if(in_array($str,['post_code','postal_code'])) $str='zip_code';
				$out=ucME($str);
				$state=isset($this->DATA['words'][$str]);
			}
		}
		return $this->renderEditLink($str,$out,$state,'words');
	}
	private function renderEditLink($slug,$phrase,$state,$type='words'){
		$link=$phrase;
		if($this->MODE==='edit'){
			if(!$state){
				//$this->log($what);
				if($this->CAN_EDIT && $this->SHOW_EDIT){
					$link='<span class="link-red text-shadow loadME" data-ref="'.$this->PERMLINK.'add_lang/'.$type.'/'.$slug.'" title="add translation- '.$type.'" >'.$phrase.' <i class="fi-plus"></i></span>';
				}
			}else{
				if($this->CAN_EDIT && $this->SHOW_EDIT){
					$link='<span class="link-green text-shadow loadME" data-ref="'.$this->PERMLINK.'edit_lang/'.$type.'/'.$slug.'" title="edit translation - '.$type.'" >'.$phrase.' <i class="fi-wrench"></i></span>';
				}
			}
		}
		return $link;
	}
	public function getStandardPhrase($str=false,$default=true){
		$state=$out=false;		
		if($str){
			$str=slugME($str,'_');
			$out=issetCheck($this->DATA['phrases'],$str);
			if($out){
				$out=$out[$this->LANGUAGE];
				if($out) $state=true;
			}				
			if((!$out || $out==='') && $default){
				 $out=ucME($str);
			}
		}
		return $this->renderEditLink($str,$out,$state,'phrases');
	}
	public function getStandardContent($str=false,$default=true){
		$state=false;
		$out=[];		
		if($str){
			$str=slugME($str,'_');
			$out=issetCheck($this->DATA['contents'],$str);
			if($out){
				$out=$out[$this->LANGUAGE];
				if($out){
					$state=true;
					$out=base64_decode($out);
				}
			}				
			if((!$out || $out==='') && $default){
				 $out=ucME($str);
			}
		}
		return $out;
	}
	
	public function Postman($post=false){		
		$state=500;$msg_type='alert';$close=false;
		$action=issetCheck($post,'action');
		$lang=issetCheck($post,'language',$this->LANGUAGE);
		switch($action){
			case 'add_lang':
				$id=issetCheck($post,'key');
				$set=issetCheck($post,'dataset');
				if($id && $set){
					$id=strtolower(str_replace(' ','_',$id));//slugify the key
					$this->switchDataset($set);
					$rec=$this->DEFAULT_REC[$this->DATASET];
					$chk=$this->saveTranslation($id,$rec,$post);
					if($chk){
						$msg='Okay, the translation has been added.';
						$state=200;
						$close=true;
						$msg_type='success';
					}else{
						$msg='Sorry, there was problem adding the translation...';
					}
				}else{
					$msg='Sorry, incomplete data supplied...';
				}
				break;
			case 'update_lang':
				$id=issetCheck($post,'ID');
				$set=issetCheck($post,'dataset');
				if($id && $set){
					$this->switchDataset($set);
					$rec=$this->get('REC',$id);
					if($rec){
						$chk=$this->saveTranslation($id,$rec,$post);
						if($chk){
							$msg='Okay, the translation has been updated.';
							$state=200;
							$close=true;
							$msg_type='success';
						}else{
							$msg='Sorry, there was problem updating the translation...';
						}
					}else{
						$msg='Sorry, I can\'t find that record ['.$id.']...';
					}
				}else{
					$msg='Sorry, incomplete data supplied...';
				}
				break;
			case 'update_csv':
				$set=issetCheck($post,'dataset');
				$chk=$this->updateFromCSV($set,$post);
				if($chk){
					$msg='Okay, the translation has been updated.';
					$state=200;
					$close=true;
					$msg_type='success';
				}else{
					$msg='Sorry, there was problem updating the translation...';
				}
				break;
			default:
				$msg='Sorry, I don\'t know what "'.$action.'" is...';
		}
		$out=array('status'=>$state,'message'=>$msg,'message_type'=>$msg_type,'close'=>$close,'type'=>'message');
		if($this->AJAX){
			jsonResponse($out);
			die;
		}else{
			return $out;
		}
	}
	
	private function updateFromCSV($set,$post){
		$rpME=function($str){
			$str=str_replace("\'",'|??|',$str);
			$str=str_replace("'",'',$str);
			$str=str_replace('\,',',',$str);
			$str=str_replace("|??|","'",$str);
			return $str;
		};
		$csv=issetCheck($post,'csv_data');
		$csv_key=array(0=>'key',1=>'english',2=>'french',3=>'german');
		$ct=0;
		$chk=false;
		$this->switchDataset($set);
		if($csv){
			$Data = str_getcsv($csv, ";\n"); //parse the rows
			foreach($Data as $Row) {
				$_csv = ($set==='phrases')?str_getcsv($Row,',',"'"):str_getcsv($Row); //parse the items in rows
				$key=trim($_csv[0]);
				if($key){
					$old=issetCheck($this->DATA[$this->DATASET],$key);
					$en=$rpME(issetCheck($_csv,1));
					$fr=$rpME(issetCheck($_csv,2));
					$de=$rpME(issetCheck($_csv,3));
					if($old){
						switch($this->DATASET){
							case 'words':
								if($fr) $old['fr']=$fr;
								if($de) $old['de']=$de;
								$this->DATA[$this->DATASET][$key]=$old;
								$ct++;
								break;
							case 'phrases':
								if($en) $old['en']=$en;
								if($fr) $old['fr']=$fr;
								if($de) $old['de']=$de;
								$this->DATA[$this->DATASET][$key]=$old;
								$ct++;
							default:
						}
					}else{// add it
						switch($this->DATASET){
							case 'words':
								if($fr && $de){
									$old['fr']=$fr;
									$old['de']=$de;
									$this->DATA[$this->DATASET][$key]=$old;
									$ct++;
								}
								break;
							case 'phrases':
								if($en && $fr && $de){
									$old['en']=$en;
									$old['fr']=$fr;
									$old['de']=$de;
									$this->DATA[$this->DATASET][$key]=$old;
									$ct++;
								}
							default:
						}
					}						
				}
			}
			if($ct>0) $chk=$this->saveData($this->DATASET);
		}
		if($chk) return $ct;
		return false;
	}
	
	private function saveTranslation($id,$rec,$post){
		$save=true;
		$chk=false;
		switch($this->DATASET){
			case 'words':
				$rec['fr']=$post['fr'];
				$rec['de']=$post['de'];
				$this->DATA[$this->DATASET][$id]=$rec;
				break;
			case 'phrases':
				$rec['fr']=$this->trimPhrase($post['fr'],'p');
				$rec['de']=$this->trimPhrase($post['de'],'p');
				$rec['en']=$this->trimPhrase($post['en'],'p');
				$this->DATA[$this->DATASET][$id]=$rec;
				break;
			case 'contents':
				$rec['fr']=base64_encode($post['fr']);
				$rec['de']=base64_encode($post['de']);
				$rec['en']=base64_encode($post['en']);
				$this->DATA[$this->DATASET][$id]=$rec;
				break;
			default:
				$save=false;
		}
		if($save){
			$chk=$this->saveData($this->DATASET);
		}
		return $chk;
	}
	
	public function trimPhrase($phrase=false,$trim=false){
		//trim tag from the ends of a phrase. used mainly when saving.
		$trimmed=$phrase;
		if($phrase && $trim){
			$trimmed=rightTrim($phrase,'</'.$trim.'>',false);
			$trimmed=leftTrim($trimmed,'<'.$trim.'>',false);
		}
		return $trimmed;
	}
		
	public function set($what,$vars=false){
		switch($what){
			case 'DATA':
				if(is_array($vars)) $this->DATA+=$vars;
				break;
			case 'LANG':
				if(is_string($vars) && $vars!=='') $this->LANGUAGE=$vars;
				break;
			case 'INIT':
				$this->init($vars);
				break;
			case 'TEMP_MODE':
			case 'MODE':
				$this->MODE=($vars==='edit')?'edit':'translate';
				if($what==='MODE') setMySession('lang_mode',$this->MODE);
				break;
		}
	}
	
	public function render($route=false,$ajax=false){
		$this->ROUTE=$route;
		$this->AJAX=$ajax;
		$action=issetCheck($this->ROUTE,1);
		$set=issetCheck($this->ROUTE,2);
		$phrase=issetCheck($this->ROUTE,3);
		$this->SHOW_EDIT=false;
		$this->switchDataset($set);
		$title=ucwords(str_replace('_lang','',$action));
		switch($action){
			case 'check_lang':
				if($phrase) $phrase=urldecode($phrase);
				$out=$this->checkTranslations($phrase,$set);
				$title='Translations';
				break;
			case 'add_lang':
			case 'new_lang':
				$title.=' '.ucwords($this->DATASET);
				$out=$this->renderForm($action,$phrase);
				break;
			case 'csv_update':
				$title.=' Update '.ucwords($this->DATASET).' from CSV';
				$out=$this->renderForm($action);
				break;
			case 'edit_lang':
				$title.=' '.ucwords($this->DATASET);
				$title.=': <span class="text-gray">'.$phrase.'</span>';
				$out=$this->renderForm($action,$phrase);
				break;
			case 'lists':
				$this->MODE='edit';
				$out=$this->renderTable($set);
				break;
			default:
				$action=$title='error';
				$out=array('title'=>'Error','content'=>msgHandler('Sorry, I don\'t know what to do...',false,false));
				break;
		}
		$this->SHOW_EDIT=true;//reset is needed here
		if($this->AJAX){
			$disp=array('title'=>'Language Editor: <span class="subheader">'.$title.'</span>','content'=>$out,'controls'=>$this->SLIM->closer);
			echo renderCard($disp);
			die;
		}else{
			return $out;
		}
	}
	private function switchDataset($set=false){
		if($set){
			if(array_key_exists($set,$this->DEFAULT_REC)) $this->DATASET=$set;
		}		
	}
	private function renderTable(){
		$row=[];$controls=false;
		$subtitle=ucME($this->DATASET);
		$control_button='<button class="button button-navy gotoME" data-ref="'.$this->PERMLINK.'lists/{list}" title="{switch_title}"><i class="fi-database"></i> {switch_label}</button>';
		$lists=array_keys($this->DEFAULT_REC);
		foreach($lists as $i){
			if($i!==$this->DATASET){
				$switch_title='manage the '.$i.' translations';
				$x=str_replace('{list}',$i,$control_button);
				$x=str_replace('{switch_title}',$switch_title,$x);
				$controls.=str_replace('{switch_label}',ucME($i),$x);
			}
		}
		$controls.='<button class="button button-olive loadME" data-ref="'.$this->PERMLINK.'new_lang/'.$this->DATASET.'" title="add a new '.$this->DATASET.'"><i class="fi-plus"></i> New '.$subtitle.'</button>';
		if($this->USER['access']>25){
			$controls.='<button class="button button-purple loadME" data-ref="'.$this->PERMLINK.'csv_update/'.$this->DATASET.'" title="update '.$this->DATASET.' from csv"><i class="fi-refresh"></i> Update '.$subtitle.'</button>';
		}

		foreach($this->DATA[$this->DATASET] as $i=>$v){
			switch($this->DATASET){
				case 'words':
					$rw=array(
						'Key'=>$i,
						'English'=>ucME($i),
						'Translations'=>'<small class="text-dark-blue"><strong>FR:</strong> '.$v['fr'].'</small><br/><small class="text-black"><strong>DE:</strong> '.$v['de'].'</small>',
						'Controls'=>'<button class="button button-dark-purple small loadME" data-ref="'.$this->PERMLINK.'edit_lang/words/'.$i.'" ><i class="fi-wrench"></i> Edit</button>'
					);
					break;
				case 'phrases':
					$rw=array(
						'Key'=>$i,
						'Translations'=>'<small class="text-maroon"><strong>EN:</strong> '.$v['en'].'</small><br/><small class="text-dark-blue"><strong>FR:</strong> '.$v['fr'].'</small><br/><small class="text-black"><strong>DE:</strong> '.$v['de'].'</small>',
						'Controls'=>'<button class="button button-dark-purple small loadME" data-ref="'.$this->PERMLINK.'edit_lang/phrases/'.$i.'" ><i class="fi-wrench"></i> Edit</button>'
					);
					break;
				case 'contents':
					$desc=(isset($v['description']) && $v['description']!=='')?'<span class="label bg-blue expanded">'.$v['description'].'</span>':'';
					$rw=array(
						'Key'=>$i,
						'Translations'=>$desc.'<div class="callout" style="max-height:10rem; max-width:40rem; overflow-y:auto"><small class="text-maroon"><strong>EN:</strong></small>'.base64_decode($v['en']).'</div>',
						'Controls'=>'<button class="button button-dark-purple small loadME" data-ref="'.$this->PERMLINK.'edit_lang/contents/'.$i.'" ><i class="fi-wrench"></i> Edit</button>'
					);
					break;
				default:
					$rw=false;
			}			
			if($rw) $row[$i]=$rw;
		}
		$args['data']['data']=$row;
		$args['before']='filter';
		$table=dataTable($args);
		
		return array('title'=>'Language Editor: <span class="subheader">'.$subtitle.'</span>','content'=>$table);
	}
	private function prepOutput($args=false){
		$out=new generic;
		$title=issetCheck($args,'title','Language Editor');
		$controls=issetCheck($args,'controls','');
		$content=(is_array($args))?issetCheck($args,'content','* no content *'):$args;		
		$out->set('ICON',false,'wrench');
		$out->set('TITLE',false,$title);
		$out->set('CONTENT',false,$content);
		$out->set('TOP_CONTROLS',false,$controls);
		return $out->get('all');		
	}
	private function checkTranslations($phrase=false,$type=false){
		$test=$this->getTranslation($phrase,$type);
		$row=false;
		foreach($this->LANGUAGES as $code=>$lang){
			$val='';
			$vid=$code;
			foreach($test as $i=>$v){
				if($v['Lang']===$code){
					$val=$v['Translation'];
					$vid=$i;
					break;
				}
			}
			$field='<input type="text" name="lang['.$vid.']" value="'.$val.'"/>';
			if($code!=='en') $row.='<tr><td><strong class="text-gray">'.$lang.' ('.$code.')</strong></td><td>'.$field.'</td></tr>';
		}

		$args=array(
			'form_url'=>$this->PERMLINK,
			'form_action'=>'update_langs',
			'english'=>$phrase,
			'form_button'=>'Add/Update Translations',
			'rows'=>$row,
			'type'=>$type
		);
		$tpl=file_get_contents(TEMPLATES.'app/app.form_lang_table_ajax.html');
		return replaceMe($args,$tpl);
	}
	private function getTranslation($phrase,$type=false,$edit=false){
		$trans=false;
		$rec=false;
		$trans=$this->getStandard($phrase,false);
		if(!$trans) $trans=$this->getStandardPhrase($phrase,false);
		if($edit){
			if(!$trans){
				//$this->log($what);
				if($this->CAN_EDIT && $this->SHOW_EDIT){
					$trans='<span class="link-red loadME" data-ref="'.$this->PERMLINK.'check_lang/'.$this->DATASET.'/'.urlencode($phrase).'/'.$type.'" title="add translation" >'.$phrase.' <i class="fi-plus"></i></span>';
				}
			}else{
				if($this->CAN_EDIT && $this->SHOW_EDIT){
					$trans='<span class="link-dark-blue loadME" data-ref="'.$this->PERMLINK.'edit_lang/'.$this->DATASET.'/'.urlencode($phrase).'" title="edit translation" >'.$trans.' <i class="fi-wrench"></i></span>';
				}
			}
		}
		return $trans;		
	}
//admin	
	private function translate($phrase,$language){
		if($phrase!=='' && $language!=='en'){
			$t['content']=GoogleTranslate::translate('en', $language, $phrase);
			$t['status']=200;
		}else{
			$t['content']=$phrase;
			$t['status']=500;
		}
		$t['target']='#gtrans';
		jsonResponse($t);
		die;
	}
	
	private function renderForm($action,$phrase=false){
		switch($action){
			case 'new_lang':
			case 'add_lang':
				$rec=$this->DEFAULT_REC[$this->DATASET];
				$faction='add_lang';
				$button='<i class="fi-plus"></i> Add Translation';
				$phrase=trim($phrase);
				break;
			case 'add_langx':
				$faction=$action;
				$button='<i class="fi-plus"></i> Add Translation';
				$rec=(is_string($phrase) && trim($phrase)!=='')?$this->getTranslation($phrase):$this->get('REC',$phrase);
				if(!$rec){
					$rec=$this->DEFAULT_REC[$this->DATASET];
					$rec['English']=$phrase;
				}
				break;
			case 'edit_lang':
				$rec=$this->get('REC',$phrase);
				$faction='update_lang';
				$button='<i class="fi-check"></i> Update Translation';
				break;
			case 'csv_update':
				$rec=false;
				$faction='update_csv';
				$button='<i class="fi-check"></i> Update '.$this->DATASET.' Database';
				break;
		}
		//form fields
		$hidden='<input type="hidden" name="dataset" value="'.$this->DATASET.'"/>';
		$parts=false;
		$new=false;
		if($action==='csv_update'){
			$parts.='<div class="callout primary"><ul><li>Paste or enter csv in the following format: <code>code_key,"english","french","german";</code></li><li>One record per line</li><li>Don\'t paste HTML here.</li></ul></div>';
			$parts.='<label class="text-maroon">CSV Data <textarea rows="10" name="csv_data" placeholder="csv data" wrap="off"></textarea></label>';
		}else{
			switch($this->DATASET){
				case 'words':
					if($action==='new_lang'){
						$parts.='<label class="text-maroon">English/Key <input name="key" type="text" placeholder="english_words" value="'.$phrase.'"/></label>';
					}else{
						$parts.='<label class="text-maroon">English <input type="text" value="'.ucME($phrase).'" disabled/></label>';
					}
					$parts.='<label class="text-dark-blue">French <input name="fr" type="text" value="'.$rec['fr'].'"/></label>';
					$parts.='<label class="text-black">German <input name="de" type="text" value="'.$rec['de'].'"/></label>';
					break;
				case 'phrases':
					if($action==='new_lang'){
						$new=array('key'=>'');
						$new+=$rec;
						$rec=$new;
					}
					$parts=$this->renderTabs($rec,$new);
					break;
				case 'contents':
					$rec['en']=base64_decode($rec['en']);
					$rec['fr']=base64_decode($rec['fr']);
					$rec['de']=base64_decode($rec['de']);
					if($action==='new_lang'){
						$new=array('key'=>'');
						$new+=$rec;
						$rec=$new;
					}
					$parts=$this->renderTabs($rec,$new);
					break;	
				default:
					$parts=msgHandler('Sorry, no dataset found...',false,false);
			}
		}
		if($this->AJAX) $parts='<div class="modal-body">'.$parts.'</div>';
		$tpl=file_get_contents(TEMPLATES.'app/app.form_lang_standards_ajax.html');
		$args=array(
			'form_url'=>$this->PERMLINK,
			'form_action'=>$faction,
			'form_parts'=>$hidden.$parts,
			'form_button'=>$button,
			'id'=>$phrase
		);
		return replaceMe($args,$tpl);
	}
	private function renderTabs($data,$new=false){
		$nav=$panels=$desc='';
		$active='is-active';
		$tab_id='lang-edit';
		$ct=0;
		foreach($data as $i=>$v){
			if(!$new && $i==='description'){
				$desc=$v;
				if($desc && $desc!=='') $desc='<div class="label bg-blue expanded">'.$desc.'</div>';
			}else{
				if($i==='key'||$i=='description'){
					$tmp='<input type="text" name="'.$i.'" id="edit-'.$i.'" value="'.$v.'"/>';
					$label=ucME($i);
				}else{
					$tmp='<textarea name="'.$i.'" id="edit-'.$i.'" class="qedit" >'.$v.'</textarea>';
					$label=$this->LANGUAGES[$i];
				}
				$nav.='<li class="tabs-title '.$active.'"><a href="#panel_'.$i.'" aria-selected="'.$active.'">'.$label.'</a></li>';
				$panels.='<div class="tabs-panel '.$active.'" id="panel_'.$i.'">'.$tmp.'</div>';
				$active='';
				$ct++;
			}
		}
		$tabs='<ul class="tabs" data-tabs id="'.$tab_id.'-tabs">'.$nav.'</ul><div class="tabs-content" data-tabs-content="'.$tab_id.'-tabs">'.$desc.$panels.'</div>';
		if($this->AJAX){
			$tabs.='<script>jQuery("#'.$tab_id.'-tabs").foundation();';
			if($this->DATASET==='contents') $tabs.='JQD.ext.initEditor(".modal-body .qedit");';
			$tabs.='</script>';
		}
		return $tabs;
	}
	
	private function initStandards($what=false,$save=false){
		$trans['first_name']=array('de'=>'Vorname','fr'=>'prénom');
		$trans['last_name']=array('de'=>'Nachname','fr'=>'nom de famille');
		$trans['email']=array('de'=>'Email','fr'=>'e-mail');
		$trans['age']=array('de'=>'Lebensdauer','fr'=>'age');
		$trans['birthday']=array('de'=>'Geburtstag','fr'=>'anniversaire');
		$trans['grade_date']=array('de'=>'Klasse Datum','fr'=>'aate de grade');
		$trans['grade_location']=array('de'=>'Klasse Standort','fr'=>'emplacement de grade');
		$trans['grade_country']=array('de'=>'Klasse Staat','fr'=>'grade - pays');
		$trans['postal_code']=array('de'=>'Postleitzahl','fr'=>'code postal');
		$trans['land_phone']=array('de'=>'Telefon','fr'=>'téléphone');
		$trans['mobile_phone']=array('de'=>'Handy','fr'=>'téléphone portable');
		$trans['country']=array('de'=>'Staat','fr'=>'Pays');
		$trans['username']=array('de'=>'Nutzername','fr'=>'nom d\'utilisateur');
		$trans['password']=array('de'=>'Passwort','fr'=>'mot de passe');
		$trans['confirm']=array('de'=>'Bestätigen','fr'=>'confirmer');
		$trans['address']=array('de'=>'Adresse','fr'=>'adresse');
		$trans['comments']=array('de'=>'Kommentar','fr'=>'commentaire');
		$trans['remarque']=array('de'=>'Bemerkung','fr'=>'remarque');
		$trans['dojo']=array('de'=>'Dojo','fr'=>'dojo');
		$trans['grade']=array('de'=>'Klasse','fr'=>'grade');
		$trans['zasha']=array('de'=>'Haltung','fr'=>'forme');
		$trans['gender']=array('de'=>'Geschlecht','fr'=>'le sexe');
		$trans['language']=array('de'=>'Sprache','fr'=>'la langue');
		$trans['participation']=array('de'=>'Beteiligung','fr'=>'participation');
		$trans['submit']=array('de'=>'senden','fr'=>'envoyer');
		$trans['home']=array('de'=>'Startseite','fr'=>'page d\'accueil');
		$trans['login']=array('de'=>'Einloggen','fr'=>'connexion');
		$trans['logout']=array('de'=>'Ausloggen','fr'=>'déconnexion');
		$trans['my_profile']=array('de'=>'Mein Profil','fr'=>'pon profil');
		$trans['events']=array('de'=>'Veranstaltungen','fr'=>'Événements');
		$trans['event']=array('de'=>'Veranstaltung','fr'=>'Événement');
		$trans['event_registration']=array('de'=>'Anmeldung','fr'=>'Enregistrement');
		$trans['grades']=array('de'=>'Klasse','fr'=>'Grade');
		$trans['details']=array('de'=>'Einzelheiten','fr'=>'détails');
		$trans['main_website']=array('de'=>'Hauptwebsite','fr'=>'site principal');
		$trans['date']=array('de'=>'Datum','fr'=>'Date');
		$trans['register_now']=array('de'=>'Jetzt registrieren','fr'=>'inscrire maintenant');
		$trans['no_information']=array('de'=>'Keine Information','fr'=>'aucune information');
		$trans['close']=array('de'=>'schließen','fr'=>'Fermer');
		$trans['reset_password']=array('de'=>'Passwort zurücksetzen','fr'=>'réinitialiser le mot de passe');
		$trans['reset']=array('de'=>'zurücksetzen','fr'=>'réinitialiser');
		$trans['yes']=array('de'=>'Ja','fr'=>'Oui');
		$trans['no']=array('de'=>'Nein','fr'=>'non');
		$trans['cancel']=array('de'=>'stornieren','fr'=>'Annuler');
		$trans['send_request']=array('de'=>'senden','fr'=>'envoyer');
		$trans['welcome']=array('de'=>'Herzlich Willkommen','fr'=>'Bienvenue');
		$trans['continue_in_german']=array('de'=>'Weiter auf deutsch','fr'=>'Continuer en allemand');
		$trans['continue_in_french']=array('de'=>'Weiter auf Französisch','fr'=>'Continuer en français');
		$trans['continue_in_english']=array('de'=>'Weiter auf Englisch','fr'=>'Continuer en anglais');
		$trans['select_language']=array('de'=>'Wähle eine Sprache','fr'=>'sélectionnez une langue');
		
		
		$phrase['login_tip']=array(
			'en'=>'Login to speed up the registration process.',
			'fr'=>'Connectez-vous pour accélérer le processus d\'inscription.',
			'de'=>'Melden Sie sich an, um den Registrierungsvorgang zu beschleunigen.',
		);
		$phrase['already_registered']=array(
			'en'=>'You are already registered for this event.',
			'fr'=>'Vous êtes déjà inscrit pour cet événement.',
			'de'=>'Sie sind bereits für diese Veranstaltung registriert.',
		);
		$phrase['no_events_found']=array(
			'en'=>'Sorry, no events found...',
			'fr'=>'Sorry, keine Events gefunden...',
			'de'=>'Désolé, aucun événement trouvé...',
		);
		$phrase['reset_info']=array(
			'en'=>'<strong>To reset your password...</strong><br/>Please enter the email address you have registered with us and we will send you an email with further instructions.',
			'fr'=>'<strong>Pour réinitialiser votre mot de passe...</strong><br/>S\'il vous plaît entrez l\'adresse email que vous avez enregistrée avec nous et nous vous enverrons un email avec des instructions supplémentaires.',
			'de'=>'<strong>Das Passwort zurücksetzen...</strong><br/>Bitte geben Sie die E-Mail-Adresse ein, die Sie bei uns registriert haben, und wir senden Ihnen eine E-Mail mit weiteren Anweisungen.',
		);
		$phrase['logout_info']=array(
			'en'=>'Sorry, no events found...',
			'fr'=>'Sorry, keine Events gefunden...',
			'de'=>'Désolé, aucun événement trouvé...',
		);
		$phrase['welcome_info']=array(
			'en'=>'This site is for members of AHK/SKV.<br/>For general information about Kyudo in Switzerland, please visit our main website at',
			'fr'=>'Ce site est destiné aux membres de AHK / SKV.<br/>Pour des informations générales sur le Kyudo en Suisse, veuillez visiter notre site web principal à',
			'de'=>'Diese Seite ist für Mitglieder von AHK / SKV.<br/>Allgemeine Informationen zu Kyudo in der Schweiz finden Sie auf unserer Hauptwebsite unter'
		);
		$phrase['site_name']=array(
			'en'=>'Swiss Kyudo Association',
			'fr'=>'Association Helvétique de Kyudo',
			'de'=>'Schweizerischer Kyudo Verband'
		);
		$phrase['no_content_found']=array(
			'en'=>'Sorry, no content found...',
			'fr'=>'Désolé, aucun contenu trouvé...',
			'de'=>'Sorry, kein Inhalt gefunden...'
		);
		$phrase['language_selected']=array(
			'en'=>'Okay, the language has been set.',
			'fr'=>'D\'accord, la langue a été définie.',
			'de'=>'Okay, die Sprache wurde eingestellt.'
		);
		
		$content['reminder_email']=array(
			'description'=>'Sent to members for reminding them about membership/subscriptions.',
			'en'=>'<h2>Hello {name},</h2><p>This is just a quick note to let you know that your subscription will be expiring soon.</p><ul><li>{item}</li><li>Start Date: {start}</li><li><strong>Expires on: {end}</strong></li><strong></strong></ul><p><strong></strong></p><table><tbody><tr><td align="center"><p><a href="{url}" class="button">Login at our site to renew your subscription</a></p></td></tr></tbody></table><p><strong></strong></p>',
			'fr'=>'<h2>Bonjour {name},</h2><p>Ceci est juste une note rapide pour vous informer que votre abonnement expirera bientôt.</p><ul><li>{item}</li><li>Date de début: {start}</li><li><strong>Date d\'expiration: {end}</strong></li><strong></strong></ul><p><strong></strong></p><table><tbody><tr><td align="center"><p><a href="{url}" class="button">Connectez-vous sur notre site pour renouveler votre abonnement</a></p></td></tr></tbody></table><p><strong></strong></p>',
			'de'=>'<h2>Hallo {name},</h2><p>Dies ist nur ein kurzer Hinweis, der Sie darauf hinweist, dass Ihr Abonnement bald ausläuft.</p><ul><li>{item}</li><li>Anfangsdatum: {start}</li><li><strong>Verfallsdatum: {end}</strong></li><strong></strong></ul><p><strong></strong></p><table><tbody><tr><td align="center"><p><a href="{url}" class="button">Melden Sie sich auf unserer Website an, um Ihr Abonnement zu erneuern</a></p></td></tr></tbody></table><p><strong></strong></p>'
		);
		$content['logged_registration_email']=array(
			'description'=>'Sent to user after completing an online registration.',
			'en'=>'<h2>Hello {name},</h2><p>This is just a quick note to let you know that your registration for the following event has been logged.</p><ul><li>{event_name}</li><li>Start Date: {event_date}</li></ul><p><strong></strong></p><table><tbody><tr><td align="center"><p><a href="{url}" class="button">Visit our site for more detials</a></p></td></tr></tbody></table><p><strong></strong></p>',
			'fr'=>'<h2>Bonjour {name},</h2><p>Ceci est juste une note rapide pour vous informer que votre inscription à l\'événement suivant a été enregistrée.</p><ul><li>{event_name}</li><li>Date de début: {event_date}</li></ul><p><strong></strong></p><table><tbody><tr><td align="center"><p><a href="{url}" class="button">Visitez notre site pour plus d\'informations</a></p></td></tr></tbody></table><p><strong></strong></p>',
			'de'=>'<h2>Hallo {name},</h2><p>Dies ist nur eine kurze Notiz, um Sie darüber zu informieren, dass Ihre Registrierung für das folgende Ereignis protokolliert wurde.</p><ul><li>{event_name}</li><li>Anfangsdatum: {event_date}</li></ul><p><strong></strong></p><table><tbody><tr><td align="center"><p><a href="{url}" class="button">Besuchen Sie unsere Website für weitere Informationen</a></p></td></tr></tbody></table><p><strong></strong></p>',
		);
		$content['notify_registration_email']=array(
			'description'=>'Sent to administrator after a user has completed an online registration.',
			'en'=>'<h2>Hello Admin.,</h2><p>This is just a quick note to let you know that a new registration has been received.</p><ul><li>{member_name}</li><li>{member_email}</li><li>{event_name}</li><li>Start Date: {start}</li></ul><p><strong></strong></p><table><tbody><tr><td align="center"><p><a href="{url}" class="button">Visit our site for more detials</a></p></td></tr></tbody></table><p><strong></strong></p>',
			'fr'=>'<h2>Bonjour Admin.,</h2><p>Ceci est juste une note rapide pour vous informer que votre abonnement expirera bientôt.</p><ul><li>{member_name}</li><li>{member_email}</li><li>{event_name}</li><li>Date de début: {event_date}</li></ul><p><strong></strong></p><table><tbody><tr><td align="center"><p><a href="{url}" class="button">Visitez notre site pour plus d\'informations</a></p></td></tr></tbody></table><p><strong></strong></p>',
			'de'=>'<h2>Hallo Admin.,</h2><p>Dies ist nur ein kurzer Hinweis, der Sie darauf hinweist, dass Ihr Abonnement bald ausläuft.</p><ul><li>{member_name}</li><li>{member_email}</li><li>{event_name}</li><li>Anfangsdatum: {event_date}</li></ul><p><strong></strong></p><table><tbody><tr><td align="center"><p><a href="{url}" class="button">Besuchen Sie unsere Website für weitere Informationen</a></p></td></tr></tbody></table><p><strong></strong></p>',
		);
		$content['email_footer']=array(
			'en'=>'<p>Sent by <a href="#">AHK/SKV</a>, Basel</p><p>Web: <a href="http://www.kyudo.ch">www.kyudo.ch</a></p>',
			'fr'=>'<p>Envoyée par<a href="#">AHK/SKV</a>, Basel</p><p>Web: <a href="http://www.kyudo.ch">www.kyudo.ch</a></p>',
			'de'=>'<p>Gesendet von <a href="#">AHK/SKV</a>, Basel</p><p>Web: <a href="http://www.kyudo.ch">www.kyudo.ch</a></p>'
		);		
		$content['email_header']=array(
			'en'=>'AHK/SKV: Member',
			'fr'=>'AHK/SKV: Membre',
			'de'=>'AHK/SKV: Mitglied'
		);

		switch($what){
			case 'words':	
				$this->DATA['words']=$trans;
				break;
			case 'phrases':
				$this->DATA['phrases']=$phrase;
				break;
			case 'contents':
				$this->DATA['contents']=$content;
				break;
			default:
				$save=false;
		}
		if($save){
			return $this->saveData($what);
		}
	}
}

