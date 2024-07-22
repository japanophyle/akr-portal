<?php

class fixDojoLocations{
	private $SLIM;
	private $LIB;
	private $DOJOS;
	private $DATA;
	private $PERMLINK;
	private $PERMBACK;
	private $PERMBASE;
	private $REF=0;

	private $AJAX;
	private $REQUEST;
	private $USER;
	private $METHOD;
	private $SECTION;
	private $ACTION;
	private $ROUTE;
	private $OUTPUT=['title'=>'Fix Dojo Records','content'=>''];
	private $SECTS=['members','appform','products','users','pages'];
	private $DEBUG=true;
	
	function __construct($slim=null){
		if(!$slim) throw new Exception(__METHOD__.': no slim object!!');
		$this->SLIM=$slim;
		$this->METHOD=$slim->router->get('method');
		if(!$this->METHOD) $this->METHOD='GET';
		$this->REQUEST=($this->METHOD==='POST')?$slim->router->get('post'):$slim->router->get('get');
		$this->ROUTE=$slim->router->get('route');
		$this->SECTION=issetCheck($this->ROUTE,4);
		$this->ACTION=issetCheck($this->ROUTE,3);
		if($this->METHOD==='POST') $this->ACTION=issetCheck($this->REQUEST,'action',$this->ACTION);
		$this->AJAX=$slim->router->get('ajax');
		$this->USER=$slim->user;
		$this->PERMBASE=URL.'admin/';
		$this->PERMBACK=$this->PERMBASE.'dojo/';
		$this->PERMLINK=$this->PERMBACK.'fix/';		
		if($this->USER['access'] < $slim->SuperLevel){
			setSystemResponse($this->PERMBACK,'Sorry, you can\'t access that module...');
		}
		$this->init();
	}
	
	private function init(){
		$recs=$this->SLIM->db->ClubInfo();
		$this->DOJOS=renderResultsORM($recs,'ClubID');
		switch($this->ACTION){
			case 'list':
			case 'update_dojo_section':
				$this->REF=issetCheck($this->ROUTE,5,0);
				$this->DATA=$this->getData($this->SECTION,$this->REF);
				break;
			case 'update_dojo':
				$this->REF=issetCheck($this->ROUTE,4,0);
				break;
		}
	}
	private function getData($section='',$ref=0,$obj=false){
		$data=[];
		if($section===''||$ref==0) return $data;
		$db=$this->SLIM->db;
		switch($section){
			case 'members':
				$recs=$db->Members->select('MemberID,FirstName,LastName,Dojo,DojoID')->where('DojoID',$ref);
				break;
			case 'appform':
				$recs=$db->SignupLog->select('ID,Name,Email,Status,DojoID,FormData')->where('DojoID',$ref);
				break;
			case 'products':
				$recs=$db->Items->select('ItemID,ItemTitle,ItemSlug,ItemStatus,ItemContent')->where('ItemContent',$ref)->where('ItemType','product');
				break;
			case 'users':
				$recs=$db->Users->select('id,Name,Email,Status,DojoLock')->where('DojoLock LIKE ? ','%'.$ref.'%');
				break;
			case 'pages':
				$recs=$db->Items->select('ItemID,ItemTitle,ItemSlug,ItemStatus,ItemPrice')->where('ItemPrice',$ref)->where('ItemType','page');
				break;
			default:
				$recs=[];
		}
		if($obj) return $recs;
		$data=($recs)?renderResultsORM($recs):[];
		return $data;
	}
	private function setData($section='',$old=0,$new=0){
		$db=$this->SLIM->db;
		$chk=$ct=0;
		switch($section){
			case 'products':
				$recs=$this->getData($section,$old,true);
				if($ct=count($recs)){
					$up=['ItemContent'=>$new];
					$chk=($this->DEBUG)?$ct:$recs->update($up);					
				}
				break;
			case 'pages':
				$recs=$this->getData($section,$old,true);
				if($ct=count($recs)){
					$up=['ItemPrice'=>$new];
					$chk=($this->DEBUG)?$ct:$recs->update($up);					
				}
				break;
			case 'members':
				$chk=$this->setData_members($old,$new);
				break;
			case 'appform':
				$chk=$this->setData_forms($old,$new);
				break;
			case 'users':
				$chk=$this->setData_users($old,$new);
				break;
		}
		return $chk;
	}
	private function setData_users($old=0,$new=0){
		if(!$old || !$new) return 0;
		$data=$this->getData('users',$old);
		if(!$data) return 0;
		$db=$this->SLIM->db;
		$ct=0;
		foreach($data as $i=>$v){
			$dl=trim($v['DojoLock']);
			if($dl!=='' && strpos($dl,'a:')===false){
				if(is_numeric($dl)){
					$dl=serialize([$dl]);
				}else{
					preME($v,2);
				}
			}
			$lock=($dl!=='')?unserialize($dl):[];
			foreach(array_keys($lock) as $l){
				if($lock[$l]==$old){
					$lock[$l]=$new;
					$rec=$db->Users->where('id',$i);
					$up=['DojoLock'=>serialize($lock)];
					$chk=($this->DEBUG)?1:$rec->update($up);
					if($chk) $ct++;
					break;
				}
			}
		}
		return $ct;					
	}
	private function setData_members($old=0,$new=0){
		if(!$old || !$new) return 0;
		$data=$this->getData('members',$old);
		if(!$data) return 0;
		$db=$this->SLIM->db;
		$ct=$ctx=0; 
		foreach($data as $i=>$v){
			$forms=$db->FormsLog->where('MemberID',$v['MemberID'])->select('ID,MemberID,FormData');
			$forms=renderResultsORM($forms);
			if($forms){
				//upfdate formslog
				foreach($forms as $frm){
					$form=compress($frm['FormData'],false);
					if($form['DojoID']==$old){
						$form['DojoID']=$new;
						$form=compress($form);
						$rec=$db->FormsLog->where('ID',$frm['ID']);
						if(count($rec)==1){
							$up=['FormData'=>$form];
							$chkx=($this->DEBUG)?1:$rec->update($up);
							if($chkx) $ctx++;
						}
					}
				}
			}
			//update member			
			$rec=$db->Members->where('MemberID',$v['MemberID']);
			if(count($rec)==1){
				$up_m=['DojoID'=>$new];
				$chk=($this->DEBUG)?1:$rec->update($up_m);
				if($chk) $ct++;
			}
		}
		return [$ct,$ctx];					
	}
	private function setData_forms($old=0,$new=0){
		$ct=0;
		if(!$old || !$new) return $ct;
		$recs=$this->getData('appform',$old);
		if(!$recs) return $ct;
		$db=$this->SLIM->db;
		foreach($recs as $data){
			$form=compress($data['FormData'],false);
			$form['DojoID']=$new;
			$form=compress($form);
			$rec=$db->SignupLog->where('ID',$data['ID']);
			if(count($rec)==1){
				$up=['DojoID'=>$new,'FormData'=>$form];
				$chk=($this->DEBUG)?1:$rec->update($up);
				if($chk) $ct++;
			}
		}
		return $ct;		
	}
	private function getDojoRec($loc=0){
		if(!$loc) return [];
		foreach($this->DOJOS as $i=>$v){
			if($v['LocationID']==$loc) return $v;
		}
		return [];
	}
	function Process(){
		switch($this->ACTION){
			case 'list':
				$this->renderItems();
				break;
			case 'update_dojo':
				$this->renderFixDojo();
				break;
			case 'update_dojo_section':
				$this->renderFixDojoSection();
				break;
			case 'update_all':
				$this->renderFixAll();
				break;
			default:
				$this->renderDashboard();
		}
		if($this->AJAX){
			echo renderCard_active($this->OUTPUT['title'],$this->OUTPUT['content'],$this->SLIM->closer);
			die;
		}
		$this->OUTPUT['menu']=['right'=>'<li><button class="button small button-maroon gotoME" title="fix all dojo locations" data-ref="'.$this->PERMLINK.'update_all" type="button"><i class="fi-check"></i> Fix All</button></li>'];
		if($this->DEBUG) $this->OUTPUT['title'].=' <span class="text-red">* Debug Mode *</span>';
		return $this->OUTPUT;		
	}
	
	private function renderDashboard(){
		$tbl=[];
		$count=0;
		foreach($this->DOJOS as $i=>$dat){
			$loc_id=$dat['LocationID'];
			$controls='<button class="button button-dark-blue small loadME" data-ref="'.$this->PERMLINK.'list/members/'.$loc_id.'">Members</button>';
			$controls.='<button class="button button-blue small loadME" data-ref="'.$this->PERMLINK.'list/appform/'.$loc_id.'">Forms</button>';
			$controls.='<button class="button button-navy small loadME" data-ref="'.$this->PERMLINK.'list/products/'.$loc_id.'">Products</button>';
			$controls.='<button class="button button-purple small loadME" data-ref="'.$this->PERMLINK.'list/users/'.$loc_id.'">Users</button>';
			$controls.='<button class="button button-dark-green small loadME" data-ref="'.$this->PERMLINK.'list/pages/'.$loc_id.'">Pages</button>';
			$controls.='<button class="button button-maroon small loadME" data-ref="'.$this->PERMLINK.'update_dojo/'.$loc_id.'"><i class="fi-widget"></i> Fix</button>';
			$state=($dat['Status']==1)?'<span class="text-dark-green">Active</span>':'<span class="text-gray">Disabled</span>';
			$tbl[$i]=array(
				'ID'=>$i,
				'Name'=>$dat['ClubName'],
				'Code'=>$dat['ShortName'],
				'Old ID'=>$loc_id,
				'Status'=>$state,
				'Controls'=>'<div class="button-group small expanded">'.$controls.'</div>'
			);
			$count++;
		}
		$this->OUTPUT['title'].=' ['.$count.']';
		$this->OUTPUT['content']=$this->renderListItems($tbl);
	}

	private function renderItems(){
		$lbl=ucME($this->SECTION);
		$button=(count($this->DATA))?'<button class="button small expanded button-maroon loadME" data-ref="'.$this->PERMLINK.'update_dojo_section/'.$this->SECTION.'/'.$this->REF.'"><i class="fi-widget"></i> Fix '.$lbl.'</button>':'';
		$this->OUTPUT['title'].=': <em>'.$lbl.' #'.$this->REF.'</em> ['.count($this->DATA).']';
		$this->OUTPUT['content']=$this->renderListItems($this->DATA).$button;
	}
	private function renderListItems($tbl=[]){
		$count=0;
		if($tbl){
			$args['data']['data']=$tbl;
			$args['before']='filter';
			$list=dataTable($args);
		}else{
			$list=msgHandler('No records found...',false,false);
		}
		return $list;
	}
	private function renderFixAll(){
		$results=[];
		foreach($this->DOJOS as $i=>$v){			
			$this->REF=$v['LocationID'];
			if(!(int)$this->REF) continue;
			$rez=$this->renderFixDojo();
			$results[$i]=['ID'=>$i,'Name'=>$v['ClubName'],'Code'=>$v['ShortName']];
			foreach($rez as $s){
				$results[$i][$s['Item']]=$s['Count'];
			}
		}
		$this->OUTPUT['title'].=': <em>Fix All Results</em>';
		$this->OUTPUT['content']=$this->renderListItems($results);
	}
	private function renderFixDojoSection($section='',$ref=0,$new=0){
		if($section==='') $section=$this->SECTION;
		if(!$ref) $ref=$this->REF;
		if(!$new){
			$dojo=$this->getDojoRec($ref);
			$new=$dojo['ClubID'];
		}
		if(!$ref || !$new || $section==='') preME('section arguments not set',2);
		$data=($this->DATA)?$this->DATA:$this->getData($section,$ref);
		$done[$section]=[
			'ref'=>$new,
			'count'=>0
		];
		if($data){
			$cnt=$this->setData($section,$ref,$new);
			if(is_array($cnt)){
				$done[$section]['count']=$cnt[0];
				if($section==='members') $done['eventforms']=['ref'=>$new,'count'=>$cnt[1]];
			}else{
				$done[$section]['count']=$cnt;
			}
		}else if($section==='members'){
			$done['eventforms']=['ref'=>$new,'count'=>0];
		}
		if(in_array($this->ACTION,['update_all','update_dojo'])){
			return $done;
		}
		$tbl=[];
		foreach($done as $i=>$v){
			$tbl[]=['Item'=>$i,'Count'=>$v['count'],'Result'=>'Updated from '.$ref.' to '.$new];
		}
		$this->OUTPUT['title'].=': <em>#'.$new.' - '.$section.' - fix</em>';
		$this->OUTPUT['content']=$this->renderListItems($tbl);
	}
	private function renderFixDojo(){
		$done=$tbl=[];
		$dojo=$this->getDojoRec($this->REF);
		foreach($this->SECTS as $section){
			$r=$this->renderFixDojoSection($section,$this->REF,$dojo['ClubID']);
			$done+=$r;
		}
		foreach($done as $i=>$v){
			$tbl[]=['Item'=>$i,'Count'=>$v['count'],'Result'=>'Updated from '.$this->REF.' to '.$dojo['ClubID']];
		}
		if($this->ACTION==='update_all'){
			return $tbl;
		}
		$this->OUTPUT['title'].=': <em>'.$dojo['ClubName'].'</em>';
		$this->OUTPUT['content']=$this->renderListItems($tbl);
	}
	
}
