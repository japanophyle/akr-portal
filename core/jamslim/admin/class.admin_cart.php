<?php
class admin_cart{
	private $SLIM;
	public $ROUTE;
	public $METHOD;
	public $REQUEST;
	public $SECTION;
	public $ACTION;
	public $USER;
	public $PLUG,$ICON,$TABLE_COUNT,$PERMLINK,$AJAX;
	
	private $PDO;
	private $DB;
	public $ADMIN;
	public $LEADER;
	private $ADMIN_PAGE;
	private $PERMBACK;
	private $APP_ID='cart';
	private $TABLE_NAME='cart_log';
	private $TABLE_STRUCTURE;
	private $PRIMARY;
	private $TOTAL_RECORDS;
	private $RETURN_COLUMNS;
	private $SEARCH_COLUMNS;
	private $DATA;
	private $STATES;
	private $SETTINGS;
	private $SETUP;
	private $SALES_REF;
	private $IS_SUBSCRIPTION;
	public $OUTPUT;
	public $DEBUG=false;
	private $READY=false;
	private $ID;//from route
	private $REF;//from route
	private $LIB;
	
	function __construct($slim){		
		if(!$slim) throw new Exception(__METHOD__.': no slim object!!');
		$this->SLIM=$slim;
		$this->AJAX=$slim->router->get('ajax');
		$this->PERMBACK=$slim->router->get('permlinks','link'); 
		$this->PERMLINK=$this->PERMBACK.$this->APP_ID.'/';
		$this->LIB= new slim_db_cart_log($slim);
		$this->RETURN_COLUMNS=array(
			'default'=>'TID,item_name,payer_name,txn_id,cart_ref,create_date,status',
			'check'=>'TID,usr_ID,status'
		);
		$this->SEARCH_COLUMNS=array(
			'text'=>array('item_name','payer_name','payer_email','txn_id','cart_ref'),
			'date'=>array('create_date','payment_date'),
		);
        $this->STATES=$this->LIB->get('states');
        $this->ICON='product text-purple';
		$this->PLUG=issetCheck($this->SLIM->AdminPlugins,'cart');
	}
	
	function init(){
		if(!$this->READY){
			$this->ADMIN = ($this->USER['access']>=$this->SLIM->AdminLevel)?true:false;
			$this->DEBUG=issetCheck($this->SETTINGS,'debug');
			$title='Transaction Manager';
			if($this->DEBUG) $title.=' <strong class="text-maroon">[DEBUG MODE]</strong>';
			$this->OUTPUT=array(
				'just_title'=>$title,
				'title'=>$title,
				'icon'=>$this->ICON,
				'desc'=>'',
				'menu'=>[],
				'content'=>false,
				'item_title'=>false
			);
			$this->READY=true;
		}
		//setVars
		$this->ID=issetCheck($this->ROUTE,3);
		$this->REF=issetCheck($this->ROUTE,4);			
	}
	function Process($what=false,$ref=false){
		$this->init();
		if($this->METHOD==='POST') $this->ProcessPost($this->REQUEST);
		switch($this->ACTION){
			case 'edit':
				$this->renderItem($this->ID);
				break;
			case 'search_form':
				$this->renderSearch_form();
				break;
			case 'invoice':
				$this->renderInvoice();
				break;
			default:
				$a=($this->ACTION)?$this->ACTION:'latest';
				$this->renderList($a);
		}
		return $this->OUTPUT;		
	}
	private function ProcessPost($post){
		switch($post['action']){
			case 'update_cart':
				$post['TID']=$post['ref'];
				unset($post['ref'],$post['email_user'],$post['email_admin']);
				$rsp=$this->updateTransaction($post);
				$rsp['url']=$this->PERMLINK.'edit/'.$post['TID'];
				break;
			default:
				preME($post,2);
		}
		setSystemResponse($rsp['url'],$rsp['message']);
		die;
	}
//render stuff
	private function renderInvoice(){
		if($invoice=$this->SLIM->Sales->getInvoiceRecord('ref',$this->ID)){
			$download=($this->REF==='download')?'download':false;
			$INV=new slimInvoiceRender($this->SLIM);
			$content=$INV->render($invoice,$download);
		}else{
			$content=msgHandler('Sorry, No invoice found...',false,false);
		}
		echo renderCart_active('View Invoice #'.$this->ID,$content,$this->SLIM->closer);
		die;
	}
	private function renderItem($ref){
		$this->DATA=$this->LIB->get('cart',$ref);
        $state=(int)$this->DATA['status'];
		//render cart info
		$cart=$this->renderItem_cart();
		//render payment info
		$paid=$this->renderItem_payment();
		
		//render record
		$info=$this->renderItem_record();		
		
		$css='<style>#visEdit table tr th{text-align:left;color:#999;} table.record-table,table.info-table{font-size:90%;}table.info-table th{min-width:5rem; max-width:8rem!important;}table.info-table td{min-width:30rem;}
		table.record-table th{text-align:left; color:#999;}
		</style>';
        $table=$css."<div class='grid-x grid-margin-x'><div class='cell large-4'><div class='panel'>$info</div></div>\n";
        $proc=array('link'=>'','tab'=>'');
        if($state!=4){
			$proc['link']='<li class="tabs-title"><a href="#trans_up">Processing Info</a></li>';
			$proc['tab']='<div class="tabs-panel" id="trans_up">'.$this->renderItem_update($ref).'</div>';
		}        
        $tabs='<ul class="tabs" data-tabs id="trans-tabs"><li class="tabs-title is-active"><a href="#trans_cart">Cart Info</a></li><li class="tabs-title"><a href="#trans_pay">Payment Info</a></li>'.$proc['link'].'</ul>';
        $tabs.='<div class="tabs-content" data-tabs-content="trans-tabs"><div class="tabs-panel is-active" id="trans_cart">'.$cart.'</div><div class="tabs-panel" id="trans_pay">'.$paid.'</div>'.$proc['tab'].'</div>';
        $table.="<div id='visEdit' class='cell large-8'>$tabs</div></div>\n";

        $out['item_title'] = "<span>Ref: $ref</span>";
        $out['content'] = $table;
        $this->setOutput($out);
	}
	private function renderItem_update($ref){
		$parts=['payer_name','payer_email','txn_id','txn_type','payment_date','payment_type','payment_status','payment_detail','mc_gross','mc_currency','residence_country','status'];
		$tr='';
		$row=[];
		foreach($parts as $i){
			$lbl=ucME($i);
			$val=issetCheck($this->DATA,$i);
			switch($i){
				case 'payment_date':
					$row[$lbl]='<input type="datetime-local" name="'.$i.'" value="'.$val.'"/>';
					break;
				case 'payment_detail':
					if($this->isNull($val))$row[$lbl]='<textarea name="'.$i.'" rows="5"></textarea>';
					break;
				case 'status':
					$so='';
					foreach($this->STATES as $x=>$y){
						$sel=($x==$val)?'selected':'';
						$so.='<option value="'.$x.'" '.$sel.'>'.$y['label'].'</option>';
					}
					$row[$lbl]='<select name="'.$i.'">'.$so.'</select>';
					break;
				default:
					$row[$lbl]='<input type="text" name="'.$i.'" value="'.fixHTML($val).'"/>';
			}
		}
		$hidden='<input type="hidden" name="action" value="update_cart"/><input type="hidden" name="ref" value="'.$ref.'"/>';
		foreach($row as $i=>$v){
			$tr.='<tr><th>' . ucME(strtolower($i)) . '</th><td>' . $v . '</td></tr>';
		}
		return '<h5>Processing Info.</h5><form id="transform" method="post" action="'.$this->PERMLINK.'edit/'.$ref.'">'.$hidden.'<table>'.$tr.'</table></form>';
	}
	private function renderItem_record(){
		$tr='';
		foreach($this->DATA as $i=>$v){
			if(!in_array($i,['mc_gross','mc_currency','residence_country','payment_type','cart_data','payment_detail','sales_ref'])){
				switch($i){
					case 'user_ID':
						if($v) $v='<button data-ref="'.$this->PERMBACK.'member/view/'.$v.'" title="view account" class="button small button-purple expanded loadME">'.$v.'</button>';
						break;
					case 'sales_ref':
						if($v){
							$v='<button data-ref="'.$this->PERMBACK.'sales/edit_record/'.$v.'/ref" title="view invoice" class="loadME button small button-purple expanded">'.$v.'</button>';
						}
						break;
					case 'cart_ref':
						if($v){
							$v='<button data-ref="'.$this->PERMLINK.'/invoice/'.$v.'" title="veiw/download invoice" class="loadME button small button-purple expanded">'.$v.'</button>';
						}
						break;
				    case 'status':
						$v='<span class="'.$this->STATES[$v]['tclass'].'">'.$this->STATES[$v]['label'].'</span>';
				}
				$tr.='<tr><th>' . ucME(strtolower($i)) . '</th><td>' . fixHTML($v) . '</td></tr>';
			}
		}
		return '<h5>Record Info.</h5><table class="record-table">'.$tr.'</table>';
	}
	private function renderItem_cart($as_data=false){
		$c=issetCheck($this->DATA,'cart_data');
		if($c && $c!=='' && $c!=='NULL'){
			$tr='';
			$cart=json_decode($c,1);
			//get the cart/form id
			$cart['form_ref']=$this->ID;
			if($as_data) return $cart;
			foreach($cart as $i=>$v){
				if(!in_array($i,['jtoken','action','price','qty'])){
					if($i==='product'){
						$tmp='';
						foreach($v as $x=>$y){
							$price=$cart['price'][$x];
							$tmp.='<li>'.$cart['qty'][$x].' x '.$y.' @ '.$cart['price'][$x].'</li>';
						}
						$v='<ul>'.$tmp.'</ul>';
						$i='Products';
					}
					$tr.='<tr><th>' . ucME(strtolower($i)) . '</th><td>' . fixHTML($v) . '</td></tr>';
				}
			}
		}else{
			if($as_data) return false;
			$tr='<tr><td><div class="callout warning">No Cart Details</div></td></tr>';
		}
		return '<h5>Cart Info.</h5><table class="info-table">'.$tr.'</table>';
	}
	private function renderItem_payment($as_data=false){
		$c=issetCheck($this->DATA,'payment_detail');
		if($c && $c!=='' && $c!=='NULL'){
			$tr='';
			$cart=json_decode($c,1);
			if(!$cart){
				$cart=unserialize($c);
				if(!$cart) $cart=array();
			}
			if($as_data) return $cart;
			foreach($cart as $i=>$v){
				$tr.='<tr><th>' . ucME(strtolower($i)) . '</th><td>' . fixHTML($v) . '</td></tr>';
			}
		}else{
			if($as_data) return false;
			$tr='<tr><td><div class="callout warning">No Payment Details</div></td></tr>';
		}
		return '<h5>Payment Info.</h5><table class="info-table">'.$tr.'</table>';
	}
	private function renderList($what='browse'){
		$ref=null;
		if($what==='status' && $this->ID){
			$title=$this->STATES[$this->ID]['label'];
			$ref=$this->ID;
		}else{
			$title=($what==='latest')?'Latest':'All';
		}
		$this->DATA=$this->LIB->get($what,$ref);
		$table=$this->renderTable();
		$buts[]='search';
        $buts[]=($what==='browse')?'latest':'browse';
		$nav=$this->renderStatusNav();
        $out['item_title'] = '<span>'.$title.' Items ('.$this->TABLE_COUNT.')</span>';
        $out['content'] =  $nav.$table;
        $this->setOutput($out);
	}
	private function renderStatusNav(){
		$nav='';
		$state=(int)issetCheck($_GET,'state');
		foreach($this->STATES as $i=>$v){
			$cls=($state==$i)?'':str_replace('text','bg',$v['tclass']);
			if($i){
				$nav.='<button class="button '.$cls.' gotoME" data-ref="'.$this->PERMLINK.'status/'.$i.'">'.$v['label'].'</button>';
				if($state==$i)$title=ucME($v['label']);
			}
		}
		return '<div class="button-group small expanded">'.$nav.'</div>';
	}
	private function renderSearch_form(){
		$form='<form class="searchForm" method="get" action="'.$this->PERMLINK.'search/"><div class="input-group"><input class="input-group-field" name="find" type="text" placeholder="TXN, Cart Ref., Sales Ref., Name"><div class="input-group-button"><input type="submit" class="submitSearch button button-navy" value="Search"></div></div></form>';
        $out['item_title'] = 'Find Transactions';
        $out['content'] =  $form;
        if($this->AJAX){
			echo renderCard_active($out['item_title'],$out['content'],$this->SLIM->closer);
			die;
		}
        $this->setOutput($out);
	}
	private function renderContextMenu($args=false){
		$base_url=$this->PERMLINK;
		$menu=[];
 		$buts['latest']='<button class="small button button-blue gotoME" data-ref="'.$base_url.'latest" title="show latest records" ><i class="fi-list"></i> Latest Items</button>';
		$buts['browse']='<button class="small button button-dark-blue gotoME" data-ref="'.$base_url.'browse" title="show all records" ><i class="fi-list"></i> All Items</button>';
		$buts['dash']= '<button class="button small button-dark-purple gotoME" data-ref="' .$base_url.'" title="cart dashboard"><i class="fi-arrow-left"></i> Back</button>';
        $buts['search']= '<button class="button small button-navy loadME" data-ref="'. $base_url .'search_form" title="find transactions"><i class="fi-magnifying-glass"></i> Search</button>';
		$buts['update_record']= '<button class="button small button-olive trigger_submit" data-ref="transform" title="update this cart"><i class="fi-check"></i> Update</button>';
 		switch($this->ACTION){
			case 'edit':
				$a=['dash'];
				if($this->DATA['status']!==4) $a[]='update_record';
				break;
			default:
				$a=['search','browse','latest'];
		}
		foreach($a as $i){
			$m=issetCheck($buts,$i);
			if($m) $menu[]=$m;
		}
		return ($menu)?'<div class="button-group">'.implode("\n",$menu).'</div>':'';
	}
 	private function renderTable(){		
        $tbl=[];
        foreach ($this->DATA as $id=>$rec) {
            $tmp=[];
            foreach ($rec as $i=>$v) {
				switch($i){
					case 'status':
						$tmp[$i]='<span class="'.$this->STATES[$v]['tclass'].'">'.$this->STATES[$v]['label'].'</span>';
						break;
					case 'create_date':
						$tmp[$i]=str_replace(' ','<br/>',$v);
						break;
					default:
						$tmp[$i]=$v;
				}
            }
			$tmp['control']='<button class="button button-dark-purple small gotoME" data-ref="'.$this->PERMLINK . 'edit/' .$id . '"><i class="fi-eye"></i> View</button>';
			$tbl[$id]=$tmp;
        }
        if(!$tbl){
			$table= msgHandler( 'Sorry, no records found...',false,false);
			$ct=0;
		}else{
			$args['data']['data']=$tbl;
			$args['before']='filter';
			$table = dataTable($args);
 		    $ct=count($tbl);
		}
		$this->TABLE_COUNT=$ct;
 		return $table;	
	}
//data stuff	
	private function updateTransaction($args=false){
		$update=(is_array($args))?$args:$this->DATA;
		$chk=false;
		$unset=array('TID','action','adminNotes','payment_details');
		$res=array('message'=>'* cart log update error *','message_type'=>'alert','update'=>false,'status'=>500,'type'=>'message');		
		if($update){
			if($ref=issetCheck($update,'TID')){
				//remove empty dates
				if(isset($update['payment_date']) && !issetVar($update['payment_date'])) unset($update['payment_date']);
				if(isset($update['create_date']) && !issetVar($update['create_date'])) unset($update['create_date']);
				//unset
				foreach($unset as $i=>$v){
					if(isset($update[$v])) unset($update[$v]);
				}
				$res=$this->LIB->updateRecord($update,$ref);
			}
		}else{
			$res['message']='Sorry, no data supplied...';
		}
		return $res;
	}
	private function setOutput($data=[]){
		$d = array( 'content', 'buts', 'info', 'desc', 'script','subtitle','item_title','extra','menu');
        if ($data) {
            foreach ($data as $e => $v){
				switch($e){
					case 'item_title':
						$v=': <span class="item_title">'.strip_tags($v).'</span>';
						break;
				}            
				$this->OUTPUT[$e] = $v;
			}
        } else {
            foreach ($d as $e){
				$v=($e==='item_title')?': <span class="item_title">Dashboard</span>':false;
				$this->OUTPUT[$e] = $v;
			}
        }
		$this->OUTPUT['title'].=$this->OUTPUT['item_title'];
        $this->OUTPUT['icon'] = '<i class="fi-'.$this->PLUG['icon'].' icon-x1b"></i>';
        $this->OUTPUT['menu']['right'] = ($this->AJAX)?'':$this->renderContextMenu();
        if(!isset($this->OUTPUT['menu'])) $this->OUTPUT['menu'] = $this->renderContextMenu();
        if(!isset($this->OUTPUT['jqd'])) $this->OUTPUT['jqd'] = false;
	}
	private function isNull($var=NULL,$arr=false){
		$var=(string)$var;
		if(is_array($arr)){
			return issetCheck($arr,$var);
		}else if(!is_object($var) && !is_array($var)){
			$test=trim(strtolower((string)$var));
			if(in_array($test,array('','null','nothing'))) return true;
		}
		return false;
	}
}
