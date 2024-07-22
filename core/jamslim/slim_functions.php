<?php
//jamslim common functions

// singleton class:
/*
 * For any class you want to make a singleton just add the following method to the class.
 * METHOD: public static function Singleton(){return SingletonBase::Singleton(get_class());}
 * then get an instance using: $foo = {classname}::Singleton();
 */

abstract class SingletonBase{
    private static $storage = array();

    public static function Singleton($class){
        if(in_array($class,self::$storage)){
            return self::$storage[$class];
        }
        return self::$storage[$class] = new $class();
    }
    public static function storage(){
       return self::$storage;
    }
}
// exception handler
function exception_handler($ex){
	$err=new slimErrors;
	$err->setType('exception');
	$err->log($ex);
}
function error_handler($errno, $errstr, $errfile, $errline){
	if($errno==8){
		if(strpos($errstr,'unserialize()')!==false){
			//ignore unserialize errors - use safeUnserialize() instead of native unserialize()
			return false;
		}
	}	
	$err=new slimErrors;
	$err->setType('error');
	$err->log($errno, $errstr, $errfile, $errline);
}

// general helper functions

function preME($me, $echo = 1, $dbg = false) {
	$pre = getCallingFunction().'<br/>';
    $pre .= print_r($me, true);
    if ($dbg || $echo > 2) {
        $ignore = issetVar((int)$dbg, 2);
        $pre.='<br/>' . getBacktrace($ignore) . '<br/>';
    }
    $out = '<pre sytle="max-height:350px; overflow:auto;">' . $pre . '</pre>';
    if ($echo) {
        echo $out;
        if (in_array($echo, array(2, 4))) die;
    }else {
        return $out;
    }
}

function getBacktrace($ignore = 2) {
    $trace = '';
    $item=array();
    $err=error_reporting();
    error_reporting(0);
    foreach (debug_backtrace() as $k => $v) {
        if ($k < $ignore) {
            continue;
        }
		$tmp='';
		foreach($v['args'] as $a=>$b){
			if(is_array($b)){
				if(count($b)>5){
					$tmp.=', [array]';
				}else{
					$tx=current($b);
					if(is_array($tx)){
						$tmp.=', ['.key($b).'][array]';
					}else{
						$tmp.=(is_array($b))?'JSON: '.json_encode($b):$b;
					}
				}
			}else if(is_string($b)){
				$tmp.=', '.$b;
			}
		}
        $trace .= '#' . ($k - $ignore) . ' ' . $v['file'].'(' . $v['line'] . '): ';
        $trace .= (isset($v['class']) ? $v['class'] . '->' : '') . $v['function'] . '(' . $tmp. ')' . "\n";
    }
	error_reporting($err);
    return $trace;
}

function getCallingFunction($completeTrace=false){
	$trace=debug_backtrace();
	$str = '';
	if($completeTrace){
		foreach($trace as $caller){
			$str .= " -- Called by {$caller['function']}";
			if (isset($caller['class'])) $str .= " From Class {$caller['class']}";
		}
	}else{
		if(isset($trace[2])){
			$caller=$trace[2];
			$str='Called by ';
			if (isset($caller['class']))$str .= "class:{$caller['class']}->";
			$str .= $caller['function'].' @ line #'.$trace[1]['line'];
		}else{
			$str='Called by ';
			$str .= $trace[1]['file'].' @ line #'.$trace[1]['line'];
		}
	}
    return $str;
}
function isRecursive($array) {
    foreach($array as $v) {
        if($v === $array) {
            return true;
        }
    }
    return false;
}
function issetVar($var=null,$default=false,$strict=true){
	$out=$default;
	if(!is_null($var)){
		if($strict){
			if($var && !empty($var) && $var!=='') $out=$var;
		}else{
			$out=$var;
		}
	}
	return $out;
}
function issetCheck($arr=false,$key=false,$default=false) {
	//for arrays only!!
	$return = $default;
	if(is_array($arr) && !empty($arr)){
		if(is_string($key)||is_integer($key)){
			$return = (isset($arr[$key]))? $arr[$key]:$default;
		}
	}
	return $return;
}
function issetOR($var, $default = false,$asString=false,$debug=false) {
	return issetVar($var,$default);
}

function formatURL($opts,$dbug=false){
	//from tbs4
	global $config;
	$slug=issetCheck($opts,'page','home');
	if($config['URL_TYPE']==='clean'){
		return URL.PAGE_SLUG.'/'.$slug;
	}else{
		return URL.$config['HOME_PAGE'].'?'.PAGE_SLUG.'='.$slug;
	}
}
function customerServices($subject=false,$email=false,$text=false){
   if(!$email){
	    $email='info@kyudousa.com';
   }
   if($subject){
	  $email.='&amp;subject='.$subject;   
   }
   if(!$text) $text='Customer Services';
   return '<a class="csLink" href="mailto:'.$email.'">'.$text.'</a>';
}

function compress($data, $pack = 1) {
    if (!$data) return false; //die('Compress Error: no data received');
    if ($pack===2) {//???
        $out = unserialize(base64_encode($data));
    }else if ($pack) {//encode
        $out = base64_encode(serialize($data));
    } else {//decode A
        $out = unserialize(base64_decode($data));
    }
    return $out;
}

function checkAdmin($perm=false) {
    //check if user admin and has permission if requested
    $userArray=getMySession();
    $usrSecurity = (int)issetCheck($userArray,"access");
    $chk = ($usrSecurity >= 3) ? 1 : 0;
    if($chk && $usrSecurity==3 && $perm){
		$chk=(int)issetCheck($userArray['permissions']['perms'],$perm,0);
	}
    return $chk;
}
function hasAccess($user=false,$what=false,$level=false){
	$state=false;
	if(!$user){
		//no
	}else if($user['access']>=25){
		$state=true;
	}else{
		$perms=issetCheck($user,'permissions');
		if($perms){
			if($chk=issetCheck($perms,$what)){
				$lv=issetCheck($chk,$level,0);
				if($lv) $state=true;
			}				
		}				
	}
	return $state;
}	

function getMySession($update=true) {
    //keeps user session alive
    $sesh=issetCheck($_SESSION,'userArray');
    $now = time();
    $exp=$now + (30 * 60);
    if($sesh){
		$expire=(int)issetCheck($sesh,'expire');		
		if(!array_key_exists('expire',$sesh)){//logged in on alpha site
            $sesh['expire'] = $exp;
		}else if ($now > $expire) {
            session_destroy();
            session_start();
            $url = URL.'page/login';
            $msg='Sorry, your session expired!... please login.';
            setSystemResponse($url,$msg);
            die("Your session has expired! <a href='" . $url . "'>Login here</a>");
        } else {
            $sesh['expire'] = $exp;
        }
    }else{
		$sesh=['id'=>0,'access'=>0,'name'=>'Guest','language'=>'en','expire'=>$exp];
	}
	if($update) $_SESSION["userArray"]=$sesh;
    return $sesh;
}

function setMySession($what=false,$vars=false){
	$sesh=getMySession(false);
	if($what){		 
		$sesh[$what]=$vars;
		$sesh['expire'] = time() + (30 * 60);//update timeout
        $_SESSION["userArray"] = $sesh;
        return true;
 	}
 	return false;
}

function slimSession($what=false,$vars=false){
	$SESSION=slimSession::Singleton();
	return $SESSION->session($what,$vars);
}

function setSystemResponse($url,$msg = false,$msg_name=false,$script=false) {
	if ($url) {
		if ($msg){
			if(!$msg_name) $msg_name='sysMSG';
			$args['key']='sysMSG';
			$args['value']=$msg;
			$_SESSION["jamSlim"][$msg_name] = $msg;
			if($script) $_SESSION["jamSlim"]['sysJS']=$script;
		}
		header('Location:' . $url);
		exit;
	}
}
function getSystemResponse($msg_name='sysMSG',$session=false) {
	$set=is_object($msg_name);
	if($set){
		$set=$msg_name;
		$msg_name='sysMSG';
	}
	if(!is_string($session) || $session==='') $session='jamSlim';
	$slim=issetCheck($_SESSION,$session);
	if($slim){
		if($script=issetCheck($slim,'sysJS')){
			unset($_SESSION[$session]['sysJS']);
			global $container;
			$container->assets->set('js',$script,'sysJS');
		}		
		$msg=issetCheck($slim,$msg_name);
		if($msg){
		   if($set) setMessage($set,$msg);//for tweakers
		   unset($_SESSION[$session][$msg_name]);
		   return $msg;
		}
	}
	return false;
}
function confetti(){
	global $container;
	$device=$container->router->get('device');
	$out=false;
	if($device==='classic'){
		$chk=(int)$container->Options->get('site_login_confetti','value');
		if($chk) $out='JQD.utils.throwConfetti();';
	}
	return $out;
}
function ucME($str=false){
	if(is_string($str)){
		$fix=str_replace(array('-','_'),' ',$str);
		return ucwords($fix);
	}
	return false;
}
function camelTo($value,$separator = ' '){
    if (!is_scalar($value) && !is_array($value)) {
        return $value;
    }
    if (defined('PREG_BAD_UTF8_OFFSET_ERROR') && preg_match('/\pL/u', 'a') == 1) {
        $pattern     = array('#(?<=(?:\p{Lu}))(\p{Lu}\p{Ll})#', '#(?<=(?:\p{Ll}|\p{Nd}))(\p{Lu})#');
        $replacement = array($separator . '\1', $separator . '\1');
    } else {
        $pattern     = array('#(?<=(?:[A-Z]))([A-Z]+)([A-Z][a-z])#', '#(?<=(?:[a-z0-9]))([A-Z])#');
        $replacement = array('\1' . $separator . '\2', $separator . '\1');
    }
    return preg_replace($pattern, $replacement, $value);
}

function slugMe($str=false,$separator='-'){
	$slug=false;
	if(is_string($str) && $str!=='' ){
		$accents_regex = '~&([a-z]{1,2})(?:acute|cedil|circ|grave|lig|orn|ring|slash|th|tilde|uml);~i';
		$special_cases = array( '&' => 'and', "'" => '');
		$slug = mb_strtolower( trim( $str ), 'UTF-8' );
		$slug = str_replace( array_keys($special_cases), array_values( $special_cases), $slug );
		$slug = preg_replace( $accents_regex, '$1', htmlentities( $slug, ENT_QUOTES, 'UTF-8' ) );
		$slug = preg_replace("/[^a-z0-9]/u", "$separator", $slug);
		$slug = preg_replace("/[$separator]+/u", "$separator", $slug);
   	}
   	return $slug;
}
function limitText($text=false,$wordCount=25,$trail=false){
	 $clean=strip_tags($text,'<span><br>');
	 $wordArray = explode(" ", $clean);
	 $count=count($wordArray);
	 if($count<($wordCount/2)){
		$output=$clean;
	 }else if($count<=$wordCount){
		$output=$clean;
	 }else{
		$output=array_slice($wordArray,0, $wordCount);
		$output=implode(" ", $output);
		if($trail)$output.='...';
	 }
	 return $output;
}

function limitChar($args){
	 $text=false;$charCount=50;$trail=false;
	 extract($args);
	 $clean=strip_tags($text);
	 $count=strlen($clean);
	 if($count<=$charCount){
		$output=$clean;
	 }else{
		$output=substr($clean,0, $charCount);
		if($trail)$output.='...';
	 }
	 return $output;
}

function msgHandler($msg,$type = false,$close=true,$class=false) {
    $alertbox = array('alert', 'info','warning','success', 'message', 'secondary');
    $check_type=function($a,$b){
		foreach($b as $v){
			if(mb_strpos($a, $v)!==false) return true;
		}
		return false;
	};
    if($class){
		$boxClass=$class;
	}else{
		$boxClass='callout';
	}
    if (!$type) {
		if($check_type($msg, array('Sorry,','Désolé,'))){
			$type = 'alert';
		}else if($check_type($msg,array('Okay,','Ok,',"D'accord,"))){
			$type = 'success';
		}else{
			$type = 'primary';
		}
    }
    $closer='<button class="close-button" aria-label="Close alert" type="button" data-close><span aria-hidden="true">&times;</span></button>';
    $closer_attr='data-closable="slide-out-left"';
    if(!$close) $closer=$closer_attr='';
	$output[] = "<p class='text'>$msg</p>";
	$box ='<div class="'.$boxClass.' '.$type.'" '.$closer_attr.'>'.$closer.implode("\n",$output).'</div>';
    return $box;
}

function getFileContents($path=false,$unpack=false){
	 $data=null;
	 if($data=file_get_contents($path)){
		if($unpack) $data=compress($data,0);
	 }
	 return $data;
}

function setFileContents($path=false,$data,$pack=false){
	 $out=false;
	 $path=strtolower(str_replace(' ','_',$path));
	 if($data){
		if($pack) $data=compress($data);
		if(file_put_contents($path,$data))$out=true;
	 }
	 return $out;
}

function login($args=false,$slim=false) {
	$LGN = new slimLogin($slim);
	$_response=array('status'=>500,'message'=>'bad request');
	$res=$LGN->Process($args['action'],$args);
	if(issetCheck($res,1)) 	$_response['status']=200;
	$_response['message']=$res[0];
	return $res;
}
//array functions 
function rekeyArray($arr,$new_key){
	$out=[];
	if(is_array($arr) && $new_key!==''){
		foreach($arr as $i=>$v){
			if($k=issetCheck($v,$new_key)){
				$out[$k]=$v;
			}
		}
	}
	return $out;
}
function array_prepend($arr, $key, $val){
    $arr = array_reverse($arr, true);
    $arr[$key] = $val;
    return array_reverse($arr, true);
} 	

function in_arrayi($needle, $haystack) {
    //a case-insensitive version in_array();
    return in_array(strtolower($needle), array_map('strtolower', $haystack));
}

function in_array_like($referencia, $array) {
    foreach ($array as $ref) {
        if (strstr($referencia, $ref)) {
            return true;
        }
    }
    return false;
}

function natksort($array) {
	// Like ksort but uses natural sort instead
	$keys = array_keys($array);
	natsort($keys);

	foreach ($keys as $k)  $new_array[$k] = $array[$k];

	return $new_array;
}

function array_find($needle,$haystack,$type='normal',$ret='value'){
	//search an array and return the value
	$AS=new arraySearch;
	$chk=$AS->find($needle,$haystack,$ret,$type);
	if($chk['response']==200 && isset($chk['data'][0])) return $chk['data'][0];
	return false;
}

function fixNewLine($str){
   return str_Replace('\n',PHP_EOL,$str);	
}

function fixNL($str){
	$str=(string)$str;
	$str=nl2br($str);
	$str=str_replace('\r\n','<br/>',$str);
	$str=str_replace('\n','<br/>',$str);
	return $str;
}

function replaceMe($data=false,$tpl=false,$pat=false){
	$debug=false;
	if(is_array($data) && is_string($tpl)){
		$start='{';
		$end='}';
		if($pat && strpos($pat,'??')!==false){
			$tmp=explode('??',$pat);
			$start=$tmp[0];
			$end=$tmp[1];
		}
		foreach($data as $i=>$v){
			if(is_array($v)){
				if($debug) preME($data,2);
				continue;
			}else if(is_null($v)){
				$v='';
			}
			$tpl=str_replace($start.$i.$end,$v,$tpl);
		}
	}
	return $tpl;
}

function jsonResponse($arr=false,$echo=true){
	if($arr){
		$j=json_encode($arr);
		if($echo){
			header('Content-Type: application/json');
			echo $j;
			die;
		}
		return $j;
	}
}
if(!function_exists('renderResultsORM')){
	function renderResultsORM($results=false,$rekey=false){
		$data=[];
		if(is_object($results)){
			$res=array_map('iterator_to_array', iterator_to_array($results));
			$data=($rekey)?rekeyArray($res,$rekey):$res;
		}else{
			$data='Error: $results must be an ORM object.';
		}
		return $data;
	}
}
function renderSelectOptions($data=[],$selected=null,$add_zero=false,$name_val=false){
	$h='';
	if($data){
		if($add_zero) $h='<option>None</option>';
		foreach($data as $i=>$v){
			$lbl=$v;
			if(is_array($v)){				
				$lbl=issetCheck($v,'OptionName');
				if(!$lbl) $lbl=issetCheck($v,'MetaValue');
				if(!$lbl) $lbl=issetCheck($v,'LocationID');
				if(is_array($lbl)) preME([$i,$lbl],2);
			}
			$sel=($selected==$i)?'selected':'';
			$value=($name_val)?$lbl:$i;
			$h.='<option value="'.$value.'" '.$sel.'>'.$lbl.'</option>';				
		}
	}else{
		$h='<option>no options found</option>';
	}
	return $h;	
}
function renderDataTable($th,$data,$tid='dTable'){
	global $container;
	$filter='<div class="table_filter">'.$container->zurb->inlineLabel('Filter','<input id="'.$tid.'_filter" class="input-group-field" type="text"/>');
	$filter.='<div class="metrics">'.(count($data)).' Record(s)</div>';
	$filter.='</div>';
	$table='<div class="tablewrap large"><table id="'.$tid.'" class="dataTable filterme"><thead><tr>'.$th.'</tr></thead><tbody>'.implode('',$data).'</tbody></table></div>';
	$container->assets->set('js','JQD.ext.initMyTable("#'.$tid.'_filter","#'.$tid.'");','datatable');
	return $filter.$table;
}
function renderInfoTable($th,$data,$tid='dTable'){
	$table='<table id="'.$tid.'" class="dataTable"><thead><tr>'.$th.'</tr></thead><tbody>'.implode('',$data).'</tbody></table></div>';
	return $table;
}
function makeInfobox($args,$slim=false){
	$img=issetCheck($args,'src');
	$title=issetCheck($args,'title');
	if($img) $img='<img style="display:block;" src="'.$img.'"/>';
	$data=array(
		'head_color'=>false,//color class
		'body_color'=>false,//color class 
		'header'=>strip_tags($title),
		'body'=>$img.issetCheck($args,'text'),
		'footer'=>false,
		'width'=>4,// foundation column
		'ref'=>issetCheck($args,'url'),
		'link_type'=>issetCheck($args,'link_type',1),
		'box_class'=>false,
		'box_props'=>false,
		'box_id'=>false,
	);
	$box = new infoGrid_box($data,$slim);
	$out= $box->render();
	return $out;
}
		
function makeInfobox_list($args){
	$out='';
	$unset=array('icon','listclass','linkclass','listid');
	foreach($unset as $u){
		if(issetCheck($args,$u)) unset($args[$u]);
	}
	foreach ($args as $rec) $out.="\n".$rec['content'];
	$css='<link rel="stylesheet" href="css/mp4/infoGrid.css">';
	$out = $css.'<div class="row infogrid small-collapse medium-uncollapse">' . $out . '</div>';
	return $out;
}

function renderMediaBox($content=false,$src=false,$url=false,$click=false,$stack=false){
	$tpl=false;
	if($stack) $stack='stack';
	if($url){
		$url='data-ref="'.$url.'"';
		$click=($click==='loadME')?'loadME':'gotoME';
	}
	if($src) $src='<div class="thumb"><img src="'.$src.'"></div>';
	if($content || $src){
		$tpl='<div class="media-object '.$click.' '.$stack.'" '.$url.'><div class="media-object-section">'.$src.'</div><div class="media-object-section">'.$content.'</div></div>';
	}
	return $tpl;
}
function renderMediaCard($content=false,$src=false,$url=false,$click=false,$stack=false){
	$tpl=false;
	if($stack) $stack='stack';
	if($url){
		$url='data-ref="'.$url.'"';
		$click=($click==='loadME')?'loadME':'gotoME';
	}
	if($src) $src='<div class="thumb"><img src="'.$src.'"></div>';
	if($content || $src){
		$tpl='<div class="media-card '.$click.'" '.$url.'><div class="media-card-thumbnail">'.$src.'</div><div class="media-card-content">'.$content.'</div></div>';
	}	
	return $tpl;
}
function renderHeroCard($content=false,$src=false,$url=false,$click=false,$stack=false){
	$tpl=false;
	if($stack) $stack='stack';
	if($url){
		$url='data-ref="'.$url.'"';
		$click=($click==='loadME')?'loadME':'gotoME';
	}
	if($src) $src='<div class="hero-card-image" ><img src="'.$src.'"/></div>';
	if($content || $src){
		$tpl='<div class="card hero-card '.$click.'" '.$url.'>'.$src.'<div class="hero-card-content">'.$content.'</div></div>';
	}	
	return $tpl;
}
function renderHeroImage($content=false,$src=false,$url=false,$click=false,$stack=false){
	$tpl=false;
	if($stack) $stack='stack';
	if($url){
		$url='data-ref="'.$url.'"';
		$click='loadIMG';
	}
	if($src) $src='<div class="hero-card-image" ><img src="'.$src.'"/></div>';
	if($content || $src){
		$tpl='<div class="card hero-card '.$click.'" '.$url.'>'.$src.'<div id="heroCaption" class="hero-card-content">'.$content.'</div></div>';
	}	
	return $tpl;
}
function renderMediaTable_row($content=[],$src=false,$url=false,$click=false,$stack=false){
	$tpl=$button=$title=$author=$publisher=$price=$isbn=false;
	if($stack) $stack='stack';
	if($url){
		$url='data-ref="'.$url.'"';
		$click=($click==='loadME')?'loadME':'gotoME';
		$button='<button title="view details" class="button small button-gbm-blue '.$click.'" '.$url.'><i class="fi-eye"></i> View</button>';
	}
	if($content){
		extract($content);
		if($src) $src='<img class="table-thumb" src="'.$src.'">';
		$tpl='<tr><td>'.$src.'</td><td><strong class="text-gbm-blue">'.$title.'</strong><br/>'.$author.'<br/>'.$isbn.'</td><td>'.$publisher.'<br/>'.$price.'</td><td>'.$button.'</td></tr>';
	}	
	return $tpl;
}
function renderSongCard($args=false){
	if(!is_array($args)) return false;
	$tpl='<div class="cell song-card {info} {load}" {url} {style}><div class="grid-x">
	<div class="cell small-1 medium-2 song-index h2">{index}</div>
	<div class="cell small-2 song-thumb show-for-medium">{thumb}</div>
	<div class="cell small-11 medium-8">{content}</div>
	</div>{overlay}</div>';
	$thumb='<i class="fi-sound icon-x2 text-olive"></i>';
	$overlay='<div class="song-overlay">{overlay}</div>';
	$info=false;
	foreach($args as $key=>$val){
		switch($key){
			case 'overlay':
				if(trim($val)!==''){
					$val=str_replace('{'.$key.'}',$val,$overlay);
					$info=true;
				}
				$tpl=str_replace('{'.$key.'}',$val,$tpl);
				break;
			case 'thumb':
				if(trim($val)=='') $val=$thumb;
				$tpl=str_replace('{'.$key.'}',$val,$tpl);
				break;
			case 'url':
				if(trim($val)!==''){
					$val='data-ref="'.$val.'"';
					if(!isset($args['load'])) $tpl=str_replace('{load}','loadME',$tpl);
				}
				$tpl=str_replace('{'.$key.'}',$val,$tpl);
				break;
			case 'style':
				if(trim($val)!==''){
					$val='style="'.$val.'"';
				}
				$tpl=str_replace('{'.$key.'}',$val,$tpl);
				break;
			case 'load':
				$tpl=str_replace('{'.$key.'}',$val,$tpl);
				break;
			default:
				$tpl=str_replace('{'.$key.'}',$val,$tpl);
		}
	}
	//info
	$val=($info)?'info':'';
	$tpl=str_replace('{info}',$val,$tpl);
	//cleanup
	$tpl=str_replace(['{url}','{load}','{style}'],'',$tpl);
	return $tpl;
}
function renderCard($args=[]){
	$parts['title']='- no header -';
	$parts['content']='<div class="callout warning">No Content??...</div>';
	$parts['image']=false;
	$parts['card_class']=false;
	$parts['card_id']=false;
	foreach($args as $i=>$v) $parts[$i]=$v;
	$parts['title']=renderCardHead($parts);
	if($parts['card_id']) $parts['card_id']='id="'.$parts['card_id'].'" ';
	$tpl='<div {card_id}class="card {card_class}">{title}{image}<div class="card-section main">{content}</div></div>';
	return replaceME($parts,$tpl);
}

function renderCard_active($title=false,$content=false,$controls=false,$card_class=false,$card_id=false,$card_data=false,$ibar=false,$head_class=false){
	if($card_id) $card_id='id="'.$card_id.'"';
	if($ibar) $ibar='<div class="bg-navy">'.$ibar.'</div>';
	$tbar='<div class="top-bar '.$head_class.'"><div class="top-bar-left"><ul class="menu"><li class="title">'.$title.'</li></ul></div><div class="top-bar-right"><ul class="menu"><li><div class="button-group small">'.$controls.'</div></li></ul></div></div>'.$ibar;
	$card='<div class="card '.$card_class.'" '.$card_id.' '.$card_data.'>'.$tbar.'<div class="card-section main">'.$content.'</div></div>';
	return $card;	
}

function renderCardHead($args=false){
	$tpl['active']='<div class="top-bar"><div class="top-bar-left"><ul class="menu"><li class="title">{title}</li></ul></div><div class="top-bar-right"><ul class="menu"><li><div class="button-group small">{controls}</div></li></ul></div></div>';
	$tpl['basic']='<div class="card-divider title">{title}</div>';
	$title=issetCheck($args,'title','???');
	$controls=issetCheck($args,'controls');
	if(is_string($controls) && $controls!==''){
		$t=str_replace('{title}',$title,$tpl['active']);
		$t=str_replace('{controls}',$controls,$t);
	}else if(is_array($controls)){
		$t=str_replace('{title}',$title,$tpl['active']);
		$t=str_replace('{controls}',implode('',$controls),$t);
	}else{
		if(!$title){
			$t='';
		}else{
			$t=str_replace('{title}',$title,$tpl['basic']);
		}
	}
	return $t;
}
function confirmDelete($question=false,$url=false,$button=false){
	$buts['close']='<a class="button secondary" data-close=""><i class="fi-x-circle"></i> No, maybe later</a>';
	if($question && $url){
		if($button) $buts['extra']=$button;
		$content='<div class="callout alert">'.$question.'</div>';
		$buts['delete']='<button class="button button-red gotoME float-right" data-ref="'.$url.'"><i class="fi-trash"></i> Yes, do it now!</a>';
	}else{
		$content='<div class="callout warning"><p>Sorry, no confirmation detials found...<br/>Please try again.</p></div>';
	}
	$content.='<div class="fcontrols">'.implode('',$buts).'</div>';
	$parts['title']='Confrim Deletion';
	$parts['content']=$content;
	return renderCard($parts);	
}
function renderMemberSelector($data=[],$ref=0,$url=false){
	$fill=[];
	$Z=new Zurb;
	$tpl=file_get_contents(APP.'templates/ng_select_members.html');
	$fill['hidden'].='<input type="hidden" name="ref" value="'.$ref.'"/>';
	$fill['form_url']=($url)?$url:URL.'api/eventslog/add_members/'.$ref;
	
	foreach($data as $i=>$v){
		$chkbox='<div class="checkboxTick"><input type="checkbox" value="'.$v['MemberID'].'" id="cbk_'.$v['MemberID'].'" name="member[]" /><label for="cbk_'.$v['MemberID'].'"></label></div>';
		$r='<td>'.$v['FirstName'].' '.$v['LastName'].'<br/>'.$v['Sex'].'</td>';
		$r.='<td>'.$v['Dojo'].'<br/>'.$v['CGradeName'].'</td>';
		$r.='<td>'.$chkbox.'</td>';
		$row[]='<tr class="visible">'.$r.'</tr>';														
	}
	$fill['rows']=implode('',$row);
	foreach($fill as $i=>$v) $tpl=str_replace('{'.$i.'}',$v,$tpl);
	return $tpl;
}
function renderTickbox($args=false){
	if(!is_array($args)) return '<div class="bg-red"> Invalid Arguments</div>';
	$id=$name=false;
	extract($args);
	return '<div class="tickbox"><input type="checkbox" id="'.$id.'" name="'.$name.'"><label for="'.$id.'"></label></div>';
}
function renderTabs($args=false){
    $tabs['panelwrap'] = '<div class="tabs-content" data-tabs-content="tabs-{wid}">{panels}</div>';
    $tabs['panel'] = '<div id="panel-{id}" class="tabs-panel {active}">{content}</div>';
    $tabs['nav'] = '<li class="tabs-title {active}"><a href="#panel-{id}">{title}</a></li>';
    $tabs['navwrap'] = '<div class="tabs" id="tabs-{wid}" data-tabs>{nav}</div>';
    $tabs['wrapper'] = '<div class="tabs-wrapper">{nav}{panels}</div>';
    $nav=$panel=false;
    $ct=0;
    if(is_array($args)){
		foreach($args as $i=>$v){
			if(is_array($v)){
				extract($v);
			}else{
				$title=ucME($i);
				$content=$v;
			}
			$active=($ct)?'':'is-active';
			$tnav=str_replace('{id}',$i,$tabs['nav']);
			$tnav=str_replace('{title}',$title,$tnav);
			$tnav=str_replace('{active}',$active,$tnav);
			$tpan=str_replace('{id}',$i,$tabs['panel']);
			$tpan=str_replace('{content}',$content,$tpan);
			$tpan=str_replace('{active}',$active,$tpan);
			$nav.=$tnav;
			$panel.=$tpan;
			$ct++;
		}
		$nav=str_replace('{nav}',$nav,$tabs['navwrap']);
		$panel=str_replace('{panels}',$panel,$tabs['panelwrap']);
		$out=str_replace('{panels}',$panel,$tabs['wrapper']);
		$out=str_replace('{nav}',$nav,$out);
		$out=str_replace('{wid}',time(),$out);		
	}else{
		$out=msgHandler('Sorry, the tab data is invalid...',false,false);
	}
	return $out;
}
function renderNotFound($msg=false){
	$search='<form class="searchForm" method="get" action="'.URL.'page/site-search/"><div class="input-group"><input class="input-group-field" name="sitesearch" type="text" placeholder="Search our site"><div class="input-group-button"><input type="submit" class="submitSearch button button-gbm-blue" value="Search"></div></div></form>';
	$out='<div class="grid-x grid-margin-x widthlock"><div class="cell"><div class="blog-post"><h2>This is not the page you are looking for...</h2>'.$msg.'<h3>What Now?</h3><p>You can select something from the main menu, or you could try searching our site. (maybe it\'s in here somewhere...)</p>'.$search.'<p>You might also find what you want from our <a href="'.URL.'page/home">homepage</a></p></div></div></div>';
	return $out;
}
function renderNiceError($msg=false){
	$email='carly@mnkyudo.org';
	$search='<form class="searchForm" method="get" action="'.URL.'page/site-search/"><div class="input-group"><input class="input-group-field" name="sitesearch" type="text" placeholder="Search our site"><div class="input-group-button"><input type="submit" class="submitSearch button button-gbm-blue" value="Search"></div></div></form>';
	$buttons='<button class="button" onclick="window.history.back()"><i class="fi-arrow-left"></i> Back to previous page</button><a class="button button-olive" href="'.URL.'page/home"><i class="fi-home"></i> Go to Homepage</a>';
	if(!$msg){
		$msg='<p class="callout warning">I\'m, not quiet sure what went wrong... sorry about that.</p>';
	}else{
		$msg=msgHandler($msg,false,false);
	}
	$out='<div class="grid-x grid-margin-x widthlock"><div class="cell"><div class="blog-post"><div class="panel"><h2><i class="fi-alert"></i>There\'s been a glitch...</h2>'.$msg.'<h3>What Now?</h3><p>You can go back, or start again from our home page.</p><div class="button-group">'.$buttons.'</div><p>If you are still having problems, please let us know via email <a href="mailto:'.$email.'">'.$email.'</a> (include a screenshot if possible.)</p></div></div></div></div>';
	return $out;
}
function langInit($lang='en'){
	Language::set('INIT',$lang);
}
function lang(){
	global $container;
	//example args ('hello %s! welcome to %s','billy','home')
	$args=func_get_args();
	$res[0]=$container->language->lang($args[0]);
	return call_user_func_array('sprintf',$res);
}


function sortArrayBy($array=false, $key=false,$dir='a',$set_locale=false){
	if($set_locale){
		$originalLocales = explode(";", setlocale(LC_ALL, 0));
		setlocale(LC_ALL, $set_locale);//eg: $set_locale="nb_NO.utf8"
	}
	if(is_array($array)){
		//check that key exists
		$tmp=current($array);
		$chk=array_key_exists($key,$tmp);
		if($chk){
			if($key){
				if($dir==='d'){
					uasort($array, function($a, $b) use($key){return strnatcmp($b[$key],$a[$key]);});
				}else{
					uasort($array,function($a, $b) use($key){return strnatcmp($a[$key], $b[$key]);});
				}
			}
		}	
	}
	return $array;
}
/**
 * @param string    $str           Original string
 * @param string    $needle        String to trim from the end of $str
 * @param bool|true $caseSensitive Perform case sensitive matching, defaults to true
 * @return string Trimmed string
 */
function rightTrim($str, $needle, $caseSensitive = true){
    $strPosFunction = $caseSensitive ? "strpos" : "stripos";
    if ($strPosFunction($str, $needle, strlen($str) - strlen($needle)) !== false) {
        $str = substr($str, 0, -strlen($needle));
    }
    return $str;
}

/**
 * @param string    $str           Original string
 * @param string    $needle        String to trim from the beginning of $str
 * @param bool|true $caseSensitive Perform case sensitive matching, defaults to true
 * @return string Trimmed string
 */
function leftTrim($str, $needle, $caseSensitive = true){
    $strPosFunction = $caseSensitive ? "strpos" : "stripos";
    if ($strPosFunction($str, $needle) === 0) {
        $str = substr($str, strlen($needle));
    }
    return $str;
}


function getAge($dob=false,$date=false){
	if(!$dob) return 0;
	if(!$date) $date=date('Y-m-d');
	return date_create($dob)->diff(date_create($date))->y;
}
function shortDate($strDate=false){
	if(issetVar($strDate)){
		return validDate($strDate);
	}
	return '-';
}
function validDate($strDate=false,$format=true,$uk=false){
	if(!$strDate||strlen(trim($strDate))<10) return false;
	try{
		if($uk) $strDate=str_replace('/','-',$strDate);
		$d=new DateTime($strDate);
		if($format){
			if(!is_string($format)){
				$format='Y-m-d';
			}
			return $d->format($format);
		}else{
			return true;
		}		
	}catch(Exception $e){
	    //echo $e->getMessage();
		//exit(1);
		return false;
	}
}

function activeRecordsButton($_route=false,$return='button'){
	$out=false;
	$color='amber';
	$icon='eye';
	$no_button=array('members/list/sleeping','members/list/inactive','members/list/active');
	if(is_array($_route)){
		$route=$_route;
		$kall=array_search('all',$route);
		$kinactive=array_search('inactive',$route);
		$kactive=array_search('active',$route);
		if($kall===false && $kinactive===false){
			if($kactive!==false) unset($route[$kactive]);
			$route[]='all';
			$label='All Members';
		}else{
			if($kall!==false) unset($route[$kall]);
			if($kinactive!==false) unset($route[$kinactive]);
			$label='Active Members';
		}
		if($return==='button'){
			$url=implode('/',$_route);		
			if($route[0]==='events'||$route[2]==='grade'||$route[0]==='help'||$route[0]==='users'||$route[0]==='options'||in_array($url,$no_button)){
				$out=false;
			}else{
				$out='<button class="small button button-'.$color.' gotoME" data-ref="'.URL.implode('/',$route).'"><i class="fi-'.$icon.'"></i> '.$label.'</button>';
			}
		}else{
			$out=($label==='View All')?true:false;
		}
	}
	return $out;	
}

function getPermalink($route=false){
	if(is_array($route) && !empty($route)){
		$t=array();
		$ct=0;
		$perm['back']=URL;
		foreach($route as $i=>$v){
			if(!empty($v)){
				$t[]=$v;
				$ct++;
				if($ct==1) $perm['back']=URL.implode('/',$t).'/';
			}
		}
		$perm['link']=URL.implode('/',$t).'/';
	}else{
		$perm['link']=$perm['back']=URL;
	}
	return $perm;	
}
function float_val($i=false){
	return preg_replace("/[^-0-9\.]/","",$i);
}
function isFloat($value){
	if(!is_numeric($value)) $value=float_val($value);
	if($value && !is_numeric($value)) return false;
	if(!is_numeric($value)) return false;		
	return is_float($value + 0);
}

function toPennies($value){
	if(!is_numeric($value)) $value=float_val($value);
	if(isFloat($value)){
		$p=(int)($value*100);
	}else{
		$p=(int)$value;
	}
	return $p;
}
function toPounds($value,$format=0){
	if(!is_numeric($value)) $value=float_val($value);
	if(isFloat($value)){
		if($value<499.99) return $value;
		$value=($value/1);
	}else if(!is_numeric($value)){
		return 0.00;
	}	
	$format=(int)$format;
	$p=($value/100);
	$d=number_format($p,2);
	if($format){
		$chx=array(0=>'&dollar;',1=>'&dollar;',2=>'&euro;',3=>'&yen;');
		return $chx[$format].' '.$d;
	}else{
		return $d;
	}
}

function truncateME($value, $limit = 70, $splitwords=false,$end = '...'){
	if(!$splitwords){
		if (strlen($value) <= $limit) return $value;
		$newstr = substr($value, 0, $limit);
		if (substr($newstr, -1, 1) != ' ') $newstr = substr($newstr, 0, strrpos($newstr, " "));
		return $newstr.$end;
	}else{
		if (mb_strwidth($value, 'UTF-8') <= $limit) {
			return $value;
		}
	    return rtrim(mb_strimwidth($value, 0, $limit, '', 'UTF-8')).$end;
	}
}

function eventOptionsMap($data,$basic,$default){
	//helper for eventsMapper
	$o=safeUnserialize($data);
	if(!is_array($o)){
		$tmp=explode(',',$o);
		if(!in_array('room',$tmp)){
			$tmp[]='product';
			$basic['log']['product']=array('label'=>'Product','required'=>0,'fields'=>array('productID'));
		}
		if(count($tmp)>1){
			$_o=[];
			$ct=1;
			foreach($basic as $set=>$fields){
				foreach($fields as $i=>$v){
					$tval=array_search($i,$tmp);
					if($tval!==false){
						$_o[$set][$i]=($tval+1);
					}else{
						$_o[$set][$i]=1000;
					}
				}
			}
			//sort					
			asort($_o['log']);
			asort($_o['user']);
			//set zeros
			$o=[];
			foreach($_o as $set=>$fields){
				foreach($fields as $i=>$v){
					if($v==1000) $v=0;
					$o[$set][$i]=$v;
				}
			}
		}else{
			$o=$default;
		}
	}
	return $o;
}
function fixHTML($string){
	$string=(string)$string;
	$out=stripslashes($string);
	$out=str_replace("%u2019","'",$out);
	$out=str_replace("%u2018","'",$out);
	$out=str_replace("&nbsp;"," ",$out);
	//from sql
	$out = str_replace('\n','',$out);
	$out =str_replace('\\','', $out); 
	$out=trim($out);
	if($out==="NULL") $out="";
	return $out;
    //return mb_convert_encoding($out,"HTML-ENTITIES",'UTF-8');
}
function fixHTML_word($string){
	$patternsx=array(
		'/<!(?:--[\s\S]*?--\s*)?>\s*/',
		'/<\?xml[^>]*>/',
		'/<[^ >]+:[^>]*>/',
		'/<\/[^ >]+:[^>]*>/'
	);
	$patterns=array(
		['/<!(?:--[\s\S]*?--\s*)?>\s*/',''],
		['MsoNormal',''],
		['<b>','<strong>'],
		['[</b>','</strong>']
	);

	preg_match_all('/[^<](m|w|o):(.*)\/[^>]/', $string, $matches);
	if($matches){
		foreach($matches[0] as $m) $string=str_replace($m,'',$string);
	}
	foreach($patterns as $p){
		$string=str_replace($p[0],$p[1],$string);
	}
	return $string;
}
function utf8tohtml($args) {
	$utf8=$encodeTags=false;
	extract($args);
	$result = '';
	for ($i = 0; $i < strlen($utf8); $i++) {
		$char = $utf8[$i];
		$ascii = ord($char);
		if ($ascii < 128) {
			// one-byte character
			$result .= ($encodeTags) ? htmlentities($char) : $char;
		} else if ($ascii < 192) {
			// non-utf8 character or not a start byte
		} else if ($ascii < 224) {
			// two-byte character
			$result .= htmlentities(substr($utf8, $i, 2), ENT_QUOTES, 'UTF-8');
			$i++;
		} else if ($ascii < 240) {
			// three-byte character
			$ascii1 = ord($utf8[$i+1]);
			$ascii2 = ord($utf8[$i+2]);
			$unicode = (15 & $ascii) * 4096 +
					   (63 & $ascii1) * 64 +
					   (63 & $ascii2);
			$result .= "&#$unicode;";
			$i += 2;
		} else if ($ascii < 248) {
			// four-byte character
			$ascii1 = ord($utf8[$i+1]);
			$ascii2 = ord($utf8[$i+2]);
			$ascii3 = ord($utf8[$i+3]);
			$unicode = (15 & $ascii) * 262144 +
					   (63 & $ascii1) * 4096 +
					   (63 & $ascii2) * 64 +
					   (63 & $ascii3);
			$result .= "&#$unicode;";
			$i += 3;
		}
	}
	return $result;
}
function fillTemplate($tpl=false,$data=false,$pat=false){
	return replaceME($data,$tpl,$pat);
}

function makeHotBox($args,$opts=false){
	$tpl['head']='<span class="small-12 columns hotTitle"><strong>{title}</strong></span>';
	$tpl['img']='<span class="large-3 columns sbImage"><img src="{src}" class="thm" alt="article image"></span><span class="large-9 columns sbText ">{text}</span></span>';
	$tpl['text']='<span class="small-12 columns sbText ">{text}</span>';
	//box
	$mbox['head']='<strong class="hotTitle">{title}</strong>';
	$mbox['img']='<span class="media-object-section"><img class="thm" src="{src}" alt="article image"></span>';
	$mbox['text']='<span class="media-object-section">'.$mbox['head'].'<span class="blurb">{text}</span></span>';
	$mbox['textb']='<span class="media-object-section">'.$mbox['head'].'<span >{text}</span></span>';
	$base=$baseclass=false;
	if($opts){
		$baseclass=$opts['class'];
	}
	if($args['src']) $base.=$mbox['img'];
	if($args['text']){
		if($args['src']){ 
			$base.=$mbox['text'];
		}else{
			$base.=$mbox['textb'];
		}
	}
	if($base){
		$base='<span class="media-object '.$baseclass.'">'.$base.'</span>';
		return fillTemplate($base, $args);
	}else{
		return false;
	}
}
if(!function_exists('makeHotlist')){
    function makeHotlist($args) {
        $out = $icon = $listclass = $linkclass=$listID=false;
        if (!is_array($args)) return $out;
        if (issetCheck($args,'icon')) {
            $icon = true;
            unset($args['icon']);
        }
        if (issetCheck($args,'listclass')) {
            $listclass = $args['listclass'];
            unset($args['listclass']);
        }
        if (issetCheck($args,'linkclass')) {
            $linkclass = $args['linkclass'];
            unset($args['linkclass']);
        }
        if (issetCheck($args,'listid')) {
            $listID = ' id="'.$args['listid'].'" ';
            unset($args['listid']);
        }
        foreach ($args as $rec) {
            if ($icon) $rec['content'] = '<span class="artIcon">' . $rec['content'] . '</span>';
            if($rec['ref']){
				$out.='<a title="click to view" href="' . $rec['ref'] . '" class="hotLink '.$linkclass.'" >' . $rec['content'] . '</a>';
			}else{
				$out.='<div class="coldLink '.$linkclass.'" >' . $rec['content'] . '</div>';
			}            
        }
        if ($out) $out = '<div class="hotList ' . $listclass . '"'.$listID.'>' . $out . '</div>';
        return $out;
    }
}

function getLayouts($type = false, $id=0,$unset=null) {
	//template parts - make class??
    //use filenames only, the path is set within the template class
     $lays['names'] = array('- select -', 'Sidebar Right', 'Sidebar Left', '3 Columns', 'No Sidebar','InfoGrid');
     $lays['templates'] = array('', 'tpl.main_sidebar.php', 'tpl.main_sidebar_left.php', 'tpl.main_3col.php', 'tpl.main_single.php', 'tpl.main_single_infogrid.php');
    if ($type){
        $out = $lays[$type][$id];
    } else {
		if($unset){
			$number=is_numeric($unset);
			$array=is_array($unset);
			foreach($lays['names'] as $i=>$v){
				$tmp=false;
				if($number){
					if($unset!==$i){
						$tmp=array('name'=>$v,'template'=>$lays['templates'][$i]);
					}
				}else if($array){
					if(!in_array($i,$unset)){
						$tmp=array('name'=>$v,'template'=>$lays['templates'][$i]);
					}
				}else{//?? return everything
					$tmp=array('name'=>$v,'template'=>$lays['templates'][$i]);
				}
				if($tmp){
					$_lays['names'][$i]=$tmp['name'];
					$_lays['templates'][$i]=$tmp['template'];
				}				
			}
		}else{
			$_lays=$lays;
		} 
        $out = $_lays;
    }
    return $out;
}
function isMobile($force = false) {
	try{
		$detect = new Mobile_Detect;
	}catch(Exception $e){
		return 'classic';
	}
	if($detect->isTablet()){
		return 'tablet';
	}else if($detect->isMobile()){
		return 'mobile';
	}else{
		return 'classic';
	}	
}
function getUserNameTitle($code=null){
	$name_title[1]='Mr';
	$name_title[2]='Mrs';
	$name_title[3]='Miss';
	$name_title[4]='Ms';
	$name_title[5]='Dr';
	$name_title[6]='Prof';
	if(is_null($code)){
	   return $name_title;
	}else{
	   return issetCheck($name_title[$code]);	 
	}		
}
function getUserInfo($ref=false){
	global $container;//jamDI	
	$USB= $container->db->myp_users;
	$whr=(is_numeric($ref))?['usr_ID'=>$ref]:['usr_Email'=>$ref];
	$rec=$USB->where($whr)->select('usr_ID,usr_Name,usr_Email,usr_Phone,usr_Address');
	$rez=renderResultsORM($rec);
	if($rez) return current($rez);
	return false;
}
function getUserPrivacy($uid=false,$by=false,$save=false){
	global $container;//jamDI
	$PRV= new privacy_ob($container->db);
	$chk=$PRV->getUserPrivacy($uid,$by,$save);
	$config=$PRV->getConfig();
	$out['ID']=$chk['ID'];
	foreach($config['privacy_sections'] as $i=>$v){
		if($v>0) $out[$i]=$chk[$i];
	}
	return $out;
}
function getUserPermissions($access=false,$bits=false,$defaults=false){
	return array();
	$PERMS = new myPress_Permissions;
	$opts=[];
	if($access){
		if(is_array($access)){
			$opts['setuserperms']=$access;
		}else{
			$opts['setbasicperms']=(int)$access;
		}
	}else if($bits){
		$opts['bits']=$bits;
	}else if($defaults){
		$opts['defaults']=$defaults;
	}
	return $PERMS->Process($opts);	
}
function getDefaultResources($key=false,$set=0){
	$RB=new resource_bits;
	$out=[];
	switch($set){
		case 1://groups
			$data=$RB->get('groups');
			break;
		case 2://types
			$data=$RB->get('types');
			break;
		case 3://user types
			$data=$RB->get('users');
			break;
		default:
			if(!$key && !$set){//all data
				$data[1]=$RB->get('groups');
				$data[2]=$RB->get('types');
				$data[3]=$RB->get('users');
			}
	}
	if($key && $data){
		$out=$data[$key];
	}else{
		$out=$data;
	}
	return $out;
	//the order of these arrays are VERY IMPORTANT - changing the order means reseting everbodys permissions!!!
	$resources[1]=array(
		'members'=>array('id'=>1,'name'=>'Members Only','forum'=>1,'documents'=>1,'offers'=>1,'files'=>0,'tutorials'=>0),
		'forums'=>array('id'=>10,'name'=>'Forums','forum'=>1,'documents'=>0,'files'=>0,'tutorials'=>0),
		"correspondence_course"=>array('id'=>3,'name'=>'Correspondence Course','forum'=>1,'documents'=>1,'files'=>0,'tutorials'=>1),
		"middle_way"=>array('id'=>4,'name'=>'The Middle Way','forum'=>1,'documents'=>0,'files'=>0,'tutorials'=>0),
		"summer_school"=>array('id'=>5,'name'=>'Summer School','forum'=>1,'documents'=>1,'files'=>0,'tutorials'=>0),
		'intro_buddh'=>array('id'=>6,'name'=>'Introducing Buddhism','forum'=>1,'documents'=>1,'files'=>0,'tutorials'=>1),
		'first_steps'=>array('id'=>7,'name'=>'First Steps in Buddhist Practice','forum'=>1,'documents'=>1,'files'=>0,'tutorials'=>1),
		'first_turning'=>array('id'=>8,'name'=>'The First Turning of the Wheel','forum'=>1,'documents'=>1,'files'=>0,'tutorials'=>1),
		'great_way'=>array('id'=>9,'name'=>'The Great Way Course','forum'=>1,'documents'=>1,'files'=>0,'tutorials'=>1),
		'teachers'=>array('id'=>2,'name'=>'Teachers Only','forum'=>1,'documents'=>1,'files'=>0,'tutorials'=>0),
		'tibet_buddhism'=>array('id'=>11,'name'=>'Tibetan Buddhism','forum'=>1,'documents'=>1,'files'=>0,'tutorials'=>1),		
		'abhidhamma'=>array('id'=>12,'name'=>'Introducing Abhidhamma','forum'=>1,'documents'=>1,'files'=>0,'tutorials'=>1),
		'meditation'=>array('id'=>13,'name'=>'Meditation','forum'=>1,'documents'=>1,'files'=>0,'tutorials'=>1),
	);
	$resources[2]=array('documents'=>1,'offers'=>2,'files'=>3,'tutorials'=>4);
	$resources[3]=array('member'=>1,'student'=>2,'teacher'=>3,'editor'=>4,'admin'=>5,'super'=>6);
	if($key){
		return $resources[$set][$key];
	}else{
	    return $resources;	
	} 
}

//sanitizer
function cleanME($val=null,$type=false){
	switch($type){
		case 'array':
			return filter_var_array($val, FILTER_UNSAFE_RAW);
			break;
		case 'bool':
			return (!is_bool($val))?false:$val;
			break;
		case 'int':
			return filter_var($val,FILTER_VALIDATE_INT);
			break;
		case 'lstr':
			return trim(filter_var($val,FILTER_SANITIZE_SPECIAL_CHARS));
			break;
		default://assume string - removes tags
			return trim(strip_tags($val));
	}
}
//dev zipper
function dataTable($args = false,$wrap='large',$table_id='dTable') {
	//admin tables	
	$defs['tbody'] = '<tr><td colspan="6"><span class="alert-box">No template data...</span></td></tr>';
	$defs['thead'] = '<tr><th colspan="6">No template data...</th></tr>';
	$defs['before'] = $defs['after'] = $table='';
	$count=0;
	if(issetCheck($args,'data')){
		$TBL=new jamTable;
		$count=count($args['data']['data']);
		$filter_class=(isset($args['before']) && $args['before']==='filter')?'filterme':false;
		$class_mod=issetCheck($args,'class');
		$args['data']['table']=array('id'=>$table_id,'class'=>'dataTable '.$filter_class.' '.$class_mod,'html'=>false);
		if(isset($args['table'])){
			foreach($args['table'] as $i=>$v) $args['data']['table'][$i]=$v;
		}
		if(isset($args['sort_data'])) $args['data']['sort_data']=$args['sort_data'];			
		$table=$TBL->Process($args['data']);
		$out = '{before}<div class="tablewrap '.$wrap.'">'.$table.'</div>{after}';
	}else{
		$out = '{before}<div class="dataTable_wrapper"><table id="'.$table_id.'" class="dataTable filterme responsive"><thead>{thead}</thead>';
		$out.='<tbody>{tbody}</tbody></table></div>{after}';		
	}
	$filter='<div><div class="input-group"><span class="input-group-label">Filter</span><input class="input-group-field filter" type="text" id="'.$table_id.'_filter"/></div>';
	$metrics='<div class="metrics">'.$count.' Record(s)</div>';
	$filter='<div>'.$filter.$metrics.'</div></div>';
	foreach ($defs as $key => $d) {
		$v = issetCheck($args,$key, $d);
		if($key==='before' && $v==='filter') $v=$filter;
		$out = str_replace('{' . $key . '}', $v, $out);
	}
	return $out;
}

//backup database
function backupDatabase($tables = '*'){
	$link = mysqli_connect(DB_HOST,DB_USER,DB_PASS,DB_NAME);
	
	//get all of the tables
	if($tables === '*'){
		$tables = array();
		$result = mysqli_query($link,'SHOW TABLES');
		while($row = mysqli_fetch_row($result)){
			$tables[] = $row[0];
		}
	}else{
		$tables = is_array($tables) ? $tables : explode(',',$tables);
	}
	
	//cycle through
	foreach($tables as $table){
		$result = mysqli_query($link,'SELECT * FROM '.$table);
		$num_fields = mysqli_num_fields($result);
		$return= 'DROP TABLE '.$table.';';
		$row2 = mysqli_fetch_row(mysqli_query($link,'SHOW CREATE TABLE '.$table));
		$return.= "\n\n".$row2[1].";\n\n";
		
		for ($i = 0; $i < $num_fields; $i++) {
			while($row = mysqli_fetch_row($result)){
				$return.= 'INSERT INTO '.$table.' VALUES(';
				for($j=0; $j < $num_fields; $j++) {
					$jfromdb = $row[$j];
					
					$jfromdb = addslashes($jfromdb);
					$jfromdb = preg_replace("/\n/","\\n",$jfromdb);//'/looking for/', 'replace with', $in this text
					if (isset($jfromdb)) { $return.= '"'.$jfromdb.'"' ; } else { $return.= '""'; }
					if ($j < ($num_fields-1)) { $return.= ','; }
				}
				$return.= ");\n";
			}
		}
		$return.="\n\n\n";
	}
	
	//save file
	$fname='db-backup-'.time().'-'.(md5(implode(',',$tables)));
	$handle = fopen(CACHE.$fname.'.sql','w+');
	fwrite($handle,$return);
	fclose($handle);
	//create zip
	create_zip(array(CACHE.$fname.'.sql'),$fname);
	
	return $fname;
}

//zip some files
/* creates a compressed zip file */
function create_zip($files = array(),$zip_name = '',$overwrite = false) {
	if(!$zip_name||$zip_name==='') $zip_name=time().'_zip_archive';
	$destination=CACHE.$zip_name.'.zip';
	//if the zip file already exists and overwrite is false, return false
	if(file_exists($destination) && !$overwrite) { return false; }
	//vars
	$valid_files = array();
	//if files were passed in...
	if(is_array($files)) {
		//cycle through each file
		foreach($files as $file) {
			//make sure the file exists
			if(file_exists($file)) {
				$valid_files[] = $file;
			}
		}
	}
	//if we have good files...
	if(count($valid_files)) {
		//create the archive
		$zip = new ZipArchive();
		if($zip->open($destination,$overwrite ? ZIPARCHIVE::OVERWRITE : ZIPARCHIVE::CREATE) !== true) {
			return false;
		}
		//add the files
		foreach($valid_files as $file) {
			$zip->addFile($file,$file);
		}
		//debug
		//echo 'The zip archive contains ',$zip->numFiles,' files with a status of ',$zip->status;
		
		//close the zip -- done!
		$zip->close();
		
		//check to make sure the file exists
		return file_exists($destination);
	}
	return false;

}
function encodeSalt($var=false,$type=1){
   if(!$var) die('Sorry, nothing to encodeSalt..');
   $var=trim($var);
   $hash = TextFun::quickHash(['info'=>$var]);
   $check = TextFun::quickHash(['info'=>$var,'encdata'=>$hash]);
   if($check){
     $out=$hash;
   }else{
     $out=false;
   }
   return $out;
}
function generate_salt($max = 15) {
	$salt = TextFun::getSalt($max);
	return $salt;
}
function terminatePayment(){
	//terminates all cart & form processes
	$sid=session_id();
	$sesh=array('mycart','sysTmpForm','sysACT','formref','ezcart_'.$sid,'mp_scart_'.$sid);
	foreach($sesh as $s){
		if(isset($_SESSION[$s])) unset($_SESSION[$s]);
	}
}

//depreciated functions - to be removed
function getOption($varname, $default = false, $type = false){
	//should not need this, use the DI when possible
    //gets a single option from the DB
    global $container;//pimple/jamDI
    $options=$container->get('Options');
    $rec=$options->get($varname,$type);
    if(!$rec) $rec=$default;
	return $rec;
}
function getOption_Long($varname, $default = false, $type = false) {
	//gets a single option to the DB
	// use for options with very long values
    global $container;//pimple/jamDI
    $db=$container->get('db');
	$out=$default;
	if(is_string($varname) && $varname!==''){
		$rec=$db->myp_items->where('itm_Type','option')->and('itm_Title',$varname);
		if(count($rec)>0){
			$rec=renderResultsORM($rec);
			$chk=current($rec);
			if($type==='var'){
				$out = $chk['itm_Content'];
			} else {
				$out = $chk;
			}
		}
	}
	return $out;
}
function saveOption_Long($varname, $value=false,$add=false) {
	//saves a single long option to the DB
	// use for options with very long values
    global $container;//pimple/jamDI
	$chk=false;
    if(is_string($varname) && $varname!==''){
		$db=$container->get('db')->myp_items();
		$upd=array(
			'itm_Content'=>$value,
		);
		$rec=$db->where('itm_Title',$varname)->and('itm_Type','option');
		if(count($rec)>0){
			$chk=$rec->update($upd);
		}else if($add){
			$upd['itm_DAte']=$upd['itm_Last']=date('Y-m-d H:i:s');
			$upd['itm_Type']='option';
			$upd['itm_Title']=$varname;
			$chk=$db->insert($upd);
		}
	}
	return $chk;			
}	

function saveOption($varname, $value=false,$id=0,$add=true){
	return setOption($varname, $value,$id,$add);
}
function setOption($varname, $value=false,$id=0,$add=false){
	//should not need this, use the DI when possible
    //saves a single option to the DB
    global $container;//pimple/jamDI
	$chk=false;
	$valid_name=(is_string($varname) && $varname!=='')?true:false;
    if((int)$id > 0 || $valid_name){
		$upd=array(
			'opt_Value'=>$value,
		);
		if(is_array($value)){
			if(isset($value['value'])) $upd['opt_Value']=$value['value'];
			if(isset($value['active'])) $upd['opt_Active']=$value['active'];
		}
		$db=$container->db->myp_options;
		if($id){
			$rec=$db->where('opt_id',$id);
		}else if($valid_name){
			$rec=$db->where('opt_Name',$varname)->limit(1);
		}
		if(count($rec)>0){//update
			$chk=$rec->update($upd);
		}else if($add){//add
			$upd['opt_Active']=0;
			$upd['opt_Name']=$varname;
			$chk=$db->insert($upd);
		}
	}			
	return $chk;
}


function addOption($varname, $value=false,$id=0){
	//should not need this, use the DI when possible
    //saves a single option to the DB
    global $container;//pimple/jamDI
	$chk=false;
	preME($varname,4);
    if(is_string($varname) && $varname!==''){
		$db=$container->get('db')->myp_options();
		$upd=array(
			'opt_Name'=>$varname,
			'opt_Value'=>$value,
			'opt_Active'=>0
		);
		if(is_array($value)){
			if(isset($value['value'])) $upd['opt_Value']=$value['value'];
			if(isset($value['active'])) $upd['opt_Active']=$value['active'];
		}
		$chk=$db->insert($upd);
	}			
	return $chk;
}


function getRequestVars($var=false,$type=false, $default = false) {
	//should not need this, use the DI when possible
    global $container;//pimple/jamDI
 	$output=$default;
 	$router=$container->get('router');
	switch($type){
	    case 'post':
			$data=$router->get('post');			
			$output=($var)?issetCheck($data,$var,$default):$data;
	        break;
	    case 'all':
			if($var){
				$data=$router->get('get');
				$output=issetCheck($data,$var);
				if(!$output){
					$data=$router->get('post');
					$output=issetCheck($data,$var,$default);
				}
			}
	        break;
        case 'session':
			if($var){
				$output=issetCheck($_SESSION,$var,$default);
			}
           break; 
        default:
			$data=$router->get('get');			
			$output=($var)?issetCheck($data,$var,$default):$data;
	 }
	 return $output;
}

function runQuery($query, $type = false) {
	//should not need this, use the DI & ORM when possible
    global $container;
    $db=$container->get('ezPDO');
    $out=[];
    if(is_string($query) && $query!==''){
		$rez=$db->runQuery($query,$type);
		if($err=$db->getErrors()){
			foreach ($err as $e) {
				$out.=$e. '<br/>';
			}
		}else if($type) {
            $out = $rez;
        } else {
            $out['result'] = 'ok';
            $out['lastID'] = $db->getlastInsertId(); //$db->query('SELECT LAST_INSERT_ID()');
            $out['query'] = $query;
            $out['recs'] = $rez;
        }
    }
    return $out;
}

function randomIOD() {
	$iod=[];
	$sql = "SELECT * FROM myp_media WHERE mda_type='image' AND mda_meta!=''";
	if ($recs = runQuery($sql, 'obj')) {
		foreach ($recs as $rec) {
			$out[$rec->mda_id]['name'] = $rec->mda_nice_name;
			$out[$rec->mda_id]['path'] = $rec->mda_path . $rec->mda_filename;
			$out[$rec->mda_id]['meta'] = compress($rec->mda_meta,false);
			if ($out[$rec->mda_id]['meta']['iod'] == 'yes')	$iod[] = $out[$rec->mda_id];
		}
	}
	$cnt = count($iod);
	$r = rand(0, $cnt);
	return $iod[$r];
}

function is_serialized( $data, $strict = true ) {
    // if it isn't a string, it isn't serialized.
    if ( ! is_string( $data ) ) {
        return false;
    }
    $data = trim( $data );
    if ( 'N;' == $data ) {
        return true;
    }
    if ( strlen( $data ) < 4 ) {
        return false;
    }
    if ( ':' !== $data[1] ) {
        return false;
    }
    if ( $strict ) {
        $lastc = substr( $data, -1 );
        if ( ';' !== $lastc && '}' !== $lastc ) {
            return false;
        }
    } else {
        $semicolon = strpos( $data, ';' );
        $brace     = strpos( $data, '}' );
        // Either ; or } must exist.
        if ( false === $semicolon && false === $brace ) {
            return false;
        }
        // But neither must be in the first X characters.
        if ( false !== $semicolon && $semicolon < 3 ) {
            return false;
        }
        if ( false !== $brace && $brace < 4 ) {
            return false;
        }
    }
    $token = $data[0];
    switch ( $token ) {
        case 's':
            if ( $strict ) {
                if ( '"' !== substr( $data, -2, 1 ) ) {
                    return false;
                }
            } elseif ( false === strpos( $data, '"' ) ) {
                return false;
            }
            // or else fall through
        case 'a':
        case 'O':
            return (bool) preg_match( "/^{$token}:[0-9]+:/s", $data );
        case 'b':
        case 'i':
        case 'd':
            $end = $strict ? '$' : '';
            return (bool) preg_match( "/^{$token}:[0-9.E-]+;$end/", $data );
    }
    return false;
}
function writeCache($name, $data, $dir = false) {
	$file=new file_cache;
	$args['filename']=$name;
	$args['data']=$data;
	$args['dir']=$dir;
	return $file->_get(__FUNCTION__,$args);
}

function readCache($name, $dir = false,$datatype='compressed') {
	$file=new file_cache;
	$args['filename']=$name;
	$args['datatype']=$datatype;
	$args['dir']=$dir;
	return $file->_get(__FUNCTION__,$args);
}

function checkXMLCache($file,$url,$time=1){
   $file=FILE_ROOT."xml/$file";
   if(file_exists($file)){
      $ftime=filemtime($file);
      $now=date("h:i",time());
	  $chk=get_time_difference($ftime,time());
	  if(issetCheck($chk,'hours')){
		  if($chk['hours'] > $time){
			$r = new http_request($url); 
			$xml=$r->DownloadToString(); 
			$xml= iconv("UTF-8","UTF-8//IGNORE",$xml);
			file_put_contents($file,$xml);
		  }
	  }
   }else{
	    $r = new http_request($url); 
	    $xml=$r->DownloadToString(); 
		$xml= iconv("UTF-8","UTF-8//IGNORE",$xml);
		file_put_contents($file,$xml);
   }	 
   return $file;
}
function get_time_difference( $start, $end ){
    $uts['start']=(!(int)$start)?strtotime( $start ):$start;
    $uts['end']=(!(int)$end)?strtotime( $end ):$end;
	$err=false;$out=[];
	if( $uts['start']!==-1 && $uts['end']!==-1 ){
        if( $uts['end'] >= $uts['start'] ){
            $diff = $uts['end'] - $uts['start'];
            if( $days=intval((floor($diff/86400))) )  $diff = $diff % 86400;
            if( $hours=intval((floor($diff/3600))) )  $diff = $diff % 3600;
            if( $minutes=intval((floor($diff/60))) )  $diff = $diff % 60;
            $diff = intval( $diff );            
            $out= array('days'=>$days, 'hours'=>$hours, 'minutes'=>$minutes, 'seconds'=>$diff);
        }else{
            $err="Ending date/time($start) is earlier than the start date/time($end)";
        }
    }else{
        $err="Invalid date/time data detected";
    }
	if($err) $out=$err;

    return $out;
}

function safeUnserialize($data=false){
	$test=null;
	if($data){
		$test=unserialize($data);
		if(!$test){
			$test = preg_replace_callback ( '!s:(\d+):"(.*?)";!',
				function($match) {
					return ($match[1] == strlen($match[2])) ? $match[0] : 's:' . strlen($match[2]) . ':"' . $match[2] . '";';
				},
				$data 
			);
		}
	}
	return $test;
}

function setToken($name=false){
	//move into slim sesson
	if($name){
		$id='_'.$name;
	}else{
		$id='';
	}
	$token = md5(uniqid(rand(), true));
	$_SESSION['token'.$id] = $token;
	return $token;
}
function checkToken($token, $sesh = 'token') {
	//move into slim sesson
	$stoken = issetCheck($_SESSION,$sesh);
	if($stoken) unset($_SESSION[$sesh]);
	if (!$stoken || !$token) {
		return 'Sorry, that form is not valid. Please refresh your browser then try again.';
	}
	if ($stoken != $token) {
		return 'Sorry, that form has expired. Please refresh your browser then try again.';
	}
	return false;
}

//ukkaman
function gradeSort($array=false){
	if(is_array($array)){
		usort($array,"gradeSortCompare");
		return $array;
	}
	return false;
}
function gradeSortCompare($a,$b){
	if ($a['grade'] == $b['grade']){//same grade
		// grade is the same, sort by grade date
		if ($a['gdate'] = $b['gdate']){
			// grade date is the same, sort by age
			return $a['age'] < $b['age'] ? 1 : -1;
		}else{
			// sort the higher score first:
			return $a['gdate'] < $b['gdate'] ? 1 : -1;
		}
    }
    // otherwise sort the higher score first:
    return $a['score'] < $b['score'] ? 1 : -1;
}

function validate_lkey(){
	return;
	$keyfile=CACHE.'lkey.dat';
	$limit=86400;//24 hours
	$ftime=filemtime($keyfile);
	$now=time();
	$dif=($now-$ftime);
	if($dif >= $limit){
		$api='https://www.jamtechsolutions.co.uk/lkey_index.php';
		$site = (empty($_SERVER['HTTPS']) ? 'http' : 'https') . '://'.$_SERVER['HTTP_HOST'].'/';
		$key = file_get_contents($keyfile);
		$a=['product'=>'memberme','url'=>$site,'lkey'=>trim($key),'action'=>'validate'];
		$h=new HttpPost($api);
		$h->setPostData($a);
		$h->setSSLChecks(false);
		$h->send();
		$r=$h->getResponse();
		if($r==='VALID'){
			touch($keyfile);
		}else{
			echo $r;
			die;			
		}
	}	
}
