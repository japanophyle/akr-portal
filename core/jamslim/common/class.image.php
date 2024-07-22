<?php
//statitc image functions
class image {
	var $PATH;
	var $FILENAME;
	var $FILESIZE;
	var $FILESIZE_H;
	var $EXT;
	var $HEIGHT;
	VAR $WIDTH;
	var $READY;
	var $ROOT;
	var $URLBASE;
	var $DEFAULT_IMAGE;
	
	function __construct(){
		$this->init();
	}
	
	public function _get($function=false,$args=false){
		if($function && method_exists($this,$function)){
			return $this->{$function}($args);
		}else{
			throw new Exception($function.' not found');
		}
	}
	
	private function init(){
		$this->ROOT=FILE_ROOT;	
		$this->URLBASE=(defined('URL'))?URL:URLBASE;	
		$this->WIDTH=$this->HEIGHT=0;
		$this->PATH=$this->FILENAME=$this->FILESIZE=$this->EXT=$this->READY=false;
		$this->DEFAULT_IMAGE='gfx/noimage.jpg';
	}
	
	private function resize($args){
		$src=$args['src'];
		$maxw=$args['maxw'];
		$maxh=$args['maxh'];
		$type=$args['src'];
		$this->scanImage($src);
		$output=false;
		if($this->READY){
			$newsize=$this->calculate_newsize($maxw, $maxh);
			if($type==='html'){
				$neww=$newsize['w'];
				$newh=$newsize['h'];
				if(!$neww && !$newh){
					$output="<img src='$src' style='width:auto;' />";
				}else if($neww && !$newh){
					$output="<img src='$src' style='width:{$neww}px;' />";
				}else if($newh && !$neww){
					$output="<img src='$src' style='height:{$newh}px;' />";
				}else{
					$output="<img src='$src' style='width:{$neww}px; height:{$newh}px;' />";
				}
			}else{
				$output=$newsize;
			}
		}else{
			$output='image: not ready...';
		}
		return $output;	
	}
	
	private function simpleThumb($args){
		$src=$args['src'];
		$w=$args['w'];
		$h=$args['h'];
		$style=$args['style'];
		$this->scanImage($args);
		if($this->READY){
			$newsize=$this->calculate_newsize($w, $h);
			$neww=$newsize['w'];
			$newh=$newsize['h'];
			$http_src=str_replace($this->ROOT,$this->URLBASE,$src);
		
			// styling
			switch ($style) {
				case 'standard':
					$hfix = ($newh < $h) ? "style=\"margin-top:" . (($h - $newh) / 2) . "px;\" " : "";
					break;
				case 'folio':
					$hfix = ($newh < $h) ? "style=\"margin-top:" . ($h - $newh) . "px;\" " : "";
					break;
				case 'height':
					$newh=$h.'px';
					$hfix='style="width:auto; height:'.$newh.'" ';
					$neww='auto';
					break;
				case 'width':
					$neww=$w.'px';
					$hfix='style="height:auto; width:'.$neww.'" ';
					$newh='auto';
					break;
				default:
					$hfix = "";
			}

			$output['src'] = "src=\"" . $http_src . "\" width=\"" . $neww . "\" height=\"" . $newh . "\" $hfix ";
			$output['h'] = $newh;
			$output['w'] = $neww;
		}else{
			$output='image: not ready...';
		}
		return $output;		
	}
	
	private function hasThumb($args) {
		$path=$args['path'];
		$type=$args['type'];
		$output = $this->DEFAULT_IMAGE;
		$this->scanImage($path);
		if($this->READY){
			if (!$type) {
				$ext = $this->EXT;
				$thumb = str_replace($ext, 'thm.' . $ext, $path);
				if (file_exists($thumb)) $output = $thumb;
			}else {//fullsize
				$output = $path;
			}
		}else{
			$output='image: not ready...';
		}
		return $output;
	}
	
	private function makeThumb($args){
		$src_path=$args['src_path'];
		$new_path=$args['new_path'];
		$new_w=$args['neww'];
		$new_h=$args['newh'];
		if(file_exists($new_path)){
		   return false;
		}
        if(!$src_path) return "image: no source file found...";
        $src_path = realpath($src_path);
        $max_mem = 500000;
        $this->scanImage(array('src'=>$src_path));
        if($this->READY){
			if (!$this->FILESIZE || $this->FILESIZE > 10000) {
				 return "image: file too big[{$this->FILESIZE}]";
			}
			$memory = $this->WIDTH * $this->HEIGHT;
			if ($memory > $max_mem) {
				//return "Failed: over memory limit[$memory > $max]";
			}
			$chk=$this->createImage($args);
			if(!$chk){
				return 'image: problem creating image...';
			}else{
				return false;
			}
		}else{
			return 'image: class not ready...';
		}		
	}

	private function getImageSRC($args) {
		//splits the image source info;
		$src=$args['src'];
		$base=issetCheck($args,'base');
		$noimage=issetCheck($args,'default',$this->DEFAULT_IMAGE);
		if (!isset($src)||$src=='') {
			$out['id'] = 0;
			$out['thumb'] = $out['image'] = $noimage;
		} else if(strpos($src,'::')){//from media lib
			$img = explode('::', $src);
			$out['id'] = (int) $img[1];
			if (strpos($img[0], '.thm.')) {
				$out['thumb'] = $base . $img[0];
				$out['image'] = $base . str_replace('.thm', '', $img[0]);
			} else {
				$tmp = $base . $img[0];
				$tmp = explode('.', $tmp);
				$ct = count($tmp) - 1;
				$ext = $tmp[$ct];
				unset($tmp[$ct]);
				$out['thumb'] = implode('.', $tmp) . '.thm.' . $ext;
				$out['image'] = $base . $img[0];
			}
		}else{//plain url
			$out['thumb'] = $out['image'] = $src;
		}
		//check exists
		if (!file_exists($base.$out['image'])) $out['image'] = $noimage;
		if (!file_exists($base.$out['thumb'])) $out['thumb'] = $noimage;
		return $out;
	}
	
	private function getImageCredit($args) {
		global $adm;		
		$id = (int) $args['id'];
		$show_admin =  $args['edit'];
		if (!$id) return false;
		$MDA = new Myp_media;
		$args['mda_id'] = $id;
		$sql = $MDA->Read($args);
		$rec = runQuery($sql, 'row');
		$title = fixHTML($rec->mda_nice_name);
		$meta = compress($rec->mda_meta, false);
		$edit = ($show_admin && $adm) ? ' <a href="mpAdmin.php?c=mediaman&amp;p=' . $id . '" title="Edit Media" class="editME jTip"></a>' : '';
		$info = (issetCheck($meta,'info')) ? '<dd class="info">' . fixHTML($meta['info']) . '</dd>' : false;
		$own = ($info) ? '<a href="#nogo" class="jTip" title="more info">&copy; ' . fixHTML($meta['credit']) . '</a>' : '&copy; ' . fixHTML($meta['credit']);
		$owner = (issetCheck($meta,'credit')) ? '<dd class="owner">' . $own . '</dd>' : false;
		$out = '<dl class="credit"><dt>' . fixHTML($rec->mda_nice_name) . $edit . '</dt>';
		$out.=$owner . $info;
		$out.='</dl>';
		return array('html'=>$out,'title'=>$title);
	}
	
	private function getImageGallery($args) {
		$data=$args['data'];
		$size=$args['size'];
		$class=$args['class'];
		if (!$data||$data==='NULL') return false;
		//ensure clean ids string
		$order= explode(',', $data);
		$tmp=$out=false;
		if(is_array($order)){
			foreach($order as $xx){
				if((int)$xx) $ids[]=$xx;
			}
			$ids=implode(',',$ids);			
			$sql = "SELECT * FROM myp_media WHERE mda_type='image' AND mda_ID IN ($ids)";
			$recs =runQuery($sql, 'obj');
			if (!$recs) return false;
			$tt = ($class == 'mgal') ? '  - drag to sort' : false;
			foreach ($recs as $rec) {
				$img = '<img class="' . $class . '" id="' . $rec->mda_id . '" alt="' . fixHTML($rec->mda_nice_name).'" title="' . fixHTML($rec->mda_nice_name) . $tt . '" src="' . $rec->mda_path . $rec->mda_filename . '" width="' . $size . 'px" />';
				$tmpsrc[$rec->mda_id] = $rec->mda_path . $rec->mda_filename;
				$tmp[$rec->mda_id] = ($class == 'mgal') ? '<div class="slide draggable" title="drag to sort">' . $img . '<a href="' . $rec->mda_id . '" title="remove"></a></div>' : $img;
			}
			if ($tmp) {
				foreach ($order as $o) {
					$out.=$tmp[$o];
					$src[$o] = $tmpsrc[$o];
				}
				return array('img' => $out, 'codes' => $ids, 'src' => $src);
			}
		}
		return false;
	}

	private function getAvatar($args) {
		$code=$args['code'];
		$img=$args['img'];
		$hash=$args['hash'];
		$size=$args['size'];
		if (!$hash) $hash = md5($code);
		$myIcon = 'idc/' . $hash . '.png';
		if (!file_exists($myIcon)) {
			if(function_exists('imageantialias')){
				$IDC = new idIcon;
			}else{
				$IDC = new visiglyph;
			}
			$IDC->identicon($hash, $size, $myIcon);
		}
		$out = ($img) ? '<img alt="avatar" class="idIcon" src="' . $myIcon . '" width="' . $size . '" height="' . $size . '"/>' : $myIcon;
		return $out;
	}

	private function getHashFromAvatar($args=false){
		$path=$args['path'];
		//gets old avatar hash - allows us to just update the avatar without updating the user
		if(!$path || $path==='') return false;
		$h=str_replace('./idc/','',$path);
		$h=str_replace('idc/','',$h);
		$h=str_replace('.png','',$h);
		return $h;
	}

	private function randomImage($args) {
		$qty =$args['qty'];
		$sql = 'SELECT * FROM myp_media JOIN (SELECT FLOOR(MAX(mda_id)*RAND()) AS ID FROM myp_media) AS x ON mda_id >= x.ID LIMIT ' . $qty;
		if ($recs = runQuery($sql, 'obj')) {
			foreach ($recs as $rec) {
				$out[$rec->mda_id]['name'] = $rec->mda_nice_name;
				$out[$rec->mda_id]['path'] = $rec->mda_path . $rec->mda_filename;
				$out[$rec->mda_id]['meta'] = unserialize($rec->mda_meta);
			}
		}
		return $out;
	}

	function getThumbIcon($args){
		$src =$args['src'];
		$types['thumb']=array('jpg','png','bmp','gif');
		$types['formman']=array('pdf','doc','docx','xls','xlsx','txt');
		$types['media']=array('mp3','wma','wav');		
		if($src){
			$finfo=getFileExType($src);
			$type=null;
			foreach($types as $i=>$v){
				if(in_array($finfo['ext'],$v)){
					$type=$i;
					break;
				}
			}
			if($type==='thumb'){
				$thumb=$this->simpleThumb($src,80,80,'height');
			}else{
				$icon=$type;	
			}
		}else{
			$icon= 'unknown';
		}	
		
		if($icon){
			return '<span style="font-size:3.5em" class="admIcon icon '.$icon.'"></span>';
		}else{
			$src=str_replace(FILE_ROOT,'./',$thumb['src']);
			return '<img '.$src.'/>';
		}
		
	}
//  Helpers ======================	
	private function scanImage($args){
		$this->init();
		if(isset($args['src'])&& $args['src']!==''){
			$p=$args['src'];
			if(strpos($args['src'],$this->ROOT)===false){
				$p=$this->ROOT.$p;
			}
			if(file_exists($p) && !is_dir($p)){			
				if($size=getimagesize($p)){
					$this->WIDTH=$size[0];
					$this->HEIGHT=$size[1];
					$this->PATH=$p;
					$info=pathinfo($p);
					$this->FILENAME=$info['filename'];
					$this->EXT=$info['extension'];
					$this->setFilesize();
					$this->READY=true;
				}
			}
		}			
	}
	
    private function setFilesize() {
		$decimals = 2;
		$fstring = true;
        $size = false;
        $ft=false;
        if ($bytes = filesize($this->PATH)) {
            $sz = 'BKMGTP';
            $factor = floor((strlen($bytes) - 1) / 3);
            $size = sprintf("%.{$decimals}f", $bytes / pow(1024, $factor));
        }
        $this->FILESIZE=$size;
        $this->FILESIZE_H=$size.$ft;
    }
	
	private function calculate_newsize($args){
		$maxw=$args['maxw'];
		$maxh=$args['maxh'];
		$w=$this->WIDTH;
		$h=$this->HEIGHT;
        $neww = 0;
        $newh = 0;
        if ($w > $maxw || $h > $maxh) {
            $ip = ($w > $h) ? ($maxw / $w) : ($maxh / $h);
            $neww = round($ip * $w);
            $newh = round($ip * $h);
            if ($newh > $maxh || $neww > $maxw) {
                $np = ($newh > $maxh) ? ($maxh / $newh) : ($maxh / $neww);
                $neww = round($np * $w);
                $newh = round($np * $h);
            }
            if ($newh > $maxh || $neww > $maxw) {
                $np = ($newh > $maxh) ? ($maxh / $newh) : ($maxh / $neww);
                $neww = round($np * $w);
                $newh = round($np * $h);
            }
        } else {
            $neww = $w;
            $newh = $h;
        }
        //final check on height
        if ($newh > $maxh) {
            $newh = $maxh;
            $neww = 0;
        } else if ($neww > $maxw) {
            $neww = $maxw;
            $newh = 0;
        }
        return array('w'=>$neww,'h'=>$newh);
	}
	
    private function createImage($args){
		$new_path=$args['new_path'];
		$new_w=$args['neww']; 
		$new_h=$args['newh'];
		$src_img = $dst_img=$done=false;
		//creates the new image using the appropriate function from gd library
		switch ($this->EXT) {
			case 'jpg':case 'jpeg': case 'JPG':
				$src_img = imagecreatefromjpeg($this->PATH);
				break;
			case 'gif': case 'GIF':
				$src_img = imagecreatefromgif($this->PATH);
				break;
			case 'png': case 'PNG':
				$src_img = imagecreatefrompng($this->PATH);
				imagealphablending($src_img, false);
				imagesavealpha($src_img, true);
				break;
		}
		if ($src_img) {
			//gets the dimmensions of the image
			$old_x = imagesx($src_img);
			$old_y = imagesy($src_img);

			if (( $old_x > $new_w ) || ( $old_y > $new_h )) {
				$ratio1 = $old_x / $new_w;
				$ratio2 = $old_y / $new_h;

				if ($ratio1 > $ratio2) {
					$thumb_w = $new_w;
					$thumb_h = $old_y / $ratio1;
				} else {
					$thumb_h = $new_h;
					$thumb_w = $old_x / $ratio2;
				}
				// we create a new image with the new dimmensions
				$thumb_w=(int)$thumb_w;
				$thumb_h=(int)$thumb_h;
				if (( $old_x < 5000 ) and ( $old_y < 5000 )) {
					$dst_img = imagecreatetruecolor($thumb_w, $thumb_h); // resize the big image to the new created one
					if ($this->EXT === 'png'||$this->EXT === 'PNG') {//preserve alpha transparency
						imagealphablending($dst_img, true);
						$transparent = imagecolorallocatealpha($dst_img, 0, 0, 0, 127);
						imagefill($dst_img, 0, 0, $transparent);
						imagecopyresampled($dst_img, $src_img, 0, 0, 0, 0, $thumb_w, $thumb_h, $old_x, $old_y); // output the created image to the file. Now we will have the thumbnail into the
					} else {
						imagecopyresampled($dst_img, $src_img, 0, 0, 0, 0, $thumb_w, $thumb_h, $old_x, $old_y); // output the created image to the file. Now we will have the thumbnail into the
					}
					// file named by $new_path
					switch ($this->EXT) {
						case 'png': case 'PNG':
							imagealphablending($dst_img, false);
							imagesavealpha($dst_img, true);
							imagepng($dst_img, $new_path,6);
							$done=true;
							break;
						case 'gif': case 'GIF':
							imagegif($dst_img, $new_path);
							$done=true;
							break;
						case 'jpg':case 'jpeg': case 'JPG':
							imagejpeg($dst_img,$new_path,100);
							$done=true;
							break;
					}
				}
			}
		}
		//destroys source and destination images.
		if(is_object($dst_img)) imagedestroy($dst_img);
		if(is_object($src_img)) imagedestroy($src_img);
		return $done;
	}	
}
