<?php

class slimDataBank{
	private $SLIM;
	private $DB;
	private $CACHED=[];
	private $ARGS;
	private $METH;
	private $USER;
	
	function __construct($slim){
		if(!$slim) throw new Exception(__METHOD__.': no slim object!!');
		$this->SLIM=$slim;
		$this->DB=$slim->db;
		$this->USER=$slim->user;	
	}
	
	function get($name=false,$args=false){
		$this->setArgs($args);
		$meth=$name.'_data';
		if($name && method_exists($this,$meth)){
			$this->METH=$meth;
			$c=$this->getCache();
			if($c){
				return $c;
			}else{
				return call_user_func(array($this,$meth));
			}
		}
		return false;
	}
	function set($name=false,$args=false){
		$this->setArgs($args);
		$meth='save_'.$name.'_data';
		if($name && method_exists($this,$meth)){
			return call_user_func(array($this,$meth));
		}
	}
	private function getCache(){
		return issetCheck($this->CACHED,$this->METH);
	}	
	
	private function setArgs($args=false){
		$this->ARGS=[];//reset
		if(is_array($args)||is_object($args)){
			foreach($args as $i=>$v){
				$k=strtoupper($i);
				$this->ARGS[$k]=$v;
			}
		}else{
			$this->ARGS=$args;
		}
	}
	
	private function getOption($value_only=false){
		//gets a single option from the DB
		//for site options check slimOptions
		$out=false;
		if($this->ARGS['VARNAME']){
			$rec=$this->DB->Options->where('OptionName',$this->ARGS['VARNAME']);
			$rec=renderResultsORM($rec,'id');
			if($rec){
				$out=current($rec);
				if(isset($this->ARGS['type'])||$value_only) $out=$out['OptionValue'];
			}
		}
		return $out;
	}
	function getOption_Long($value_only = false) {
		//gets a single option to the DB
		// use for options with very long values
		$out=false;
		if($this->ARGS['VARNAME']){
			$rec=$this->DB->Items->where('ItemType','option')->and('ItemTitle',$this->ARGS['VARNAME']);
			$rec=renderResultsORM($rec,'ItemID');
			if($rec){
				$out=current($rec);
				if(isset($this->ARGS['type'])||$value_only) $out=$out['ItemContent'];
			}
		}
		return $out;
	}	
	private function getPageData($ref=false,$type='id'){
		$out=false;
		$select='ItemID,ItemType,ItemTitle,ItemContent,ItemShort,itmStatus,ItemDate';
		$DB=$this->DB->Items()->select($select)->order('ItemTitle');
		switch($type){
			case 'all':
				$recs=$DB->where('ItemType','page');
				break;
			case 'active':
				$recs=$DB->where('ItemStatus','published');
				break;
			case 'slug':
				$recs=$DB->where('ItemSlug',$ref);
				break;
			case 'id':
				$recs=$DB->where('ItemID',$ref);
				break;
			default:
				$recs=false;
		}
		if(count($recs)>0){
			$recs=renderResultsORM($recs,'ItemID');
			if(in_array($type,array('all','active','article'))){
				$out=$recs;
			}else{
				$out=current($recs);
			}
		}
		return $out;
		
	}
	private function sidebar_data($ref=false,$type=false){
		$out=false;
		$select='ItemID,ItemType,ItemTitle,ItemContent,ItemShort,itm_Parent,ItemDate';
		$DB=$this->DB->Items()->select($select)->order('ItemTitle');
		$where['ItemType']='sidebar';
		if($this->ARGS){
			preME($this->ARGS,2);			
		}else{
			$where['ItemStatus']='published';
		}
		$recs=$DB->where($where);
		if(count($recs)>0){
			$out=renderResultsORM($recs,'ItemID');
		}
		return $out;		
	}
	
	private function navigation_data(){
		$options=$this->ARGS;
		$this->ARGS['VARNAME']='site_main_nav_3';
		$this->ARGS['DEFAULT']=false;
        $navData[0]['url'] = 'home';
        $navData[0]['label'] = 'Home Page';
        $navData[0]['line_class'] = ($options['ITEM_SLUG']) ? 'active' : false;
        $navData[0]['link_class'] = false;
        $url=array('page' => 'home', 'base' => $options['MAINPAGE'], 'lead' => 1);
        $ndata = $this->getOption_long(true);
        $navData = compress($ndata, false);
        $this->CACHED['navigation_data']=$navData;
        return $navData;
	}

    function item_slug_data(){
		$out=false;
		$slug=issetCheck($this->ARGS,'ITEM_SLUG');
        if($slug){
			$data=$this->getPageData($slug,'slug');
			if($data){
                $out['ITEM_DATA'] = $data;
                $out['ITEM_TYPE'] = $data['ItemType'];
                $out['ITEM_ID'] = $data['ItemID'];
                $out['ITEM_SLUG'] = $data['ItemSlug'];
            }
        }else{
			if(!in_array($slug,$this->ARGS['FAUX_PAGES'])){
				if ($slug !== 'home'){
					$out['MSG'] = 'Sorry, I could not find that item [' . $slug . ']<br/>';
					$out['ITEM_SLUG'] = 'home';
				}
			}
		}
		return $out;
    }
    function item_type_data(){
		$out=false;
		$type=issetCheck($this->ARGS,'ITEMTYPE');
		$group=(isset($this->ARGS['ITEMGROUP']))?$this->ARGS['ITEMGROUP']:'none';//allow for zero
		$category=issetCheck($this->ARGS,'ITEMCATEGORY');
		$status=issetCheck($this->ARGS,'ITEMSTATUS');
		$sel=issetCheck($this->ARGS,'SELECT');
		$order=issetCheck($this->ARGS,'ORDER','ItemTitle ASC');
		$limit=issetCheck($this->ARGS,'LIMIT');
		if($type){
			$db=$this->SLIM->db->Items();
			$rec=$db->where('ItemType',$type);
			if($status)$rec->where('ItemStatus',$status);
			if($group!=='none')$rec->where('ItemGroup',$group);
			if($category)$rec->where('ItemCategory',$category);
			if($sel)$rec->select($sel);
			if($order)$rec->order($order);
			if($limit)$rec->limit($limit);
			$out=renderResultsORM($rec,'ItemID');
			$out=$this->setMetaData_items($out);
		}
		return $out;				
	}
    function item_id_data(){
		$out=false;
		$id=issetCheck($this->ARGS,'ITEMID');
		$sel=issetCheck($this->ARGS,'SELECT');
		if($id){
			$db=$this->SLIM->db->Items();
			$rec=$db->where('ItemID',$id)->order('ItemTitle ASC');
			if($sel)$rec->select($sel);
			$out=renderResultsORM($rec,'ItemID');
			$out=$this->setMetaData_items($out);
		}
		return $out;				
	}
	private function setMetaData_items($rec){
		$t=issetCheck($rec,'ItemShort');
		$c=json_decode($t,1);
		if($c)$rec['meta']=$c;
		return $rec;	
	}
	function item_new_data(){
		$type=issetCheck($this->ARGS,'ITEMTYPE');
		$date=date('Y-m-d H:i:s');
		$default['new']=array(
			'ItemID'=>0,
			'ItemType'=>$type,
			'ItemTitle'=>'',
			'ItemSlug'=>'',
			'ItemPrice'=>'',
			'ItemCurrency'=>1,
			'ItemCategory'=>0,
			'ItemGroup'=>0,
			'ItemContent'=>'',
			'ItemShort'=>'',
			'ItemStatus'=>'draft',
			'ItemDate'=>$date
		);
		return $default;
	}
	function content_types_data(){
		$def = array('page', 'product', 'sidebar');
		$key=issetCheck($this->ARGS,'KEY');
		if(!$key||$key==='') $key=$def;
		$raw=issetCheck($this->ARGS,'RAW',true);
		$rev=issetCheck($this->ARGS,'REV');
		$db=$this->DB->Options();
		$out = $var = false;
		if (is_array($key)) {
			$tmp = $key;
			$var = 0;
		} else {
			$tmp[] = $key;
			$var = 1;
		}
		foreach ($tmp as $t) {
			$v=false;
			$rec = $db->select('id')->where(array('OptionGroup'=>'ItemType','OptionValue'=>$t))->limit(1);
			if(count($rec)>0) $v=$rec[0]['id'];
			if ($v) {
				if (!$raw) {
					if ($t == 'post') {
						$t = 'blog posts';
					} else if ($t == 'extlink') {
						$t = 'external links';
					} else {
						$t.='s';
					}
				}
				if ($var) {
					$out = $v;
				} else if ($rev) {
					$out[$t] = $v;
				} else {
					$out[$v] = $t;
				}
			}
		}
		return $out;
	}	

	function search_items_data() {
		//admin items search
		$out=false;
		switch($this->ARGS['ITEMTYPE']){
			case 'book':
				// do something
				break;
			default:
				$out=false;
		}
		return $out;
	}
	
	function table_info(){
		$tbl=issetCheck($this->ARGS,'table');
		if($tbl){
			$info=$this->SLIM->pdo->getFields($tbl);
			preME($info,2);
		}
	}
	
}
