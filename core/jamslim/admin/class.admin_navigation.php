<?php

class admin_navigation{
	private $SLIM;

	private $PAGES;
	private $ARTS;
	private $NAV;
	private $NAVID;
	private $AUTOMENU=false;
	private $OPTION_NAME='main_menu';
	private $SITE_ID=false;
	private $PERMLINK;
	private $liveNav;
	private $CONTROLS;

	public $SECTION=false;
	public $ACTION=false;
	public $ID=false;
	public $MODEL=false;
	public $ADMIN=false;  
  
	public $PLUG;
	public $AJAX;
	public $REQUEST;
	public $USER;
	public $METHOD;
	public $LEADER;
	public $ROUTE;	

	function __construct($slim=null){
		if(!$slim) throw new Exception(__METHOD__.': no slim object!!');
		$this->SLIM=$slim;
		$this->USER=$slim->user;
		$this->ADMIN = ($this->USER['access']>=4)?true:false;
		$this->AJAX = $slim->router->get('ajax');
		$this->ROUTE = $slim->router->get('ajax');
		$this->METHOD = $slim->router->get('method');
		$this->REQUEST=($this->METHOD==='POST')?$slim->router->get('post'):$slim->router->get('get');
		$this->SITE_ID = SLIM_SITE_ID;
		$this->PERMLINK=URL.'admin/navigation';
		$this->liveNav=false;
	}
  
	function Process(){
		if($this->METHOD==='POST'){
			$this->ProcessPost();
		}else{
			return $this->navManager3();
		}
	}
    
    function ProcessPost(){
	    if($this->REQUEST){
			$response['message']=$this->updateMenu3($this->REQUEST);
		}else{
			$response['message']=array('message'=>'Sorry, nothing to update...','message_type'=>'alert');
		}
		if($this->AJAX){
			$json=array('action'=>'alert','message'=>$response['message']);
			echo json_encode($json);
		}else{
			setSystemResponse($this->PERMLINK,$response['message']['message']);
		}
		die;

	}
	private function setNavData(){
		//set available pages
		$recs=$this->SLIM->db->Items->select('ItemID,ItemSlug,ItemTitle,ItemStatus')->where('ItemType','page')->and('ItemStatus','published');
		$this->PAGES=renderResultsORM($recs,'ItemID');
		$this->AUTOMENU=getRequestVars('automenu');//get from site options
		$curNav=$this->loadMenu();
		$this->CONTROLS='<span class="controls"><a href="#nogo" title="click to remove this item" class="removeME text-red">remove</a> <a href="#nogo" title="toggle settings" class="controlSwitch">settings</a></span>';
		$this->NAV= $curNav;
	}
	function navManager3($args=false){
		$this->setNavData();
		$opts=$this->renderMenus();	
		
		$tpl=file_get_contents(TEMPLATES.'parts/tpl.sitenav3.html');
		$tpl=str_replace('{id}',(string)$this->NAVID,$tpl);
		$tpl=str_replace('{form_action}',$this->PERMLINK,$tpl);
		$tpl=str_replace('{opt_main}',$opts['menu'],$tpl);
		$tpl=str_replace('{opt_pages}',$opts['pages'],$tpl);
		$buts[]=array('label'=>'AutoMenu', 'href'=>$this->PERMLINK.'&amp;automenu=1','class'=>'secondary','title'=>'rebuild the menus based on page & articles.');

		$out['desc']='<p>This page allows you to organise the main site navigation based on the pages you\'ve created.<br>Drag items by grabbing the green handle to the right of each item.</p><ol><li>Move items from the <strong>Available Items</strong> list and drop them into the <strong>Menu List</strong></li><li>In the <strong>Menu List</strong>, fill in each items <strong>label</strong> (which is the text that appears in the site navigation.)</li><li>Drag items (in the <strong>Menu list</strong>) into the desired order, then click <strong>Save Changes</strong>.</li><ol>';
		$out['content']=$tpl;
		$out['icon']='<i class="fi-compass icon-x1b"></i>';
		$out['title']='Navigation Manager';
		$out['item_title']='';
		$out['message']=false;
		$out['menu']['right']='<button type="button" data-open="nav-help" class="button button-aqua"><i class="fi-info"></i> Help</button>';
		$out['menu']['right'].='<button id="btnAddExt" type="button" class="button button-purple" title="add an external link to the menu"><i class="fi-link"></i> External Link</button>';
		$out['menu']['right'].='<button id="btnOut" type="button" class="button button-olive"><i class="fi-check"></i> Save</button>';
		
		$this->SLIM->assets->add('script','<script src="assets/js/admin/jquery.sortableLists.js"></script><script src="assets/js/admin/jamslim-menuEditor.js"></script>','navman');
		$this->SLIM->assets->add('styles','<link rel="stylesheet" href="assets/css/navman.css" type="text/css" />','navman');
		$this->SLIM->assets->set('js','initNavman();','navman');
		return $out;	  
	}
	
	function decodeJSON($str=false){
		$out=null;
		if($str){
			$out=json_decode($str,true);
			if(!$out){
				$str=html_entity_decode($str);
				$out=json_decode($str,true);
			}
		}
		return $out;
	}

	function updateMenu3($post){
		$state='alert';
		if($post['action']==='savemenu' && $post['data']){
			$data=$this->decodeJSON($post['data']);
			$ct=1;
			$new=array();
			foreach($data as $i=>$v){
				$sb=1;
				$v=$this->checkID($v);
				$id=$v['ref'];
				$new[$id]=$this->setMenuRecord($v,$ct);
				$children=issetCheck($v,'children');
				if($children){
					foreach($children as $x=>$y){
						$ssb=1;
						$y=$this->checkID($y);
						$sid=$y['ref'];
						$new[$id]['subs'][$sid]=$this->setMenuRecord($y,$sb,$id);
						$children2=issetCheck($y,'children');
						if($children2){
							foreach($children2 as $xx=>$yy){
								$yy=$this->checkID($yy);
								$ssid=$yy['ref'];
								$new[$id]['subs'][$sid]['subs'][$ssid]=$this->setMenuRecord($yy,$ssb,$sid);
								$ssb++;
							}
						}
						$sb++;
					}
				}
				$ct++;	
			}
			if(!empty($new)){
				$chk=$this->saveMenu(compress($new),true);
				$state=($chk)?'success':'primary';
			}else{
				$chk['DB_STATUS']='no navigation data!!';
			}
			$out['message']=($chk)? 'The menu has been updated.':'Okay, but it does not seem like you have made any changes';
		}else{
		    $out['message']='Sorry, I don\'t know what to do...';	
		}
		$out['message_type']=$state;
		return $out;
    }
    function saveMenu($value=false,$add=false){
		$chk=false;
		if($value){
			$db=$this->SLIM->db->Options;
			$upd=['OptionValue'=>$value];
			$rec=$db->where('OptionName',$this->OPTION_NAME)->limit(1);
			if(count($rec)>0){
				$chk=$rec->update($upd);
			}else if($add){
				$upd['OptionGroup']='navigation';
				$upd['OptionDescription']='public main menu';
				$upd['OptionName']=$this->OPTION_NAME;
				$chk=$db->insert($upd);
			}
		}
		return $chk;			
	}
	function loadMenu(){
		$menu=$this->SLIM->Options->get('main_menu');
		return $menu;
	}
	
    function checkID($rec=false){
		//from post
		$ref=issetCheck($rec,'ref');
		if($ref && is_numeric($ref)){
			$rec['ref']=(int)$ref;
		}else if($ref && is_string($ref) && $ref!==''){
			$rec['ref']=$ref;
		}else if($rec['text']!=''){
			$rec['ref']='ext-'.slugME(array('str'=>$rec['text']));
		}else{
			$rec['ref']=false;
		}
		return $rec;
	}
	function setMenuRecord($data=false,$pos=0,$parent=0){
		if(!is_array($data)) return false;
		$rec=array(
			'id'=>$data['ref'],
			'label'=>$data['text'],
			'slug'=>$data['href'],
			'position'=>$pos,
			'target'=>issetCheck($data,'target'),
			'tooltip'=>issetCheck($data,'tooltip'),
			'subs'=>false,
			'parent'=>$parent
		);
		return $rec;		
	}

	private function renderMenus(){
		$compare=array();
		$menu=$available='';
		$page_posx=$sub_posx=$page_pos=1;
		//used pages
		foreach($this->NAV as $i=>$main){
			if(!isset($main['position'])) continue;
			$page_pos=($main['position'])?$main['position']:$page_posx;
			$compare[$main['id']]=$main['slug'];
			$subs=false;
			if($main['subs']){
				$sub_posx=1;
				foreach($main['subs'] as $x=>$sub){
					$subs2=false;
					$sub_pos=($sub['position'])?$sub['position']:$sub_posx;
					$sub_subs=issetCheck($sub,'subs');
					if($sub_subs){
						$_sub_posx=1;
						foreach($sub_subs as $ss=>$sub_s){
							$subs2.=$this->menuRow($sub_s,$_sub_posx,false,$page_pos);
						}
						$subs2='<ul>'.$subs2.'</ul>';
					}
					$compare[$sub['id']]=$sub['slug'];
					$subs.=$this->menuRow($sub,$sub_pos,$subs2,$page_pos);
					$sub_posx++;
				}
				if($subs) $subs='<ul>'.$subs.'</ul>';
			}
			$menu.=$this->menuRow($main,$page_pos,$subs);
			$page_posx++;
		}
		//available pages
		$av=[];
		foreach($this->PAGES as $r){
			$chk=isset($compare[$r['ItemID']]);
			if(!$chk && $r['ItemStatus']==='published'){
				$title=fixHTML($r['ItemTitle']);
				$av[$title]='<li class="menu-item" data-ref="'.$r['ItemID'].'" data-text="'.$title.'" data-href="'.$r['ItemSlug'].'" data-pos="'.$page_pos.'" >'.$this->menuItem($title).'</li>';

			}
		}
		if($av){
			ksort($av);
			$available=implode("\n",$av);
		}
		return array('pages'=>$available,'menu'=>$menu);	   
    }
    
	private function menuRow($data=false,$pos=0,$subs=false,$parent=0){
		if(!is_array($data)) return false;
		$row='<li class="menu-item" data-text="'.$data['label'].'" data-ref="'.$data['id'].'" data-parent="'.$parent.'" data-href="'.$data['slug'].'" data-pos="'.$pos.'" >';
		$row.=$this->menuItem($data['label']);
		$row.=$subs;
		$row.='</li>';	
		return $row;
	}
	private function menuItem($title){
		$tpl='<div>
				<span class="grabber bg-orange">&nbsp;</span>
				<span class="txt">'.$title.'</span>
                <div class="button-group tiny float-right"> 
					<a href="#" class="button btnEdit">Edit</a> 
					<a href="#" class="button bg-red btnRemove">X</a>
					<a href="#" class="button bg-olive btnAdd">+</a>  
				</div>
              </div>';
        return  $tpl;		
	}
}
