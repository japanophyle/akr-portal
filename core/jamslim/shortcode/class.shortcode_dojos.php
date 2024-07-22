<?php

class shortcode_dojos extends slimShortCoder{
	
	function __construct($slim=null){
		if(!$slim) throw new Exception(__METHOD__.': no slim object!!');
		parent::__construct($slim);
		$this->USER=$slim->user;
	}
	
	//required function
	function getReplace($args=false){
		$content['cnt']=$this->getDojos($args['args']);
		$content['script']=$content['jqd']=$content['js']='';
		$content['rp'] = $content['cnt'];
		$content['find'] = '<p>' . $this->FIND . '</p>';
		return $content;
	}
	function render($ref=false){
		return $this->getDojos($ref);
	}
	
	function getDojos($ref=false){
		$DB=$this->SLIM->db->ClubInfo;
		$out='';
		$w=['Status'=>1];
		if($ref)$w['ShortName']=$ref;
		$recs=$DB->where($w);
		$rez=renderResultsORM($recs,'ClubID');
		if($rez){
			if($ref){
				$out=$this->renderViewDojo(current($rez));
			}else{
				foreach($rez as $i=>$v){
					$out.='<div class="cell"><div class="callout loadME dojo-link" data-ref="'.URL.'page/dojo/'.$v['ShortName'].'"><div class="h5"><i class="fi-target"></i> '.$v['ClubName'].'</div><span class="label button-ahk-red">'.$v['ShortName'].' / '.$v['Country'].'</span></div></div>';
				}
				$out='<div class="block"><div class="grid-x grid-margin-x small-2 medium-up-3">'.$out.'</div></div>';
			}
		}else{
			$out=msgHandler($this->SLIM->language->getStandardPhrase('no_details_found'),false,false);
			if($ref) $out=renderCard_active('Dojo #'.$ref,$out,$this->SLIM->closer);
		}
		return $out;
	}
	
	function renderViewDojo($recs){
		$lib=new slim_db_members($this->SLIM);
		if($id=issetCheck($recs,'LeaderID')){
			$member=$lib->get('details',$id);
			$leader=$member['Normalname'];
		}else{
			$leader='- no leader -';
		}
		$web=($recs['Website']!=='')?'<a href="'.$recs['Website'].'" target="_blank">'.$recs['Website'].'</a>':'';
		$email=($recs['Email']!=='')?'<a href="mailto:'.$recs['Email'].'" target="_blank" rel="noopener noreferrer">'.$recs['Email'].'</a>':'';
		$map=$this->renderMap($recs['Geo']);
		$out='<div class="callout"><div class="h4">'.$recs['ClubName'].'</div><ul>
		<li><strong>Leader:</strong> '.$recs['Leader'].'</li>
		<li><strong>Website:</strong> '.$web.'</li>
		<li><strong>Email:</strong> '.$email.'</li>
		<li><strong>Address:</strong> '.$recs['Address'].'</li>
		</ul>'.$map.'</div>';
		$close=($this->AJAX)?$this->SLIM->closer:'';
		$out=renderCard_active('Dojo #'.$recs['ShortName'],$out,$close);
		return $out;
	}
	
	function renderMap($geo=''){
		if(!$geo || trim($geo)==='') return false;
		$geoc=explode(',',$geo);
		$link='https://facilmap.org/Mk33LCYtH6tE?search=false&toolbox=false#16/'.trim($geoc[0]).'/'.trim($geoc[1]).'/Mpnk/'.trim($geoc[0]).'%2C%2'.trim($geoc[1]);
		return '<div><iframe style="height:20rem; width:100%; border:none;" src="'.$link.'"></iframe></div>';
	}

}
