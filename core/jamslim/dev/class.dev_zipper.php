<?php
class dev_zipper{
	var $FILES;
	var $SETUP;
	var $ACTION;
	private $OPT_NAME='util_zipper';
	private $OPT_GROUP='system';
	private $OPT_ID;
	private $OPT_REC;
	private $OPTIONS;
	var $OUTPUT;
	var $TOOL_ID='zipper';
	var $PERMLINK;
	var $MSG=false;
	var $POST;
	
	private $SLIM;
	private $ROUTE;
	private $DB;//notORM
	private $PARAMS;
	private $AJAX;
	private $GET;
	private $ADMIN;
	private $ZIP_PATH;
	private $PERMBACK;
	private $FORMLINK;
	private $ACTIONS;
	private $APP_NAME;
	private $APP_TITLE;
	private $APP_URL;
	private $APP_ICON;
	
	
	function __construct($slim){
		if(!is_object($slim)) throw new Exception ('the slim object is missing...');
		$this->SLIM=$slim;
		//$dbo=false,$routes=false,$ajax=false
		$this->DB=$slim->db;//$dbo;
		$this->ROUTE=$slim->router->get('route');
		$this->PERMBACK=URL.issetCheck($this->ROUTE,0,'admin').'/';
		$this->PERMLINK=$this->PERMBACK.$this->TOOL_ID.'/';
		$this->FORMLINK=$this->PERMBACK.$this->TOOL_ID.'/';
		$this->GET=$slim->router->get('get');;//(!empty($_GET))?$_GET:false;
		$this->POST=$slim->router->get('post');//(!empty($_POST))?$_POST:false;
		$this->PARAMS=$this->ROUTE;
		$this->AJAX=$slim->router->get('ajax');
		$this->ADMIN=($slim->user['access']==$slim->SuperLevel)?true:false;
		$this->ACTION=issetCheck($this->GET,'act');//$routes['url_action'];
		$this->ACTIONS['options']=array('label'=>'App Settings','desc'=>'manage options','color'=>'purple');
		$this->ACTIONS['archive']=array('label'=>'Zip New Files','desc'=>'create an archive from scan','color'=>'dark-blue');
		$this->ACTIONS['serverbackup']=array('label'=>'Zip From List','desc'=>'create an archive from a list','color'=>'blue');
		$this->init();
	}
	
	function init(){
		//setup initial vars
		if(!$this->ADMIN) setSystemResponse(false,URL);
		$this->APP_NAME='mpZipper';
		$this->APP_TITLE='File Archiver';
		$this->APP_URL='';//'?c=zipper'
		$this->APP_ICON='archive';
		$this->loadConfig();
		if(!$this->FILES) $this->FILES=issetCheck($_SESSION,'zipper_files');
		//check if we are returning from a post
		//preME($_SESSION,2);
		$tmp=issetCheck($_SESSION,'zip_post_result');
        //setup the output object
		$this->OUTPUT=new generic_a;
		$this->OUTPUT->set('ICON','<i class="fi-'.$this->APP_ICON.'"></i>');
		$this->OUTPUT->set('TITLE',$this->APP_TITLE);
		$this->OUTPUT->set('CONTENT','* no content *');
		$this->OUTPUT->set('MENU','');
		if($tmp){
			$this->OUTPUT->set('TITLE',$tmp['TITLE']);
			$this->OUTPUT->set('CONTENT',$tmp['CONTENT']);
			$this->OUTPUT->set('MENU',$tmp['MENU']);
			unset($_SESSION['zip_post_result']);
			$this->ACTION='frompost';
		}
		//setup dynamic options
		//preME(ROOT,2);
	}
	
	function updateConfig($post){
		$save=false;
		//preME($post);
		foreach($post as $i=>$v){
			if(isset($this->OPTIONS[$i])){
				switch($i){
					case 'limit':
						$this->OPTIONS[$i]=(is_numeric($v))?$v:strtotime($v);
						break;
					case 'dirs': case'skip_files': case'skip_dirs':
						$tmp=explode(',',$v);
						foreach($tmp as $x=>$t) $tmp[$x]=trim(str_replace(PHP_EOL,'',$t));
						$this->OPTIONS[$i]=$tmp;
						break;
					default:
						$this->OPTIONS[$i]=$v;						
				}
				$save=true;
			}
		}
		if($save){
			$chk=$this->saveConfig();
			$msg=($chk)?'Okay, the options have been updated':'Sorry, I could not update the options... please try again';
			setSystemResponse($this->PERMLINK,$msg);
			die($msg);
		}
	}
	
	function saveConfig(){
		$chk=false;
		if($this->OPTIONS){
			$data=compress($this->OPTIONS);
			$id=0;
			if($this->OPT_REC){
				$this->OPT_REC['OptionValue']=$data;
			}else{
				$this->OPT_REC=array(
					'id'=>0,
					'OptionID'=>0,
					'OptionName'=>$this->OPT_NAME,
					'OptionValue'=>$data,
					'OptionGroup'=>$this->OPT_GROUP,
					'OptionDescription'=>'holds dev zipper options',
				);
			}
			//preME($this->OPT_REC,2);
			//preME(date('d/m/Y',$this->OPTIONS['limit']),2);
			$chk=$this->saveOptions();
		}
		return $chk;
	}
	
	function loadConfig(){
		$this->getOptions();
		if(!$this->OPTIONS){
			$this->OPTIONS=array(
				'destination'=>'zipTest.zip',
				'destination_backup'=>'zipServer',
				'dirs'=>array(APP,CORE,ROOT.'public',ROOT.'vendor'),
				'limit'=>strtotime('-5days'),
				'skip_files'=>array('mpIcons.css',CORE.'class.jSlimAutoLoader.php'),
				'skip_dirs'=>array(CACHE),								
			);
		}
		$b=issetCheck($this->OPTIONS,'destination_backup');
		if(!$b) $this->OPTIONS['destination_backup']='zipServer';
		$this->ZIP_PATH=CACHE.$this->OPTIONS['destination'];
	}
	
	function Process(){
		if($this->POST){
			if(isset($this->POST['zips'])){
				$act=issetCheck($this->POST,'action','archive_now');
				$this->POST['action']=$act;
				switch($act){
					case 'archive_now':
						$this->zipFiles($this->POST['zips']);
						$this->ACTION='skip';
						break;
					case 'backup_now':
					case 'Backup Now':
						$this->serverBackup($this->POST['zips']);
						$this->ACTION='skip';
						break;
					case 'backup':
					case 'update_list':
						$this->serverBackup_save($this->POST['zips']);
						$this->ACTION='skip';
						break;
					default:
					
				}
			}elseif(isset($this->POST['add_files'])){	
				switch($this->POST['action']){
					case 'Replace':
						$this->serverBackup_replace();
						break;
					case 'Append':
						$this->serverBackup_append();
						break;
				}	
			}elseif(isset($this->POST['options'])){
				$this->updateConfig($this->POST['options']);
				$this->ACTION='skip';
			}
		}
		$cwd=getcwd();
		$reset_cwd=false;
		switch($this->ACTION){
			case 'skip':
				//redirect for slim
				$content=$this->OUTPUT->get('all');
				$_SESSION['zip_post_result']=$content;
				setSystemResponse($this->PERMLINK);
				die;
				break;
			case 'download':
			    chdir(ROOT);
			    $reset_cwd=true;
				$file=issetCheck($_GET,'zip');
				if($file) $file=base64_decode($file);
				$this->downloadArchive($file);
				break;
			case 'options':
				$this->renderAdminOptions();
				break;
			case 'serverbackup':
				$this->serverBackup_render();
				break;
			case 'archive':		
				chdir(ROOT);
				$this->scanForValidFiles();
				$this->renderValidFiles();
				break;
			case 'backup':
				$this->serverBackup_save(false,true);
				break;
			case 'frompost':
				//contents should already be loaded from the session
				break;
			default:
				$this->renderDash();
				
		}
		if($reset_cwd) chdir($cwd);
		if($this->MSG){
			Assets::add('message',$this->MSG);
		}
		$o=$this->OUTPUT->get();
		$out=array();
		foreach($o as $i=>$v) {
			if($i==='MENU') $v=array('right'=>$v);
			$out[strtolower($i)]=$v;
		}
		return $out;
	}
	function renderDash(){
		$links=[];
		foreach($this->ACTIONS as $i=>$v){
			$href=$this->PERMLINK.'?act='.$i;
			$links[]='<div class="cell medium-4"><div class="callout bg-'.$v['color'].' gotoME" data-ref="'.$href.'"><strong>'.$v['label'].'</strong><br/><small>'.$v['desc'].'</small></div></div>';
		}
		$output='<div class="grid-x grid-margin-x">'.implode('',$links).'</div>';
		$this->OUTPUT->set( 'sub_title', 'Dashboard');
		$this->OUTPUT->set( 'CONTENT',$output);
		$this->OUTPUT->set( 'MENU',$this->renderTopControls(array('archive','fileman')));

	}
	function renderTopControls($p=false){
		$c=$this->ACTIONS;
		$c['dash']=array('label'=>'Dashboard','desc'=>'app dashboard','color'=>'aqua');
		//$c['save']='<button class="button button-olive submitME" title="save the changes" data-ref="form1">Save</button>';
		$c['options']=array('label'=>'App Settings','desc'=>'manage options','color'=>'purple');
		$c['download']=array('label'=>'Download','desc'=>'download this file','color'=>'orange');
		$c['rescan']=array('label'=>'Rescan','desc'=>'rescan the directories','color'=>'maroon','href'=>'?act=archive&rescan=1');
		$c['fileman']=array('label'=>'File Man.','desc'=>'file manager','color'=>'orange');
		$c['backup']=array('label'=>'Backup','desc'=>'backup local files','color'=>'dark-blue','load'=>'loadME');
		$c['serverbackup']=array('label'=>'Zip From List','desc'=>'create an archive from a list','color'=>'blue');
		$c['serverbackup_now']=array('label'=>'Backup Now','desc'=>'zip the selected files','color'=>'olive','load'=>'submitME','href'=>'form1');
		$c['archive']=array('label'=>'Archive','desc'=>'View the selected files','color'=>'dark-blue');
		$c['archive_now']=array('label'=>'Archive Now','desc'=>'zip the selected files','color'=>'olive','load'=>'submitME','href'=>'form1');
		$c['save']=array('label'=>'Save','desc'=>'save the changes','color'=>'olive','load'=>'submitME','href'=>'form1');
		$controls=false;
		//$p=array();
		switch($this->ACTION){
			case 'options':$p=array('dash','save');break;
			case 'archive':$p=array('options','serverbackup','rescan','backup','archive_now');break;
			case 'serverbackup':$p=array('options','archive','serverbackup_now',);break;
		}
		if($p){
			if(!is_array($p)) $p=(array)$p;
			foreach($p as $part){
				$v=issetCheck($c,$part);
				if($v){
					$load=issetCheck($v,'load','gotoME');
					if($part==='fileman'){
						$href=$this->PERMBACK.'files/';
					}else if($load==='submitME'){
						$href=issetCheck($v,'href','form1');
					}else{
						$act=issetCheck($v,'href','?act='.$part);
						$href=$this->PERMLINK.$act;							
					}
					$controls.='<button class="'.$load.' button small button-'.$v['color'].'" data-ref="'.$href.'" title="'.$v['desc'].'">'.$v['label'].'</button>';
				}
			}
		}
		return $controls;
	}
	function renderValidFiles(){
		$dirs='';
		$ct=0;
		$data=array();
		foreach($this->OPTIONS['dirs'] as $dir) $dirs.='<li>'.$dir.'</li>';			
		foreach($this->FILES as $dir=>$files){
			foreach($files as $file){
				$data['data'][$ct]=array(
					'filename'=>$file['pathinfo']['basename'],
					'directory'=>str_replace(ROOT,'',$file['pathinfo']['dirname']),
					'date'=>date('d M Y H:i',$file['filetime']),
					'archive?'=>'<input type="checkbox" name="zips[]" value="'.$file['filename'].'" checked/>'
				);
				$ct++;
			}
		}
		$table=dataTable(array('data'=>$data,'before'=>'filter'));
		$metrics='<div class="callout bg-blue"><h4>Metrics</h4><p>Time Limit: on or after <strong>'.date('d M Y H:i',$this->OPTIONS['limit']).'</strong><br/><strong>'.$ct.'</strong> valid files found</p><h4>Files Skipped</h4><p>'.implode('<br/>',$this->OPTIONS['skip_files']).'</p><h4>Directories Skipped</h4><p>'.implode('<br/>',$this->OPTIONS['skip_dirs']).'</p></div>';
		$scanned='<div class="callout bg-maroon text-white"><h4>Directories Scanned:</h4><ol>'.$dirs.'</ol></div>';		
		$output='<div class="grid-x grid-margin-x"><div class="cell medium-6">'.$metrics.'</div><div class="cell medium-6">'.$scanned.'</div></div>';
		$output.='<h3>Files:</h3>';
		$output.='<form id="form1" method="post" action="'.$this->FORMLINK.'"><input type="hidden" name="tool" value="'.$this->TOOL_ID.'"/>';
		$output.=$table;
		$output.='<input type="hidden" name="action " value="archive_now"/>';
		$output.='<button class="button right loadME" type="button" data-ref="'.$this->PERMLINK.'&act=Backup">Backup</button><button class="button success" type="submit">Archive Now</button></form>';
		$this->OUTPUT->set( 'TITLE', $this->ACTIONS[$this->ACTION]['label']);
		$this->OUTPUT->set( 'CONTENT', $output);
		$this->OUTPUT->set( 'MENU', $this->renderTopControls(array('options','serverbackup','rescan')));
	}
	function serverBackup_render(){
		$dirs='<form method="post" action="'.$this->FORMLINK.'"><label class="text-white">file paths (csv)</label><textarea name="add_files"></textarea><label class="text-white">Destination filename (extensions will be added, only works when using "Replace")<input type="text" name="dest_backup" placeholder="sample_file_name" value="'.$this->OPTIONS['destination_backup'].'"/></label><input class="button small bg-aqua" type="submit" name="action" value="Append"/><input class="button small bg-olive" type="submit" name="action" value="Replace"/></form>';
		$ct=0;
		$_files=$this->serverBackup_load();
		//preME($this->OPTIONS,2);
		$data=array();
		$table=msgHandler('No files found...');
		if(is_array($_files)){
			foreach($_files as $ct=>$file){
				$data['data'][$ct]=array(
					'filepath'=>$file,
					'archive?'=>'<input type="checkbox" name="zips['.$ct.']" value="'.$file.'" checked/>'
				);
			}
			$table=dataTable(array('data'=>$data,'before'=>'filter'));
			$ct++;
		}
		$metrics='<div class="callout bg-blue"><h4>File</h4><p>List loaded from <strong>data/'.$this->OPTIONS['destination_backup'].'.php</strong><br/><strong>'.($ct).'</strong> valid files found</p><h4>Files Skipped</h4><p>'.implode('<br/>',$this->OPTIONS['skip_dirs']).'</p></div>';
		$scanned='<div class="callout bg-maroon"><h4>Add/Replace Files:</h4><ol>'.$dirs.'</ol></div>';		
		$output='<div class="grid-x grid-margin-x"><div class="medium-6 cell">'.$metrics.'</div><div class="medium-6 cell">'.$scanned.'</div></div>';
		$output.='<h3>Files:</h3>';
		$output.='<form method="post" id="form1" action="'.$this->FORMLINK.'"><input type="hidden" name="tool" value="'.$this->TOOL_ID.'"/>';
		$output.=$table;
		$output.='<input class="button right" type="submit" name="action" value="Update List"/><input class="button success right" type="submit" name="action" value="Backup Now"/></form>';
		$this->OUTPUT->set( 'TITLE', $this->ACTIONS[$this->ACTION]['label']);
		$this->OUTPUT->set( 'CONTENT', $output);
		$this->OUTPUT->set( 'MENU', $this->renderTopControls(array('dash','options','archive')));//'<a class="button small bg-purple" href="'.$this->PERMLINK.'&act=options">Set Options</a><a class="button small bg-blue" href="'.$this->PERMLINK.'&act=serverbackup">Server Backup</a>');
	}
	
	function renderAdminOptions(){
		$row='';
		foreach($this->OPTIONS as $i=>$v){
			switch($i){
				case 'limit':
					$last='<strong class="text-dark-green">Last Zipped: '.$this->lastZipDate().'</strong><br/>';
					$date=date('d M Y H:i',$v);
					$row.='<tr><th>'.ucME($i).'</th><td>'.$last.'<input type="datetime" name="options['.$i.']" value="'.$date.'"/></td></tr>';
					break;
				case 'skip_dirs': case 'skip_files': case 'dirs':
				    $dirs=implode(','.PHP_EOL,$v);
				    //$dirs=str_replace(PHP_EOL,'',$dirs);
					$row.='<tr><th>'.ucME($i).'</th><td><textarea rows="8" name="options['.$i.']">'.$dirs.'</textarea></td></tr>';
					break;
				default:
					$row.='<tr><th>'.ucME($i).'</th><td><input type="text" name="options['.$i.']" value="'.$v.'"/></td></tr>';
			}
		}
		$output='<div class="callout bg-blue">Manage the options for scanning and archiving.</div>';
		$output.='<h3>Options:</h3>';
		$output.='<form id="form1" method="post" action="'.$this->FORMLINK.'"><input type="hidden" name="tool" value="'.$this->TOOL_ID.'"/>';
		$output.='<table class="listTable">'.$row.'</table>';
		$output.='<input class="button success right" type="submit" value="Update Options"/></form>';
		$this->OUTPUT->set( 'TITLE', $this->ACTIONS[$this->ACTION]['label']);
		$this->OUTPUT->set( 'CONTENT', $output);
		$this->OUTPUT->set( 'MENU', $this->renderTopControls(array('dash','save')));
	}
	
	private function lastZipDate(){
		$last="unknown??";
		if(file_exists($this->ZIP_PATH)){
			 $last=date ("d M Y H:i", filemtime($this->ZIP_PATH));
		}
		return $last;
	}

	function scanForValidFiles(){
		if(!is_array($this->OPTIONS['dirs'])) die(__METHOD__.':the var $dirs is not an array...');
		if(empty($this->OPTIONS['dirs'])) die(__METHOD__.':the var $dirs is empty...');
		$scan=array();
		foreach($this->OPTIONS['dirs'] as $d){
			$chk=$this->fileScanner($d);
			$t=key($chk);
			if($t && $t!==''){
				$scan+=$chk;
			}else{
				//preME([$this->OPTIONS['dirs'],$d,$chk]);
				$c=current($chk);
				if($c) $scan[$d]=$c;
			}
		}
		$dupes=[];
		$fix=[];
		ksort($scan);
		//remove duplicates && skips
		foreach($scan as $i=>$v){
			if($this->validDir($i)){
				//preME($i,2);
				foreach($v as $file){
					//if(!isset($file['filename'])) preME($v,2);
					if(!in_array($file['filename'],$dupes)){
						if(!in_array($file['pathinfo']['basename'],$this->OPTIONS['skip_files'])){
							$dupes[]=$file['filename'];
							$fix[$i][]=$file;
						}
					}
				}
			}
		}
		$this->FILES=$fix;
		$_SESSION['zipper_files']=$fix;
	}
	
	function validDir($dir=false){
		if(trim($dir)==='') return false;
		$valid=true;
		foreach($this->OPTIONS['skip_dirs'] as $d){
			//if(strpos($d,$dir)!==false){
			if($d==$dir){
				$valid=false;
				break;
			}
		}
		return $valid;
	}

	function fileScanner($path){
		//$path='./'.$path;
		//preME($path,2);
		$limit=(int)$this->OPTIONS['limit'];
		$result=[];
		if(!$path||$path==='') return $result;//die('the path is empty....');
		if(!is_dir($path)) return $result;
		$dir  = new RecursiveDirectoryIterator($path, RecursiveDirectoryIterator::SKIP_DOTS);
		$files = new RecursiveIteratorIterator($dir, RecursiveIteratorIterator::CHILD_FIRST);
		foreach ($files as $file=>$mykey) {
			if(is_dir($file)) {
				$directory = $file;
				$subdir  = new RecursiveDirectoryIterator($directory, RecursiveDirectoryIterator::SKIP_DOTS);
				$subfiles = new RecursiveIteratorIterator($dir, RecursiveIteratorIterator::CHILD_FIRST);
				foreach ($subdir as $myfile=>$mykey) {
					$time=filemtime($myfile);
					$info=pathinfo($myfile);
					if($time>=$limit){
						$result[$info['dirname']][]=array(
							'filename'=>$myfile,
							'pathinfo'=>$info,
							'filetime'=>$time
						);
					}
				}
				if(!empty($subfiles)){
					$myfile=$mykey=false;
					foreach ($subfiles as $myfile=>$mykey) {
						if(!is_dir($myfile)){
							$time=filemtime($myfile);
							$info=pathinfo($myfile);
							if($time>=$limit){
								$result[$info['dirname']][]=array(
									'filename'=>$myfile,
									'pathinfo'=>$info,
									'filetime'=>$time
								);
							}
						}
					}
				}
			}else{
				$time=filemtime($file);
				if(!is_dir($file)){
					if($time>=$limit){
						$result[$dir->getPathname()][]=array(
							'filename'=>$file,
							'pathinfo'=>pathinfo($file),
							'filetime'=>filemtime($file)
						);
					}
				}
			}
		}
		return $result;
	}
	function serverBackup_list(){
		//display a list of files
		$log=array();
		$glue=','.PHP_EOL;
		//preME($this->FILES,2);
		if(is_array($this->FILES)){
			foreach($this->FILES as  $dir=>$files){
				foreach($files as $file){
					$log[]=str_replace(CACHE,'',$file['filename']);
				}
			}
		}else{
			$log[]='no files in array...';
		}
		
		echo renderCard_active('Backup List','<textarea style="height:25rem;">'.implode($glue,$log).'</textarea>',$this->SLIM->closer);
		die;
	}

	function serverBackup_save($files=false,$ajax=false){
		$log=array();
		if($ajax) $files=$this->serverBackup_list();
		if(!is_array($files)) die('no files in array...');
		foreach($files as  $file){
			$log[]=str_replace(CACHE,'',$file);
		}
		$data='<?php '."\n".'$zipMe=array("'.implode('","',$log).'");';
		$fpath=CACHE.$this->OPTIONS['destination_backup'].'.php';
		file_put_contents($fpath,$data);
		$msg='Okay, the server backup data is saved: '.$fpath;
		$url=$this->PERMLINK.'&act=serverbackup';
		setSystemResponse($url,$msg);
		die;
	}
	
	function serverBackup($post=false){
		$zipMe=($post)?$post:$this->serverBackup_load();
		$files=[];
		if($zipMe){
			$this->OPTIONS['destination']=CACHE.$this->OPTIONS['destination_backup'].'_backup.zip';
			$this->ZIP_PATH=$this->OPTIONS['destination'];
			foreach($zipMe as $file){
				$files[]=$file;
			}
		}else{
			die('backup file data not found...');
		}
		if($files){
			$this->zipFiles($files);
		}else{
			die('error doing server backup');
		}
	}
	function serverBackup_append(){
		$post=trim($this->POST['add_files']);
		$dest=issetCheck($this->POST,'dest_backup');
		if($post && $post!==''){
			$post=explode(',',$this->POST['add_files']);
			$zipMe=$this->serverBackup_load();
			$ct=0;
			foreach($post as $i=>$v){
				$target_file=str_replace(ROOT,'',$v);
				$src_file=ROOT.$target_file;
				if(!in_array($src_file,$zipMe)){
					if(file_exists($src_file)){
						$zipMe[]=$src_file;
						$ct++;
					}
				}
			}
		}
		if($ct>0){
			if($dest && $dest!==''){
				if($dest!==$this->OPTIONS['destination_backup']){
					//$this->OPTIONS['destination_backup']=$dest;
					//$this->saveConfig();
				}					
			}
			$this->serverBackup_save($zipME);
		}else{
			die('no file data was added to backup...');	
		}
	}
	function serverBackup_replace(){
		$post=explode(',',$this->POST['add_files']);
		$dest=issetCheck($this->POST,'dest_backup');
//		preME($post,2);
		$zipMe=false;
		if(is_array($post) && ! empty($post)){
			foreach($post as $i=>$v){
				$target_file=preg_replace('/\s\s+/', ' ', $v);
				$tp=explode('tbs4/',$target_file);
				//preME($tp,2);
				$src_file=ROOT.trim($tp[1]);
				if(file_exists($src_file)){
					$zipMe[]=$src_file;
				}
			}
			if($zipMe){
				if($dest && $dest!==''){
					if($dest!==$this->OPTIONS['destination_backup']){
						$this->OPTIONS['destination_backup']=$dest;
						$this->saveConfig();
					}					
				}
				$this->serverBackup_save($zipMe);
			}
		}else{
			die('no file data to backup...');
		}
	}
	function serverBackup_load(){
		$zfiles=CACHE.$this->OPTIONS['destination_backup'].'.php';
		//$zfiles=CACHE.'data/zipServer.php';
		$zipMe=false;
		if(file_exists($zfiles)){
			include_once $zfiles;
		}
		if(!$zipMe && $this->FILES){
			//preME($this->FILES,2);
			foreach($this->FILES as $f){
				foreach($f as $r) $zipMe[]=$r['filename'];
			}
		}
		return $zipMe;
	}

	function zipFiles($files=false){
		if(!is_array($files)) die(__METHOD__.': no files to archive...');
		//if(!$this->ZIP_PATH||$this->ZIP_PATH==='') die(__METHOD__.': no destination file...');
		if(!$this->OPTIONS['destination']||$this->OPTIONS['destination']==='') die('no destination file...');

		if(!extension_loaded('zip'))die(__METHOD__.': ZIP is not available on this server...');
		//if (!is_writable(dirname($this->ZIP_PATH))) die(__METHOD__.': destination directory ['.dirname($this->ZIP_PATH).'] is not writable...');
		if (!is_writable(dirname($this->ZIP_PATH))) die('destination directory ['.dirname($this->ZIP_PATH).'] is not writable...');
		if(file_exists($this->ZIP_PATH)) unlink($this->ZIP_PATH);
		$zip = new ZipArchive();
		//if ($zip->open($this->ZIP_PATH, ZipArchive::CREATE)) {
		if ($zip->open($this->ZIP_PATH, ZipArchive::CREATE)) {
			foreach($files as  $file){
				$target_file=str_replace(ROOT,'',$file);
				$src_file=ROOT.$target_file;
				if(file_exists($src_file)){
					 $zip->addFile($src_file,$target_file);
					 $log[]='adding '.$target_file.' :: '.$this->ZipStatusString($zip->status);
				}else{
					 die(__METHOD__.': file ['.$src_file.'] does not exist. ');
				}
			}
			$chk=$zip->close();
			$output='<div class="callout success"><h2>Files Zipped...</h2><p>The zip archive contains '.count($log).' files with a status of: '.$this->ZipStatusString($chk).'</p><p><a class="button" href="'.$this->PERMLINK.'&act=download&zip='.base64_encode($this->ZIP_PATH).'">Download the archive: '.$this->OPTIONS['destination'].'</a></p></div>';
			//$output='<div class="callout success"><h2>Files archived..</h2><p>The zip archive contains '.count($log).' files with a status of: '.$this->ZipStatusString($chk).'</p><p><a class="button" href="'.$this->PERMLINK.'/download">Download the archive: '.$this->ZIP_PATH.'</a></p></div>';
			$output.='<div class="callout"><h3>Log:</h3><p>'.implode('<br/>',$log).'</p></div>';
			$title=($this->POST['action']==='Backup Now')?'Backup':'Archive';
			$this->OUTPUT->set( 'TITLE', 'Make '.$title);
			$this->OUTPUT->set( 'CONTENT', $output);
			$this->OUTPUT->set( 'MENU', $this->renderTopControls(array('archive','serverbackup','download')));
			//$this->OUTPUT->set( 'TOP_CONTROLS', '<a class="button small secondary" href="'.$this->PERMLINK.'">Scan Results</a> <a class="button warning" title="download the archive" href="'.$this->PERMLINK.'download">Download</a>');
		}else{
			die(__METHOD__.': error opening/creating destination file ['.$this->ZIP_PATH.']...<br/>'.$this->ZipStatusString($zip->status));
		}
	}
	
	function ZipStatusString( $status ){
		switch( (int) $status ) {
			case ZipArchive::ER_OK           : return 'N No error';
			case ZipArchive::ER_MULTIDISK    : return 'N Multi-disk zip archives not supported';
			case ZipArchive::ER_RENAME       : return 'S Renaming temporary file failed';
			case ZipArchive::ER_CLOSE        : return 'S Closing zip archive failed';
			case ZipArchive::ER_SEEK         : return 'S Seek error';
			case ZipArchive::ER_READ         : return 'S Read error';
			case ZipArchive::ER_WRITE        : return 'S Write error';
			case ZipArchive::ER_CRC          : return 'N CRC error';
			case ZipArchive::ER_ZIPCLOSED    : return 'N Containing zip archive was closed';
			case ZipArchive::ER_NOENT        : return 'N No such file';
			case ZipArchive::ER_EXISTS       : return 'N File already exists';
			case ZipArchive::ER_OPEN         : return 'S Can\'t open file';
			case ZipArchive::ER_TMPOPEN      : return 'S Failure to create temporary file';
			case ZipArchive::ER_ZLIB         : return 'Z Zlib error';
			case ZipArchive::ER_MEMORY       : return 'N Malloc failure';
			case ZipArchive::ER_CHANGED      : return 'N Entry has been changed';
			case ZipArchive::ER_COMPNOTSUPP  : return 'N Compression method not supported';
			case ZipArchive::ER_EOF          : return 'N Premature EOF';
			case ZipArchive::ER_INVAL        : return 'N Invalid argument';
			case ZipArchive::ER_NOZIP        : return 'N Not a zip archive';
			case ZipArchive::ER_INTERNAL     : return 'N Internal error';
			case ZipArchive::ER_INCONS       : return 'N Zip archive inconsistent';
			case ZipArchive::ER_REMOVE       : return 'S Can\'t remove file';
			case ZipArchive::ER_DELETED      : return 'N Entry has been deleted';
		   
			default: return sprintf('Unknown status %s', $status );
		}
	}
	
	function downloadArchive(){
		if(file_exists($this->ZIP_PATH)){
			header('Content-Type: application/zip');
			header('Content-disposition: attachment; filename='.$this->OPTIONS['destination']);
			header('Content-Length: ' . filesize($this->ZIP_PATH));
			readfile($this->OPTIONS['destination']);
			die;
		}else{
			die(__METHOD__.': The archive ['.$this->ZIP_PATH.'] does not exist...');
		}		
	}
	
	function getOptions(){
		$rec=$this->DB->Options->Select('id,OptionValue')->where('OptionName',$this->OPT_NAME)->limit(1);
		$res=renderResultsORM($rec,'id');
		if($res){
			$this->OPT_ID=key($res);
			$this->OPT_REC=current($res);
			$this->OPTIONS=compress($this->OPT_REC['OptionValue'],0);
		}
	}
	
	function saveOptions(){
		$opts=$this->OPT_REC;
		$id=(int)issetCheck($opts,'id');
		$db=$this->DB->Options;
		if($id){
			$rec=$db->where("id", $id);
			$chk=$rec->update($opts);
		}else{
			$chk=$db->insert($opts);
			if(!$chk) preME((string)$db,2);
		}
		return $chk;
	}
}
