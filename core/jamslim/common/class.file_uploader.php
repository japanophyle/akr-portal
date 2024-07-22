<?php

class file_uploader {
	private $SLIM;
	private $DB;
	private $FIELD_NAME='qqfile';
    private $allowedExtensions = array();
    private $sizeLimit = 10485760;
    private $file;
	private $MEDIA_EXT= array();
	private $MEDIA_ROOT='public/content/library/';
	private $UPLOAD_DIR='public/content/upload/';
	private $MEDIA_DIR;
    private $MEDIA= array();
    private $MAKE_THUMBNAILS=true;
	
    function __construct($slim){  
		if(!$slim) throw new Exception(__METHOD__.': no slim object!!');
		$this->SLIM=$slim;
		$this->DB=$slim->db;
        $this->MEDIA_EXT['image']=array('jpg','gif','png');
        $this->MEDIA_EXT['document']=array('doc','docx','pdf','txt','rtf','xls','xlsx');
        $this->MEDIA_EXT['audio']=array('mp3','wma');
        $this->MEDIA_EXT['video']=array('mpg','mov','wmv','avi');
		$this->MEDIA=array('image'=>'images','document'=>'docs','audio'=>'audio','video'=>'video');
		$this->MEDIA_DIR=date('Y_m').'/';
		$this->UPLOAD_DIR=FILE_ROOT.$this->UPLOAD_DIR;
		$allowedExtensions = array_merge($this->MEDIA_EXT['image'],$this->MEDIA_EXT['document'],$this->MEDIA_EXT['audio'],$this->MEDIA_EXT['video']);
        $allowedExtensions = array_map("strtolower", $allowedExtensions);
        $this->MAKE_THUMBNAILS= $slim->Options->getSiteOptions('medialib_thumbnails',true);
            
        $this->allowedExtensions = $allowedExtensions;        
     
        $this->checkServerSettings();  
    }
    
    private function setUploader(){
        if (isset($_GET[$this->FIELD_NAME])) {
            $this->file = new file_uploader_ajax($this->FIELD_NAME);
        } elseif (isset($_FILES[$this->FIELD_NAME])) {
            $this->file = new file_uploader_form($this->FIELD_NAME);
        } else {
            $this->file = false; 
        }
	}
	
	public function set($what=false,$vars=false){
		switch($what){
			case 'size_limit':
				if((int)$vars>1000) $this->sizeLimit = $vars;
				break;
			case 'field_name':
				if(is_string($vars) && $vars!=='') $this->FIELD_NAME = $vars;
				break;
			case 'media_root':
				if(is_string($vars) && $vars!==''){
					if(is_dir(ROOT.$vars))	$this->MEDIA_ROOT = $vars;
				}
				break;
		}
	}
	public function getName(){
		if ($this->file) return $this->file->getName();
	}

    private function checkFileType($ext){
	  //determines which folder to move the uploaded file to
      $out='unknown';
      $ext=strtolower($ext);
      if(in_array($ext,$this->MEDIA_EXT['image'])) $out= 'image';
      if(in_array($ext,$this->MEDIA_EXT['document'])) $out= 'document';
      if(in_array($ext,$this->MEDIA_EXT['video'])) $out= 'video';
      if(in_array($ext,$this->MEDIA_EXT['audio'])) $out= 'audio';
      return $out;
    }
	
    private function checkServerSettings(){        
        $postSize = $this->toBytes(ini_get('post_max_size'));
        $uploadSize = $this->toBytes(ini_get('upload_max_filesize'));        
        
        if ($postSize > $this->sizeLimit){
			$this->sizeLimit=$postSize;
            //$size = max(1, $this->sizeLimit / 1024 / 1024) . 'M';             
            //throw new Exception("{'error':'increase post_max_size and upload_max_filesize to $size ($postSize)'}");    
        }        
    }
    
    private function toBytes($str){
        $val = (int)trim($str);
        $last = strtolower($str[strlen($str)-1]);
        switch($last) {
            case 'g': $val *= 1024;
            case 'm': $val *= 1024;
            case 'k': $val *= 1024;        
        }
        return $val;
    }
    
    /**
     * Returns array('success'=>true) or array('error'=>'error message')
     */
    function handleUpload($uploadDirectory=false, $replaceOldFile = FALSE){
        $this->setUploader();     
		if(is_string($uploadDirectory)) $this->UPLOAD_DIR=FILE_ROOT.$uploadDirectory;
        if (!is_writable($this->UPLOAD_DIR)){
            return array('error' => "Server error. Upload directory isn't writable. [{$this->UPLOAD_DIR}]");
        }
        
        if (!$this->file){
            return array('error' => 'No files were uploaded.');
        }
        
        $size = $this->file->getSize();
        
        if ($size == 0) {
            return array('error' => 'File is empty');
        }
        
        if ($size > $this->sizeLimit) {
            return array('error' => 'File is too large');
        }
        
        $pathinfo = pathinfo($this->file->getName());
        $filename = $pathinfo['filename'];
        $ext = issetCheck($pathinfo,'extension');		// hide notices if extension is empty
        $ftype=$this->checkFileType($ext);

        if($this->allowedExtensions && !in_array(strtolower($ext), $this->allowedExtensions)){
            $these = implode(', ', $this->allowedExtensions);
            return array('error' => 'File has an invalid extension, it should be one of '. $these . '.');
        }
        
        if($replaceOldFile){
            /// don't overwrite previous files that were uploaded
            while (file_exists($this->UPLOAD_DIR . $filename . '.' . $ext)) {
                $filename .= rand(10, 99);
            }
        }
        
        $fpath=$this->UPLOAD_DIR . $filename . '.' . $ext;
        
        if ($this->file->save($fpath)){
			if($ftype==='image'){
				$this->compressImage($fpath);	
			}
		    $chk=$this->AddtoLibrary($filename, $ext);
			if($chk=='ok'){
               return array('message'=>'Okay, the file was uploaded and added to the media library.');
			}else{
			   return array('error'=> 'The file was uploaded, but I could not add it to the Media Library.'.$chk);
			}
        } else {
            return array('error'=> 'Could not save uploaded file. The upload was cancelled, or server error encountered.');
        }
        
    }
	private function compressImage($source_image, $target_image=false){
		if(!$target_image) $target_image=$source_image;
		if(file_exists($source_image)){
			$image_info = getimagesize($source_image);
			switch($image_info['mime']){
				case 'image/jpeg':
					$source_image = imagecreatefromjpeg($source_image);
					imagejpeg($source_image, $target_image, 75);
					break;
				case 'image/gif':
					$source_image = imagecreatefromgif($source_image);
					imagegif($source_image, $target_image, 75);
					break;
				case 'image/png':
					$source_image = imagecreatefrompng($source_image);
					imagesavealpha($source_image, true);
					imagepng($source_image, $target_image, 6);
					break;
			}
			if(is_object($source_image)) imagedestroy($source_image);
		}
		return $target_image;
	}	
	private function AddtoLibrary($filename, $ext){
	    $result=false;
	    $nfilename= preg_replace('/[^A-Za-z0-9\. _-]/', '', $filename);
		$mType=$this->checkFileType($ext);
		$mName=$filename.'.'.$ext;
		$nName=$nfilename.'.'.$ext;
		$mPath=$this->MEDIA_ROOT.$this->MEDIA[$mType].'/'.$this->MEDIA_DIR;
		$mTarget=FILE_ROOT.$mPath.$nName;
		$src=$this->UPLOAD_DIR.$mName;
		//move file
		if(!$this->checkPath($mPath)) return "Sorry, I could not create the folder : $mPath";
		$chk=rename ($src, $mTarget);
		if($chk){
		   //make thumbnail
			if($mType==='image'){
				if($this->MAKE_THUMBNAILS){
					$thmTarget=FILE_ROOT.$mPath.$nfilename.'.thm.'.$ext;
					$opts['src_path']=$mTarget;
					$opts['new_path']=$thmTarget;
					$opts['neww']=$opts['newh']=100;
					$IMG= new image;		   
					$thm=$IMG->_get('makeThumb',$opts);
				}
			}
		    //add to media lib
		    $mDate = date("Y-m-d", filectime($mTarget));
		    $nice = $this->nicename($mName,$mType);
		    $mPath=str_replace('public/','',$mPath);//fix path for public
		    $chk=$this->insert_record($mName, $mDate,$mType,$mPath,$nice);
		    if($chk===1){
		       $result='ok';
		    }else{
		       $result=$chk;
		    }
		}else{
			$result="Sorry, I could not move the uploaded file to : $mTarget";
		}
		return $result;	
	}
	
	private function checkPath($path){
		if(substr($path, -1) == '/') {
			$path = substr($path, 0, -1);
		}	
		if(is_dir(FILE_ROOT.$path)){
			return true;
		}else{
			if(mkdir(FILE_ROOT.$path, 0755)){
				return true; 
			}
			return false;
		}
	}
	
	private function insert_record($name, $mod_date,$ftype,$fpath,$nice) {
	    $exists=$this->inDB($name);
	    if(!$exists){
			$insert=array(
				'mda_filename'=>$name,
				'mda_nice_name'=>$nice,
				'mda_date'=>$mod_date,
				'mda_type'=>$ftype,
				'mda_path'=>$fpath
			);
			$db=$this->DB->Media;
			$chk=$db->insert($insert);
			return ($chk)?1:'Sorry, there was a problem adding record to database';
	    }else{
			return '['.$name.'] is already in the media library.';
	    }
    }

	private function inDB($fname){
		$rez=$this->DB->Media->where('mda_filename',$fname);
		if(count($rez)>0) return true;
        return false;
    }	  

	private function nicename($fname,$type){
	    $tmp=$this->MEDIA_EXT[$type];
		$out=str_replace($tmp,'',$fname);
	    $out=rtrim($out,'.');
        $out=str_replace(array('_','-'),' ',strtolower($out));
        return ucwords($out);
    }

}

/**
 * Handle file uploads via ajax
 */
class file_uploader_ajax {
    /**
     * Save the file to the specified path
     * @return boolean TRUE on success
     */
	var $fieldname;
	function __construct($fieldname='qqfile'){
		$this->fieldname=$fieldname;
	}
    function save($path) {    
        $input = fopen("php://input", "r");
        $temp = tmpfile();
        $realSize = stream_copy_to_stream($input, $temp);
        fclose($input);
        
        if ($realSize != $this->getSize()){            
            return false;
        }
        
        $target = fopen($path, "w");        
        fseek($temp, 0, SEEK_SET);
        stream_copy_to_stream($temp, $target);
        fclose($target);
        return true;
    }
    function getName() {
        return $_GET[$this->fieldname];
    }
    function getSize() {
        if (isset($_SERVER["CONTENT_LENGTH"])){
            return (int)$_SERVER["CONTENT_LENGTH"];            
        } else {
            throw new Exception('Getting content length is not supported.');
        }      
    }   
}

/**
 * Handle file uploads via regular form post (uses the $_FILES array)
 */
class file_uploader_form {  
    /**
     * Save the file to the specified path
     * @return boolean TRUE on success
     */
	var $fieldname;
	function __construct($fieldname='qqfile'){
		$this->fieldname=$fieldname;
	}
    function save($path) {
        if(!move_uploaded_file($_FILES[$this->fieldname]['tmp_name'], $path)){
            return false;
        }
        return true;
    }
    function getName() {
        return $_FILES[$this->fieldname]['name'];
    }
    function getSize() {
        return $_FILES[$this->fieldname]['size'];
    }
}
