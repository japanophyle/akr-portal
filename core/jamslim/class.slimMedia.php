<?php

class slimMedia{
	private $SLIM;
	private $DB;
	private $ITYPE;
	private $ROUTE;
	private $CPATH;
	private $PATHS;
	private $POST;
	private $MEDIA=array('document','image','audio','video');
	private $MEDIA_EXT;
	private $ROOT='content/library/docs/';
	private $ITEMID;
	private $PERMLINK;
	private $PERMBACK;
	private $ICON='mediaman';
	private $ERR;
	private $UPLOADER;//current upload engine
	private $AJAX;
	private $ITEMS;
	private $LOADER;
	private $ACTION;
	private $FORM;
   
    function __construct($slim=null){
		if(!$slim) throw new Exception(__METHOD__.': no slim object!!');
		$this->SLIM=$slim;
		$this->DB=$slim->db;
		$this->LOADER=$this->PERMBACK=URL.'admin/media/';
		$this->POST=$slim->router->get('post');
		$this->ROUTE=$slim->router->get('route');
		$this->ITYPE=issetCheck($this->ROUTE,2,'image');
		$this->PERMLINK=URL.'admin/media/'.$this->ITYPE.'/';
		$this->AJAX=$slim->router->get('ajax');
		$this->ACTION=issetCheck($this->ROUTE,3);
		$this->ITEMID=issetCheck($this->ROUTE,4);
		$cpath=issetCheck($_SESSION,'mlib_path',$this->ROOT);
		$this->CPATH=str_replace('//','/',$cpath);
		$this->FORM=new jamForm;
		//set media extentions
		$this->setPaths();
		$this->setExt();
	}
   
	private function init($options=false){
		//set currrent path
		$this->CPATH=issetCheck($options,'path',$this->CPATH);
		if(isset($options['path'])) $_SESSION['mlib_path']=$options['path'];
		//set action
		$this->ACTION=issetCheck($options,'action',$this->ACTION);
		if($this->POST && !issetCheck($this->POST,'action')){
			if(isset($this->POST['imgUrl'])){
				$this->POST['action']='upload_crop';
			}else if($_FILES){
				$this->POST['action']='upload';
			}
		}
		$this->setUploader();
		//set records
		$this->ITEMID=issetCheck($options,'item');
		$this->getItems();
	}
	private function setUploader(){
		if($this->ITYPE==='image'){
			$this->UPLOADER=($this->AJAX)?'multi':issetCheck($_GET,'upl','multi');			
		}else{
			$this->UPLOADER='default';
		}
	}
 	private function getUploader(){
		switch($this->UPLOADER){
			case 'crop':
				$up=$this->showCropUploader();
				break;
			case 'multi':
				$up=$this->showDropzoneUploader();
				break;
			default:
				$up=$this->showUploader();
		}
		return $up;	
	}
    function Process(){
	    $this->init();
        //post actions
		$action=$this->ACTION;
		if($this->POST){
			$paction=issetCheck($this->POST,'action');
			switch($paction){
				case 'save':
				   $this->ERR[]=$this->updateItem($this->POST);
				   $action='showitem';
				   break;
				case 'delete':
				   $this->ERR[]=$this->deleteItem($this->POST);
				   $action=false;
				   $this->ITEMID=false;
				   $this->getItems();
				   break;
				case 'upload_crop':
				   $this->doUpload_crop();
				   break;
				case 'upload_file':
				   $this->doUpload_file();
				   break;
			}
		}
		//display actions
		if(!$action && $this->ITEMID) $action='showitem';
        switch($action){
			case 'showitem':
				$output=$this->showItem();
				break;
			case 'upload':
				$output=($this->UPLOADER==='crop')?$this->showCropUploader():$this->showUploader();
				break;
			default:
				$output['icon']=$this->ICON;
				$output['title']='Upload Media';
				$output['content']='<div class="callout alert">'.$action.'</div>';	  

		}
		if($this->ERR){
			$output['message']=$this->ERR;
		}
		return $output;
	}
	
	function get($what=false,$vars=false){
		switch($what){
			case 'media_type':
				$this->ITYPE=$vars;
				$this->ITEMID=false;
				$this->getItems();
				return $this->ITEMS;
			case 'media_id':
				$this->ITYPE=false;
				$this->ITEMID=$vars;
				$this->getItems();
				return $this->ITEMS;
			case 'media_new':
				return array(
					'mda_type'=>'',
					'mda_path'=>'',
					'mda_filename'=>'',
					'mda_nice_name'=>'',
					'mda_date'=>date('Y-m-d H:i:s'),
				);
			case 'uploader':
				$this->setUploader();
				$output=$this->getUploader();
				return $output;
				break;
			default:
				return false;
		}
	}
/// data functions   
	private function setExt(){
		$this->MEDIA_EXT['image']=array('.jpg','.gif','.png');
		$this->MEDIA_EXT['document']=array('.doc','.pdf','.txt','.rtf','.xls','xlsx');
		$this->MEDIA_EXT['audio']=array('.mp3','.wma');
		$this->MEDIA_EXT['video']=array('.mpg','.mov','.wmv','.avi');
	}
   
	private function setPaths(){
		$rez=$this->DB->Media->select('DISTINCT mda_path');
		$chk=renderResultsORM($rez);
		$paths=[];
		foreach($chk as $rec){
			if(issetOR($rec['mda_path'])) $paths[]=str_replace('//','/',$rec['mda_path']);
		}
		$this->PATHS=$paths;
    }

    private function getItems(){
		$rez=$this->DB->Media();
		$chk=renderResultsORM($rez);
		if($this->ITEMID){
			$rez->where('mda_id',$this->ITEMID);
		}elseif($this->ITYPE){//media browser
			$rez->where('mda_type',$this->ITYPE)->order('mda_date DESC');
		}else{
			$rez->order('mda_date DESC');
		}
		$chk=renderResultsORM($rez,'mda_id');
		if(is_array($chk)){
			$this->ITEMS=$chk;
		}else{
			$this->ERR[]='Sorry, no records found...';
		}
	}
   
	private function updateItem($details){
		$chk=checkToken($details['token']);
		if($chk) return $chk;
		$rez=$this->DB->Media->where('mda_id',$details['mda_id']);
		if(count($rez)>0){
			$chk=$rez->update($details);
			if(!$chk){
				return 'Sorry, there was a problem updating the record.';
			}else{
				return 'Okay, the record has been updated.';
			}
		}else{
			return 'Sorry, I could not find a record to update.';
		}
	}
   
	function deleteItem($details){
		$chk=checkToken($details['token']);
		if($chk) return $chk;
		$rez=$this->DB->Media->where('mda_id',$details['mda_id']);
		if(count($rez)>0){
			$chk=$rez->update($details);
			if(!$chk){
				return 'Sorry, there was a problem deleting the record.';
			}else{
				return 'Okay, the record has been deleted.';
			}
		}else{
			return 'Sorry, I could not find a record to delete.';
		}
	}
  
//display functions
   function showItems(){
      $TBL=$this->MPM;
	  $pmy=$TBL->primary;
   	  $td=$tr=$th=$thead=false;
	  $widths=array('tiny','','','thin','');
	  $order=array(0,3,4,5,1);
	  $odd='odd';
	  foreach($this->ITEMS as $rec){
         $ct=0;
		 foreach($order as $key){
		    $i=$TBL->fieldlist[$key];
			$v=$rec->$i;
		    $tdClass=($widths[$ct])?'class="'.$widths[$ct].'"':'';
			$src=($rec->mda_type=='image')?$this->getThumb($rec->mda_path,$rec->mda_filename):$this->getMediaIcon($rec->mda_filename);
			$iwidth=($rec->mda_type=='image')?80:64;
		    $type=$TBL->fieldtype[$key];
			if(in_array($type,array('int','str','dte'))){
			   if(!$thead){
			      $th.='<th '.$tdClass.'>'.ucwords(strtolower($TBL->labels[$key])).'</th>';
				  if($key==0)$th.='<th '.$tdClass.'>Preview</th>';
			   }
		       $td.=($key==5)?'<td '.$tdClass.'>'.date("d/m/Y",strtotime($v)).'</td>':'<td '.$tdClass.'>'.fixHTML($v).'</td>';
			   if($key==0)$td.='<td '.$tdClass.'><img src="'.$src.'" width="'.$iwidth.'" /></td>';
			   $ct++;
		    }
		 }	     
		 $view=' <a class="loadME" href="'.$this->LOADER.'?c=mediaview&amp;i='.$rec->$pmy.'">VIEW</a>';
		 $td.='<td><a href="'.$this->PERMLINK.'&p='.$rec->$pmy.'">EDIT</a>'.$view.'</td>';
		 $tr.='<tr class="'.$odd.'">'.$td.'</tr>';
		 if(!$thead) $thead='<tr>'.$th.'<th>Controls</th></tr>';
		 $td='';
		 $odd=($odd=='odd')?'even':'odd';
	  }
	  if(!$tr) $tr='<tr class="'.$odd.'"><td class="alert" colspan="9">Sorry, no items found in "'.$this->CPATH.'"</tr>';
	  $filter='<tr><th>Data Filter:</th><th colspan="8"><input type="text" class="text" id="filterMe" value="" /></th></tr>';
	  $table='<table id="dataTable" class="filterme"><thead>'.$thead.'</thead><tfoot>'.$thead.'</tfoot></tbody>'.$tr.'</tbody></table>';

	  $out['icon']=$this->ICON;
	  $out['title']='Media Library';
	  $out['buttons']='<li><a class="btn ui-corner-all" title="upload files" href="'.$this->PERMLINK.'&act=upload">Upload Files</a></li>';
	  $out['content']=$table;
	  $out['desc']="&bull; All images for the site <strong>must</strong> be in the Media Library.<br/>&bull; To edit an item, click the 'edit' link on the relevant row.<br/>&bull; Use the Filter to quickly search through the list.<br/>&bull; Click the column headers to sort the table.";
	  return $out;
   }
   
   function pathNav(){
      $p=explode('/',$this->CPATH);
	  $nav='<li>Path:&nbsp;</li>';
	  $lnk='';
	  echo $this->CPATH;
	  foreach($p as $v){
	    if($v!==''){
		   $lnk.=$v.'/';
		   $href='#nogo';
		   $class=($this->CPATH==$lnk)?'active':'';
		   $title='current';
		   if($class!='active'){
		      $v.=' &raquo;';
			  $href=$this->PERMLINK.'&amp;dir='.$lnk;
			  $title='view';
		   }
		   $nav.='<li><a class="btn ui-corner-all '.$class.'" href="'.$href.'" title="'.$title.' directory ">'.$v.'</a></li>';
		}
	  }
	  return $nav;
   }
   
   function showItem(){
   	  $hidden=$td=$trow=$th=$thead=$size=false;	  
	  $widths=array('thin','','','thin','');
	  $order=array(0,3,4,1);
	  $odd='odd';
      $tpl='<tr><th class="thin">{0}</th><td>{1}</td></tr>';
	  $ct=0;
	  $iclass="class='text input-medium'";
	  foreach($this->ITEMS as $rec){
	     //set image
	     $src=($rec->mda_type=='image')?$rec->mda_path.$rec->mda_filename:$this->getMediaIcon($rec->mda_filename);
		 if($rec->mda_type=='image'){
		    $idata=getimagesize($src);
		    $height=($idata[1]>300)?'height:300px;':false;
		    $width=(!$height && $idata[0]>500)?'width:500px;':false;
		    $src='<img style="display:block; '.$width.$height.'" src="'.$src.'"/ >';
		    $tmp=str_replace('{0}','Image Full Size',$tpl);
		    $size=str_replace('{1}',$idata[3],$tmp);
		 }else{
		    $src='<img style="display:block; height:64px;" src="'.$src.'"/ >';
		 }
		 $tmp=str_replace('{0}','Preview',$tpl);
		 $image=str_replace('{1}',$src,$tmp);
		 foreach($rec as $i=>$v){
		    if(in_array($i,array('mda_id','mda_nice_name'))){
			  if($i=='mda_id') $hidden.="<input name='$i' value='$v' type='hidden' />";
			  if($i=='mda_nice_name'){
			    $v=fixHTML($v);
				$v="<input $iclass name='$i' value='$v' type='text' />";
			    $tmp=str_replace('{0}',ucwords(strtolower($TBL->labels[$ct])),$tpl);
			    $nice=str_replace('{1}',$v,$tmp);
			  }
			}else{
			  $tmp=str_replace('{0}',ucwords(strtolower($TBL->labels[$ct])),$tpl);
			  $tmp=str_replace('{1}',fixHTML($v),$tmp);
			  $trow.=$tmp;
			}
			$ct++;
		 }
	  }
	  $hidden.=$this->FORM->hidden(array('name'=>'action', 'value'=>'save'));
	  $hidden.=$this->FORM->hidden(array('name'=>'tbl', 'value'=>'Media'));
	  $hidden.=$this->FORM->hidden(array('name'=>'token', 'value'=>$this->FORM->setToken(), 'html'=>''));
	  $controls='<input type="submit" value="Save" class="ui-state-default green ui-corner-all" />';
	  $controls.='<input type="button" value="Detete" id="deleteMe" class="ui-state-default red ui-corner-all" />';
	  $controls.='<input type="button" value="Cancel" id="backButton" class="ui-state-default ui-corner-all" />';
	  $controls='<div class="fcontrols">'.$controls.'</div>';
	  $table='<form id="form1" name="form1" method="post" action="'.$this->PERMLINK.'&amp;p='.$this->ITEMID.'"><table><tr><th colspan="2">Media Item Settings</th></tr><tr><th>Item</th><th>Value</th></tr>'.$image.$nice.$trow.$size.'</table>'.$hidden.$controls.'</form>';
	  $out['icon']=$this->ICON;
	  $out['title']='Edit Media Item';
	  $out['buttons']='<li><a class="btn ui-corner-all" title="upload files" href="'.$this->PERMLINK.'&act=upload">Upload Files</a></li><li><a class="btn ui-corner-all" title="Meida library" href="'.$this->PERMLINK.'">Back to list</a></li>';
	  $out['content']=$table;
	  $out['desc']="This page allows you to set various options for the item.";
	  return $out;
   }
   
   function showUploader(){
      $output=file_get_contents(TEMPLATES.'parts/tpl.uploader.html');
      $output=str_replace('{url}',URL.'upload.php?dir=content/upload/',$output);
	  $desc="<small>Drag the item you want to upload from your machine and drop into the area below (or click it to browse for files).<br/>Click the 'Upload File' button to add the item into the media library.</small>";
      //$out['script']="function createUploader(){var uploader = new qq.FileUploader({element: document.getElementById('dropable'),action: '".URL."/uploader.php?dir=content/upload/',debug: true, onComplete: function(id, fileName, responseJSON){}});} window.onload = createUploader; ";	  
	  if($this->AJAX){
		$output.='<script>$(function() {initUploader();});</script>';
	  }else{
		$this->SLIM->assets->set('js', 'initUploader();','uploader'); 
	  }
	  $out['icon']=$this->ICON;
	  $out['title']='Upload Media';
	  $out['content']='<div class="callout">'.$desc.'</div>'.$output;	  
	  return $out;
   }
   function showCropUploader(){
        $opts['adminpage']=$this->PERMLINK;
        $opts['uploadphp']=URL.'upload.php?dir=content/upload/';
        $CP=new cropUpload($this->SLIM,$opts);
        $output=$CP->Process();
        $out['icon'] = $this->ICON;
        $out['title'] = $output['title'];
        $out['item_title'] = '<span>'.$output['sub_title'].'<span>';
        $buts[] = array('label' => 'Meida library', 'href' => $this->PERMLINK, 'class' => 'secondary', 'title' => 'back to Meida library');
        $out['script']=$output['js'];
        $out['buttons'] = $buts;
        $out['content'] = $output['contents'];
        return $out;
    }
    function showDropzoneUploader(){
	  $js='Dropzone.autoDiscover = false;
		var MDZ= new Dropzone(".dropzone",{
			paramName: "userImage", 
			maxFilesize: 2,
			maxFiles:10,
			acceptedFiles: "image/*,application/pdf",
			accept: function(file, done) {
				if (file.name == "justinbieber.jpg") {
				  done("Naha, you don\'t.");
				}else { 
				  done(); 
				}
			}
		});';
      $output=file_get_contents(TEMPLATES.'parts/tpl.dz_uploader.html');
      $output=str_replace('{url}',URL.'upload.php?dir=content/upload/',$output);
	  $desc="<small>Drag the items you want to upload from your machine and drop into the area below (or click it to browse for files).<br/>The upload will start automatically and will queue other files in the background.<br/>Progress and results will be displayed in the \"dropzone\" area.<br/><span class='text-dark-blue'>Note that you can upload upto 10 items at once (depending on bandwidth and server resources.)</span></small>";
	  $out['icon']=$this->ICON;
	  $out['title']='Upload Media: Multiple Files';
	  $out['content']='<div class="callout">'.$desc.'</div>'.$output;
	  if($this->AJAX){
		  $out['content'].="\n<script>$js</script>";
	  }else{
		  $this->SLIM->assets->set('js', $js,'dropzone');		  
	  }	  
	  return $out;
   }
   function doUpload_crop(){
        $opts['adminpage']=$this->PERMLINK;
        $opts['uploadphp']=URL.'upload.php?dir=content/upload/';
        $CP=new cropUpload($this->SLIM,$opts);
        $output=$CP->Process();
        echo jsonResponse($output);
        die;
	}
// helper functions
   function checkMediaType($fname){
      $out='unknown';
      $chk = strtolower( substr( $fname, -4 ) );
      if(in_array($chk,$this->MEDIA_EXT['image'])) $out= 'image';
      if(in_array($chk,$this->MEDIA_EXT['document'])) $out= 'document';
      if(in_array($chk,$this->MEDIA_EXT['video'])) $out= 'video';
      if(in_array($chk,$this->MEDIA_EXT['audio'])) $out= 'audio';
      return $out;
   }
   
   function getMediaIcon($fname){
      $chk = strtolower( substr( $fname, -4 ) );
	  if(in_array($chk,$this->MEDIA_EXT['document'])){
	     $src='gfx/admin/icons/'.strtolower( substr( $fname, -3 ) ).'.png';
	  }else if(in_array($chk,$this->MEDIA_EXT['audio'])){
	     $src='gfx/admin/icons/audio.png';
	  }else if(in_array($chk,$this->MEDIA_EXT['video'])){
	     $src='gfx/admin/icons/video.png';
	  }else{
	     $src='gfx/admin/icons/unknown.png';
	  }
	  return $src;
   }
   
   function getThumb($path,$filename){
      $ext=$this->getExtension($filename);
	  $thmName=str_replace($ext,'thm.'.$ext,$filename);
	  if(!file_exists($path.$thmName)){
		$opts['src_path']=$path.$filename;
		$opts['new_path']=$path.$thmName;
		$opts['neww']=$opts['newh']=100;
		$IMG= new image;		   
		$thm=$IMG->_get('makeThumb',$opts);
		 if(!$thm || is_string($thm)){
		    $out=$path.$filename;
		 }else{
		    $out=$path.$thmName;
		 }
	  }else{
	     $out=$path.$thmName;
	  }
	  return $out;
   }
   
   function getExtension( $str ){
       $i = strrpos( $str, "." );
       if( !$i ) return "";
       $l = strlen( $str ) - $i;
       $ext = substr( $str, $i+1, $l );
       return strtolower($ext);
   } 
  
}
