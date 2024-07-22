<?php

class idIcon{
	var $SAVEPATH='idc/';
	var $TO_FILE=true;
	/* generate sprite for corners and sides */
	function getsprite($shape,$R,$G,$B,$rotation,$spriteZ) {
		//global $spriteZ;
		$sprite=imagecreatetruecolor($spriteZ,$spriteZ);
		imageantialias($sprite,TRUE);
		$fg=imagecolorallocate($sprite,$R,$G,$B);
		$bg=imagecolorallocate($sprite,255,255,255);
		imagefilledrectangle($sprite,0,0,$spriteZ,$spriteZ,$bg);
		switch($shape) {
			case 0: // triangle
				$shape=array(
					0.5,1,
					1,0,
					1,1
				);
				break;
			case 1: // parallelogram
				$shape=array(
					0.5,0,
					1,0,
					0.5,1,
					0,1
				);
				break;
			case 2: // mouse ears
				$shape=array(
					0.5,0,
					1,0,
					1,1,
					0.5,1,
					1,0.5
				);
				break;
			case 3: // ribbon
				$shape=array(
					0,0.5,
					0.5,0,
					1,0.5,
					0.5,1,
					0.5,0.5
				);
				break;
			case 4: // sails
				$shape=array(
					0,0.5,
					1,0,
					1,1,
					0,1,
					1,0.5
				);
				break;
			case 5: // fins
				$shape=array(
					1,0,
					1,1,
					0.5,1,
					1,0.5,
					0.5,0.5
				);
				break;
			case 6: // beak
				$shape=array(
					0,0,
					1,0,
					1,0.5,
					0,0,
					0.5,1,
					0,1
				);
				break;
			case 7: // chevron
				$shape=array(
					0,0,
					0.5,0,
					1,0.5,
					0.5,1,
					0,1,
					0.5,0.5
				);
				break;
			case 8: // fish
				$shape=array(
					0.5,0,
					0.5,0.5,
					1,0.5,
					1,1,
					0.5,1,
					0.5,0.5,
					0,0.5
				);
				break;
			case 9: // kite
				$shape=array(
					0,0,
					1,0,
					0.5,0.5,
					1,0.5,
					0.5,1,
					0.5,0.5,
					0,1
				);
				break;
			case 10: // trough
				$shape=array(
					0,0.5,
					0.5,1,
					1,0.5,
					0.5,0,
					1,0,
					1,1,
					0,1
				);
				break;
			case 11: // rays
				$shape=array(
					0.5,0,
					1,0,
					1,1,
					0.5,1,
					1,0.75,
					0.5,0.5,
					1,0.25
				);
				break;
			case 12: // double rhombus
				$shape=array(
					0,0.5,
					0.5,0,
					0.5,0.5,
					1,0,
					1,0.5,
					0.5,1,
					0.5,0.5,
					0,1
				);
				break;
			case 13: // crown
				$shape=array(
					0,0,
					1,0,
					1,1,
					0,1,
					1,0.5,
					0.5,0.25,
					0.5,0.75,
					0,0.5,
					0.5,0.25
				);
				break;
			case 14: // radioactive
				$shape=array(
					0,0.5,
					0.5,0.5,
					0.5,0,
					1,0,
					0.5,0.5,
					1,0.5,
					0.5,1,
					0.5,0.5,
					0,1
				);
				break;
			default: // tiles
				$shape=array(
					0,0,
					1,0,
					0.5,0.5,
					0.5,0,
					0,0.5,
					1,0.5,
					0.5,1,
					0.5,0.5,
					0,1
				);
				break;
		}
		/* apply ratios */
		for ($i=0;$i<count($shape);$i++)
			$shape[$i]=$shape[$i]*$spriteZ;
		imagefilledpolygon($sprite,$shape,count($shape)/2,$fg);
		/* rotate the sprite */
		for ($i=0;$i<$rotation;$i++)
			$sprite=imagerotate($sprite,90,$bg);
		return $sprite;
	}

	/* generate sprite for center block */
	function getcenter($shape,$fR,$fG,$fB,$bR,$bG,$bB,$usebg,$spriteZ) {
		//global $spriteZ;
		$sprite=imagecreatetruecolor($spriteZ,$spriteZ);
		imageantialias($sprite,TRUE);
		$fg=imagecolorallocate($sprite,$fR,$fG,$fB);
		/* make sure there's enough contrast before we use background color of side sprite */
		if ($usebg>0 && (abs($fR-$bR)>127 || abs($fG-$bG)>127 || abs($fB-$bB)>127))
			$bg=imagecolorallocate($sprite,$bR,$bG,$bB);
		else
			$bg=imagecolorallocate($sprite,255,255,255);
		imagefilledrectangle($sprite,0,0,$spriteZ,$spriteZ,$bg);
		switch($shape) {
			case 0: // empty
				$shape=array();
				break;
			case 1: // fill
				$shape=array(
					0,0,
					1,0,
					1,1,
					0,1
				);
				break;
			case 2: // diamond
				$shape=array(
					0.5,0,
					1,0.5,
					0.5,1,
					0,0.5
				);
				break;
			case 3: // reverse diamond
				$shape=array(
					0,0,
					1,0,
					1,1,
					0,1,
					0,0.5,
					0.5,1,
					1,0.5,
					0.5,0,
					0,0.5
				);
				break;
			case 4: // cross
				$shape=array(
					0.25,0,
					0.75,0,
					0.5,0.5,
					1,0.25,
					1,0.75,
					0.5,0.5,
					0.75,1,
					0.25,1,
					0.5,0.5,
					0,0.75,
					0,0.25,
					0.5,0.5
				);
				break;
			case 5: // morning star
				$shape=array(
					0,0,
					0.5,0.25,
					1,0,
					0.75,0.5,
					1,1,
					0.5,0.75,
					0,1,
					0.25,0.5
				);
				break;
			case 6: // small square
				$shape=array(
					0.33,0.33,
					0.67,0.33,
					0.67,0.67,
					0.33,0.67
				);
				break;
			case 7: // checkerboard
				$shape=array(
					0,0,
					0.33,0,
					0.33,0.33,
					0.66,0.33,
					0.67,0,
					1,0,
					1,0.33,
					0.67,0.33,
					0.67,0.67,
					1,0.67,
					1,1,
					0.67,1,
					0.67,0.67,
					0.33,0.67,
					0.33,1,
					0,1,
					0,0.67,
					0.33,0.67,
					0.33,0.33,
					0,0.33
				);
				break;
		}
		/* apply ratios */
		for ($i=0;$i<count($shape);$i++)
			$shape[$i]=$shape[$i]*$spriteZ;
		if (count($shape)>0)
			imagefilledpolygon($sprite,$shape,count($shape)/2,$fg);
		return $sprite;
	}

	function identicon($myHash,$mySize,$myIcon){
		if((int)$mySize<80) $mySize=80;
		/* parse hash string */

		$csh=hexdec(substr($myHash,0,1)); // corner sprite shape
		$ssh=hexdec(substr($myHash,1,1)); // side sprite shape
		$xsh=hexdec(substr($myHash,2,1))&7; // center sprite shape

		$cro=hexdec(substr($myHash,3,1))&3; // corner sprite rotation
		$sro=hexdec(substr($myHash,4,1))&3; // side sprite rotation
		$xbg=hexdec(substr($myHash,5,1))%2; // center sprite background

		/* corner sprite foreground color */
		$cfr=hexdec(substr($myHash,6,2));
		$cfg=hexdec(substr($myHash,8,2));
		$cfb=hexdec(substr($myHash,10,2));

		/* side sprite foreground color */
		$sfr=hexdec(substr($myHash,12,2));
		$sfg=hexdec(substr($myHash,14,2));
		$sfb=hexdec(substr($myHash,16,2));

		/* final angle of rotation */
		$angle=hexdec(substr($myHash,18,2));

		/* size of each sprite */
		$spriteZ=128;

		/* start with blank 3x3 identicon */
		$identicon=imagecreatetruecolor($spriteZ*3,$spriteZ*3);
		imageantialias($identicon,TRUE);

		/* assign white as background */
		$bg=imagecolorallocate($identicon,255,255,255);
		imagefilledrectangle($identicon,0,0,$spriteZ,$spriteZ,$bg);

		/* generate corner sprites */
		$corner=$this->getsprite($csh,$cfr,$cfg,$cfb,$cro,$spriteZ);
		imagecopy($identicon,$corner,0,0,0,0,$spriteZ,$spriteZ);
		$corner=imagerotate($corner,90,$bg);
		imagecopy($identicon,$corner,0,$spriteZ*2,0,0,$spriteZ,$spriteZ);
		$corner=imagerotate($corner,90,$bg);
		imagecopy($identicon,$corner,$spriteZ*2,$spriteZ*2,0,0,$spriteZ,$spriteZ);
		$corner=imagerotate($corner,90,$bg);
		imagecopy($identicon,$corner,$spriteZ*2,0,0,0,$spriteZ,$spriteZ);

		/* generate side sprites */
		$side=$this->getsprite($ssh,$sfr,$sfg,$sfb,$sro,$spriteZ);
		imagecopy($identicon,$side,$spriteZ,0,0,0,$spriteZ,$spriteZ);
		$side=imagerotate($side,90,$bg);
		imagecopy($identicon,$side,0,$spriteZ,0,0,$spriteZ,$spriteZ);
		$side=imagerotate($side,90,$bg);
		imagecopy($identicon,$side,$spriteZ,$spriteZ*2,0,0,$spriteZ,$spriteZ);
		$side=imagerotate($side,90,$bg);
		imagecopy($identicon,$side,$spriteZ*2,$spriteZ,0,0,$spriteZ,$spriteZ);

		/* generate center sprite */
		$center=$this->getcenter($xsh,$cfr,$cfg,$cfb,$sfr,$sfg,$sfb,$xbg,$spriteZ);
		imagecopy($identicon,$center,$spriteZ,$spriteZ,0,0,$spriteZ,$spriteZ);

		// $identicon=imagerotate($identicon,$angle,$bg);

		/* make white transparent */
		imagecolortransparent($identicon,$bg);

		/* create blank image according to specified dimensions */
		$resized=imagecreatetruecolor($mySize,$mySize);
		imageantialias($resized,TRUE);

		/* assign white as background */
		$bg=imagecolorallocate($resized,255,255,255);
		imagefilledrectangle($resized,0,0,$mySize,$mySize,$bg);

		/* resize identicon according to specification */
		imagecopyresampled($resized,$identicon,0,0,(imagesx($identicon)-$spriteZ*3)/2,(imagesx($identicon)-$spriteZ*3)/2,$mySize,$mySize,$spriteZ*3,$spriteZ*3);

		/* make white transparent */
		imagecolortransparent($resized,$bg);

		/* and finally, send to standard output or file */
		if($this->TO_FILE){
			ob_start();
			if(strpos($myIcon,$this->SAVEPATH)===false) $myIcon=$this->SAVEPATH.'/'.$myIcon;
			imagepng($resized,$myIcon);
			$contents = ob_get_contents();
			ob_end_clean();
			imagedestroy($resized);
			$fh = fopen($myIcon, "a+" );
			fwrite( $fh, $contents );
			fclose( $fh );
		}else{
			header("Content-Type: image/png");
			imagepng($resized,$myIcon);
			imagedestroy($resized);
		}

	}

}//end class

//test
//$test= new idIcon;
//$test->TO_FILE=false;
//echo $test->idIcon('5058f1af8388633f609cadb75a75dc9d',48,'defaulticon');
