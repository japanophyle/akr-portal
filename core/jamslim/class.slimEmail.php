<?php

class slimEmail{
	private $SLIM;
	private $RECIPIENTS;
	private $SELECTED_RECIPIENTS;
	private $EMAIL;
	private $MESSAGES;
	private $LOAD_MAIL=false;
	private $LANG='fr';
	private $AJAX;
	private $ROUTE;
	private $PERM_LINKS;
	private $SESSION_NAME;
	private $VALID_ACTS=array(
		'writer',
		'messages',
		'new_message',
		'delete_message',
		'email_preview',
		'sender',
		'preview_recipients',
		'reset_recipients',
		'add',
		'quick_lists',
		'search_members'
	);
	private $PARTS;

	public $MAIL_BOT;
	public $ADMIN_EMAIL;
	public $ACTION;
	public $PERMBACK;
	public $PERMLINK;
	public $POST;
	public $TITLE;
	public $LOG_SEND=false;
	public $MODE=false;
	public $ARGS;
	
	function __construct($slim=null){
		$this->SLIM=$slim;
		$this->MESSAGES=new slimEmail_messages($slim);
		$this->LANG=$slim->language->get('_LANG');
		$this->AJAX=$slim->AppVars->get('ajax');
		$this->ROUTE=$slim->AppVars->get('route');
		$this->MAIL_BOT=$slim->options->getSiteOptions('email_mailbot',true);
		$this->ADMIN_EMAIL=$slim->options->getSiteOptions('email_administrator',true);
		$this->PERM_LINKS=getPermalink($this->ROUTE);
		$this->PERMBACK=$this->PERM_LINKS['back'];
		$this->PERMLINK=$this->PERMBACK.'mailer/';
		$this->TITLE='Emailer:';		
		$this->PARTS=$slim->EmailParts;
	}
	
	function setMode($m=false){
		switch($m){
			case 'subscription':
				$this->MODE=$m;
				$this->SESSION_NAME='subs_reminder';
				break;
			default:
				$this->MODE='admin';
				$this->SESSION_NAME='mlr_recipients';
				break;
		}
		$this->SLIM->Recipients->SESSION_NAME=$this->SESSION_NAME;
		$this->SLIM->Recipients->MODE=$this->MODE;
	}
	function PostMan($post){
		$this->POST=$post;
		$this->ACTION=issetCheck($post,'act');
		if(!$this->ACTION) $this->ACTION=issetCheck($post,'action');
		return $this->renderPOST();
	}
	private function setAction($var=false){
		if(!$var) $var=issetCheck($this->ROUTE,2);
		if(in_array($var,$this->VALID_ACTS)){
			$act=$var;
		}else{
			$act=($var)?$var:'messages';
		}
		$this->ACTION=$act;
	}
	function render($act=false){
		$this->setAction($act);
		$title=ucME($this->ACTION);
		$content=msgHandler('Hmmm, seems to be some problem...');
		$sidebar=false;
		$state=200;
		switch($this->ACTION){
			case 'add':
				return $this->addSelectedRecipients();
				break;
			case 'dashboard':
				$content=$this->renderDashboard();
				break;
			case 'writer':
				$id=(int)issetCheck($this->ROUTE,3);
				$content=$this->renderWriter($id);
				break;
			case 'messages':
				$content=$this->renderMessageList();
				break;
			case 'new_message':
				$content=$this->renderNewMessageForm();
				break;
			case 'email_preview':
				$id=(int)issetCheck($this->ROUTE,3);
				$content=$this->renderPreview($id);
				break;
			case 'sender':
				$id=(int)issetCheck($this->ROUTE,3);
				$content=$this->renderSender($id);
				break;
			case 'preview_recipients':
				$id=(int)issetCheck($this->ROUTE,3);
				$content=$this->renderPreviewRecipients($id);
				break;
			case 'recipients':
				$content=$this->renderRecipients();
				break;
			case 'list_selector':
				$content=$this->listSelector();
				break;
			case 'reset_recipients':
				$this->setSelectedRecipients(true);
				break;
			case 'search_members':
				$this->renderSearch();
				break;
			case 'quick_lists':
				$this->renderQuickLists();
				break;			
			default:
				$state=500;
				$title='Ooops!';
				$content=msgHandler('Sorry, I don\'t understand "'.$this->ACTION.'"...');

		}
		if(!$this->AJAX){
			if(is_array($content)){
				$content='<div class="grid-x grid-margin-x"><div class="cell medium-8">'.$content['A'].'</div><div class="cell medium-4">'.$content['B'].'</div></div>';
			}
		}
		return $content;			
	}
	function renderPOST(){
		$response['status']=500;
		switch($this->ACTION){
			case 'save_message':
			case 'email_send_save':
				$msg=issetCheck($this->POST,'email');
				$url_act=($this->ACTION==='email_send_save')?'sender':'writer';
				$id=(int)issetCheck($this->POST,'id');
				$url=$this->PERMLINK.$url_act.'/'.$id;
				//always save message
				$subj=issetCheck($this->POST,'subject','- no subject -');
				$response['url']=$url;
				if($msg && $msg!==''){
					if($this->ACTION==='email_send_save') $mail['LastSent']=date('Y-m-d H:i:s');
					$mail['Message']=$msg;
					$mail['Subject']=$subj;
					$mail['ID']=$id;
					$chk=$this->MESSAGES->set('save_message',$mail);
					if($chk){
						$response['status']=200;
						$response['type']='redirect';
						$response['message']=($url_act==='sender')?'Okay, the messge has been updated and is being sent.':'Okay, the messge has been saved.';
					}else{
						$response['message']='Sorry, there was a problem saving the message...';
					}
				}else{
					$response['type']='message';
					$response['message']='Sorry, the message seems to be empty...';
				}
				break;
			case 'update_selected_recipients':
			    $response=$this->setSelectedRecipients();
			    $response['url']=$this->PERMLINK.'writer';
				break;
			case 'add_unselected':
			    $response=$this->SLIM->Recipients->addSelected('ins',$this->POST['member']);
			    $response['url']=$this->PERMLINK.'writer';
				break;
			case 'new_message':
				$subject=issetCheck($this->POST,'subject');
				$response['type']='message';
				$response['url']=$this->PERMLINK.'messages';
				if($subject){
					$chk=$this->MESSAGES->set('new_message',array('Subject'=>$subject));
					if($chk){
						$response['message']='Okay, the message has been added...';
						$response['status']=200;
						$response['url']=$this->PERMLINK.'writer/'.$chk;
						$response['type']='redirect';
					}else{
						$response['message']='Sorry, there was a problem adding the message...';
					}
				}else{
					$response['message']='Sorry, the subject seems to be empty...';
				}
				break;
			default:
				$response['url']=$this->PERMLINK;
				$response['type']='message';
				$response['message']='Sorry, what are you tring to do?';
		}
		if($this->AJAX){
			$rsp=array('response'=>$response['status'],'type'=>'message','message'=>$response['data'],'message_type'=>'success','close'=>1);
			if($response['status']==500) $rsp['message_type']='alert';
			jsonResponse($rsp);
		}else{
			setSystemResponse($response['url'],$response['message']);
		}
		die;
	}
	function setLogging($state=false){
		$this->LOG_SEND=($state)?true:false;
	}
	function setRecipients($data=false){
		$out=false;
		if(is_array($data) && !empty($data)){
			foreach($data as $i=>$v){
				$email=issetCheck($v,'email');
				if($email){
					$out[$i]=$v;
				}
			}
		}else{
			$out=array();
		}
		$this->RECIPIENTS=$out;
	}
	private function addSelectedRecipients(){
		$what=issetCheck($this->ROUTE,3);
		$var=issetCheck($this->ROUTE,4);		
		return $this->SLIM->Recipients->addSelected($what,$var);
	}
	function addRecipients($data=false,$selected=false){
		$this->SLIM->Recipients->add($data,$selected);
		if($selected){
			$this->SELECTED_RECIPIENTS=$this->SLIM->Recipients->get('selected');
		}else{
			$this->RECIPIENTS=$this->SLIM->Recipients->get('recipients');
		}
	}
	function setEmail($mail=false){
		if($mail){
			if(is_string($mail)){
				$this->LOAD_MAIL=$mail;
				$mail=false;
			}else if(!is_array($mail)){
				$mail=$this->SLIM->Options->get('email_fields');
			}
		}
		$this->EMAIL=$mail;
	}
	private function renderDashboard(){
		$buttons=false;
		$title='Emailer';
		$content=msgHandler('This is for sending an email to a few recipients (You cannot add images or attachments).  Use the mailing list for bulk mailouts.','message',false);
		$content.='<a title="write/edit message" href="'.$this->PERMLINK.'writer" class="secondary hollow admLink button text-navy"><i class="text-blue fi-at-sign icon-x2"></i><br/><span class="caption">Write Message</span></a>';
		$content.='<a title="select recipients" href="'.$this->PERMBACK.'members/select" class="loadME secondary hollow admLink button text-navy"><i class="fi-torsos icon-x2"></i><br/><span class="caption">Select Recipients</span></a>';
		$out=renderCard_active($title,$content,$buttons);
		return $out;
	}
	private function renderSidebar(){
		$title='Recipients';
		$button='<button class="button small button-olive loadME" href="'.$this->PERMBACK.'members/view/writer/members_search" ><i class="fi-plus"></i></button>';
		$content=msgHandler('No recipients selected',false,false);
		if($this->RECIPIENTS){
			$content='<ol id="recipients">';
			foreach($this->RECIPIENTS as $i=>$v){
				$content.='<li>'.$v['name'].'<br/><small>'.$v['email'].'</small></li>';
			}
			$content.='</ol>';
		}
		return renderCard_active($title,$content,$button);
	}
	private function renderXForm($url,$action,$content,$id='formX'){
		if($url && $action && $content){
			$form='<form id="'.$id.'" method="post" action="'.$url.'"><input type="hidden" name="action" value="'.$action.'"/>';
			$form.=$content.'</form>';
			return $form;
		}else{
			return msgHandler('Sorry, invalid form vars...');
		}
	}
	
	private function renderSender($id=false){
		$email=$this->getMessages($id);
		if($email) $email=$email[$id];
		$email['Recipients']=$this->getSelectedRecipients();
		$chk=$this->sendEmail($email);
		if($chk['status']==200){
			$upd['Recipients']=array_keys($email['Recipients']);
			$upd['LastSent']=Date('Y-m-d H:i:s');
			$upd['Status']=1;
			$upd['ID']=$id;
			$this->MESSAGES->set('update_message',$upd);
			setMySession('set',array('subs_message'=>false,"{$this->SESSION_NAME}"=>false));
		}
		setSystemResponse($this->PERMLINK.'messages',$chk['message']);
		die;	
	}
	private function renderPreview($id=0){
		$email=$this->getMessages($id);
		if($email){
			$email=$email[$id];
			$email['Recipients']=$this->getSelectedRecipients();
		}
		return $this->renderEmailPreview($email);
	}
	private function renderWriterHelp(){		
		$title='<span class="text-dark-purple">Email Hints</span>';
		$content='<div class="callout">';
		$content.='<p class="lead">You can use the following codes to pull member information into the email.</p><ul>';
		$content.='<li><code>{name}</code>: display the members full name</li>';
		$content.='<li><code>{dojo}</code>: display the members dojo</li>';
		$content.='<li><code>{grade}</code>: display the members grade</li>';
		$content.='<li><code>{id}</code>: display the members database id</li>';
		$content.='</ul>';
		$content.='</div>';
		return renderCard_active($title,$content,$this->SLIM->closer);
	}
	private function renderWriter($id=false){
		$email=$this->getMessages($id);
		if($email) $email=$email[$id];
		$email['Recipients']=$this->getSelectedRecipients();
		$title='Email Writer';
		$parts=$this->renderEmailForm($email);
		$url=($this->MODE==='subscription')?$this->PERMBACK.'subscriptions/subs_notify/writer':$this->PERMLINK;
		$content=$this->renderXForm($url,'save_message',implode('',$parts['message']),'ajax_form');
		$content.='<div class="reveal" id="writer_help" data-reveal data-animation-in="slide-in-down" data-animation-out="spin-out">'.$this->renderWriterHelp().'</div>';
		$buttons='<button class="small button button-lavendar" data-open="writer_help"><i class="fi-first-aid"></i> Help</button>';
		$buttons.='<button class="small button button-dark-blue loadME" data-ref="'.$this->PERMLINK.'messages"><i class="fi-mail"></i> Messages</button>';
		$buttons.='<button class="small button button-navy loadME" data-ref="'.$this->PERMLINK.'email_preview/'.$id.'"><i class="fi-eye"></i> Preview</button>';
		$buttons.='<button class="small button button-olive submitME" data-ref="ajax_form"><i class="fi-check"></i> Save</button>';
		$buttons.='<button id="email-save-send" class="small button button-dark-green submitME" data-ref="ajax_form" data-act="email_send_save"><i class="fi-arrow-right"></i> Save & Send</button>';
		if($this->MODE==='subscription') $this->TITLE.=' <span class="subheader">Subscriptions</span>';
		$out['A']=renderCard_active($title,$content,$buttons);
		$out['B']=$this->renderSelectedRecipients();	
		return $out;
	}
	private function renderPreviewRecipients($id=0){
		$email=$this->getMessages($id);
		if($email) $email=$email[$id];
		$rp=json_decode($email['Recipients'],true);
		$row=false;
		$title='Previous Recipients';
		$buttons='<button class="small button button-dark-blue gotoME" data-ref="'.$this->PERMLINK.'add/messages/'.$id.'"><i class="fi-plus"></i> Add to Send List</button>&nbsp;&nbsp;&nbsp;';
		$buttons.=$this->SLIM->closer;
		foreach($rp as $mid){
			$rec=$this->SLIM->options->get('member_info',$mid);
			if($rec){
				$row[]='<tr><td>'.$mid.'</td><td>'.$rec['Name'].'<br/><small class="text-blue">'.$rec['Email'].'</small></td><td>'.$rec['Dojo'].'</td><td>'.$rec['CGradeName'].'</td></tr>';
			}
		}
		if($row){
			$thead='<th>ID</th><th>Name/Email</th><th>Dojo</th><th>Grade</th>';
			$content='<div class="tabs-content"><table><thead><tr>'.$thead.'</tr></thead><tbody>'.implode('',$row).'</tbody></table></div>';
		}else{
			$content=msgHandler('No recipients found...',false,false);
		}
		if($this->AJAX){
			echo renderCard_active($title,$content,$buttons);
			die;
		}else{
			return $content;
		}
	}
	private function renderSearch($what=false){
		$title='Search: Members';
		$data=$this->SLIM->Recipients->get('unselected');
		$tpl=file_get_contents(TEMPLATES.'parts/tpl.select_members.html');
		$fill['hidden']='<input type="hidden" name="action" value="add_unselected"/>';
		$fill['form_url']=$this->PERMLINK.'add';
		foreach($data as $i=>$v){
			$chkbox='<div class="checkboxTick"><input type="checkbox" value="'.$i.'" id="cbk_'.$i.'" name="member[]" /><label for="cbk_'.$i.'"></label></div>';
			$r='<td>'.$v['name'].'<br/><small class="text-dark-blue">'.$v['type'].'</small></td>';
			$r.='<td>'.$v['dojo'].'<br/><small class="text-dark-green">'.$v['grade'].'</small></td>';
			$r.='<td>'.$chkbox.'</td>';
			$row[]='<tr class="visible">'.$r.'</tr>';														
		}
		$fill['rows']=implode('',$row);
		foreach($fill as $i=>$v) $tpl=str_replace('{'.$i.'}',$v,$tpl);
		if($this->AJAX){
			echo $tpl;
			die;
		}else{
			return $tpl;
		}
	}
	private function renderQuickLists(){
		$content='';
		$title='Quick Lists';
		$sections['Members']=array(
			'active'=>array('color'=>'olive','icon'=>'torsos','caption'=>'Active','options'=>false),
			'inactive'=>array('color'=>'gray','icon'=>'torsos','caption'=>'Inactive','options'=>false),
			'unpaid_sales'=>array('color'=>'maroon','icon'=>'results-demographics','caption'=>'Outstanding Payments','options'=>false),
		);
		$sections['Member_Type']=array(
			'type'=>array('color'=>'dark-blue','icon'=>'address-book','caption'=>'Type','options'=>'membertype')
		);
		$sections['Grade']=array(
			'grade'=>array('color'=>'purple','icon'=>'universal-access','caption'=>'Grade','options'=>'grades')
		);
		$sections['Dojo']=array(
			'dojo'=>array('color'=>'navy','icon'=>'target','caption'=>'Dojo','options'=>'dojo_count_active')
		);
		$sections['Subscriptions']=array(
			'active'=>false,
			'membership'=>false,
			'last_30'=>false,
			'next_30'=>false,
			'ikyf'=>false,
			'subs_unpaid'=>false,
		);
		$sections['User_Level']=array('admins'=>false,'leaders'=>false,'users'=>false);
		$sections['Events']=array('admins'=>false,'leaders'=>false,'users'=>false);
		$lnk='<a title="{title}" href="{url}" class="secondary hollow admLink button text-{color}"><i class="fi-{icon} icon-x2"></i><br/><span class="caption">{caption}</span></a>';
		$fill=array('title'=>'','url'=>'#','color'=>'navy','icon'=>'torso','caption'=>'&nbsp;<br/>&nbsp');
		$buttons=$this->SLIM->closer;
		foreach($sections as $sect=>$parts){
			$links=false;
			foreach($parts as $i=>$v){
				$filler=$fill;
				$filler['url']=$this->PERMLINK.'add/'.$i;
				if($v && $v['options']){//lookup
					$filler['icon']=$v['icon'];
					$filler['color']=$v['color'];
					$filler['caption']=$v['caption'];
					$opts=$this->SLIM->options->get($v['options']);
					if($opts){
						$u=$filler['url'];
						if($sect==='Dojo'){
							$clubs=$this->SLIM->options->get('clubs');
							foreach($opts as $x=>$y){
								if($y['DojoID']>0){
									$clb=issetCheck($clubs,$y['DojoID']);
									if(!$clb) preME($y,2);
									$clb=($clb)?$clb['ShortName']:'Dojo #'.$y['DojoID'];
									$filler['url']=$u.'/'.$y['DojoID'];
									$filler['caption']=$clb;
									$links.=replaceMe($filler,$lnk);
								}
							}
						}else{
							foreach($opts as $x=>$y){
								$ukey=($sect==='Grade')?$y['OptionValue']:$x;
								$filler['url']=$u.'/'.$ukey;
								$filler['caption']=$y['OptionName'];
								$links.=replaceMe($filler,$lnk);
							}
						}
					}else{
						$links.=replaceMe($filler,$lnk);
					}
				}else{
					if($sect==='Subscriptions'){
						$filler['icon']='torsos-all-female';
						$filler['color']='red-orange';
						$filler['caption']=ucME($i);
					}else if($sect==='User_Level'){
						$filler['icon']='torso-business';
						$filler['color']='dark-green';
						$filler['caption']=ucME($i);
					}else{
						if(!$v || !is_array($v))continue;
						$filler['icon']=$v['icon'];
						$filler['color']=$v['color'];
						$filler['caption']=$v['caption'];
					}
					$links.=replaceMe($filler,$lnk);
				}
			}
			if($links){
				$size='medium-6';
				$content.='<div class="cell '.$size.' callout"><h5>'.ucME($sect).'</h5>'.$links.'</div>';
			}
		}
		$content='<div style="max-height:32rem"><div class="grid-x">'.$content.'</div></div>';
		if($this->AJAX){
			echo renderCard_active($title,$content,$buttons);
			die;
		}else{
			return $content;
		}
	}
	private function getMessages($id=false){
		if(is_numeric($id)){
			$recs=$this->MESSAGES->get('id',$id);
		}else{
			$recs=$this->MESSAGES->get('all',$id,'LastSent DESC');
		}
		return $recs;
	}
	private function renderMessageList(){
		$messages=$this->getMessages();
		$title='Messages';
		$buttons='';
		$top_buttons='<button data-tooltip title="search for members and add then to the send list" class="small button button-maroon loadME" data-ref="'.$this->PERMLINK.'search_members"><i class="fi-torso"></i> Search</button>';
		$top_buttons.='<button data-tooltip title="select members by grade,dojo, active etc." class="small button button-dark-blue loadME" data-ref="'.$this->PERMLINK.'quick_lists"><i class="fi-torso"></i> Quick Lists</button>';
		$this->SLIM->topbar->setTopButton($top_buttons,'test');
		if($messages){
			$states=array(0=>'-',1=>'Sent',2=>'Disabled');
			$data=[];
			foreach($messages as $i=>$v){
				$data[$i]=$v;
				if(isset($v['Owner'])){
					if($v['Owner']){
						$rec=$this->SLIM->db->Users->select("id, Name")->where('id',$v['Owner']);
						$rec=renderResultsORM($rec,'id');
						$owner=$rec[$v['Owner']]['Name'];
					}else{
						$owner='-';
					}
					$data[$i]['Owner']=$owner;
				}
				$state=$states[$v['Status']];
				$data[$i]['Status']=$states[$v['Status']];
				$buts='<button class="small button button-navy loadME" data-ref="'.$this->PERMLINK.'email_preview/'.$i.'"><i class="fi-mail"></i> Preview</button>';
				$buts.='<button class="button gotoME small" data-ref="'.$this->PERMLINK.'writer/'.$i.'"><i class="fi-pencil"></i> Select</button>';
				$data[$i]['Controls']=$buts;
			}
			$args['data']['data']=$data;
			$args['before']='filter';
			$content=dataTable($args);
			$buttons='<button class="button small button-olive loadME" data-ref="'.$this->PERMLINK.'new_message"><i class="fi-plus"></i> New Message</button>';
		}else{
			$content=msgHandler('Sorry, no messages found...<br><button class="button button-olive loadME" data-ref="'.$this->PERMLINK.'new_message"><i class="fi-plus"></i> Add a new message</button>',false,false);
		}
		if($this->AJAX){
			$content.='<div class="button-group expanded">'.$buttons.'</div>';
			echo renderCard_active($title,$content,$this->SLIM->closer);
			echo '<script>JQD.ext.initMyTable(".reveal #dTable_filter",".reveal #dTable");</script>';
			die;
		}
		$out['A']=renderCard_active($title,$content,$buttons);
		$out['B']=$this->renderSelectedRecipients();
		return $out;
	}
	private function setSelectedRecipients($empty=false){
		return $this->SLIM->Recipients->set($empty);
	}
	
	private function getMemberInfo($id=0){
		return $this->SLIM->options->get('member_info',$id);
	}
	private function getSubsInfo($id=0){
		$notify=issetCheck($this->ARGS,1,0);
		return $this->SLIM->Subscriptions->getRecords('id',$id,true,$notify);
	}
	
	private function listSelector($data=false){
		$key=issetCheck($this->ROUTE,3);
		$d=(is_array($data))?$data:$this->SLIM->options->get('expire_lists');
		$opts=false;
		foreach($d as $i=>$v){
			$sel=($key===$i)?'selected':'';
			$opts.='<option value="'.$i.'" '.$sel.'>'.$v.'</option>';
		}
		return $opts;
	}
	private function renderRecipients($what=false){
		if(!$what) $what=issetCheck($this->ARGS,0,'selected');
		$lists=$this->SLIM->options->get('expire_lists');
		$this->TITLE='Recipients: <span class="subheader">Subscriptions '.$lists[$what].'</span>';
		$selector='<select id="report_list" class="selectSwitch">'.$this->listSelector($lists).'</select>';
		$title='Members';
		$hidden='<input type="hidden" name="list_type" value="'.$what.'"/>';
		$thead='<th>Name/Email</th><th>Item</th><th>Dates</th><th>Select</th>';
		foreach($this->RECIPIENTS as $i=>$v){
			$box='<div class="checkboxTick"><input id="tick_'.$i.'" type="checkbox" class="tickbox" name="send['.$i.']" checked/><label for="tick_'.$i.'"></label></div>';
			$row[$i]='<tr><td>'.$v['name'].'<br/><small class="text-dark-blue">'.$v['email'].'</small></td><td>'.$v['item'].'</td><td><small>Start: '.$v['start'].'<br/>End: '.$v['end'].'</small></td><td>'.$box.'</td></tr>';
		}
		$filter='<div id="filter">'.$this->SLIM->zurb->inlineLabel('Filter','<input id="dfilter" class="input-group-field" type="text"/>');
		$filter.='<div class="metrics">'.(count($row)).' Record(s)</div></div>';
		$table='<table id="dataTable" class="row_hilight"><thead><tr>'.$thead.'</tr></thead><tbody>'.implode('',$row).'</tbody></table>';
		$this->SLIM->assets->set('js','JQD.ext.initMyTable("#dfilter","#dataTable");','my_table');
		$this->SLIM->assets->set('js','$("#report_list").on("change",function(){var v=$(this).val(); var u="'.$this->PERMLINK.'recipients/"+v; JQD.utils.setLocation(u);});','select_me');
		$this->SLIM->assets->set('js','JQD.inits.initToggleTicks();','toggle_me');
		
		$top_buttons='<button class="small button button-dark-blue gotoME" data-ref="'.$this->PERMLINK.'writer"><i class="fi-mail"></i> Write Email</button>';
		$buttons=$selector.'<button class="small button button-dark-green submitME" data-ref="formX"><i class="fi-plus"></i> Add to Send List</button>';
		$buttons.='<button class="small button button-navy toggleSelection" data-target="formX" data-class="tickbox" title="Toggle Selected"><i class="fi-checkbox"></i></button>';
		$table=$this->renderXForm($this->PERMBACK.'subscriptions/subs_notify/recipients/selected','update_selected_recipients',$filter.$table.$hidden);
		$this->SLIM->topbar->setTopButton($top_buttons,'test');
		$out['A']=renderCard_active($title,$table,$buttons);
		$out['B']=$this->renderSelectedRecipients();		
		return $out;
	}
	private function renderSelectedRecipients($sidebar=true){
		$row=[];
		$this->getSelectedRecipients();
		$title='Send List';
		$hidden='<input type="hidden" name="list_type" value="selected"/>';
		if($id=(int)issetCheck($this->ROUTE,3)){
			$hidden='<input type="hidden" name="ID" value="'.$id.'"/>';
		}
		$thead=($sidebar)?'<th>Name/Email</th><th>Remove</th>':'<th>Name/Email</th><th>Item/Dates</th><th>Remove</th>';
		foreach($this->SELECTED_RECIPIENTS as $i=>$v){
			$box='<div class="checkboxTick"><input id="tick2_'.$i.'" type="checkbox" class="tickbox" name="remove['.$i.']" /><label for="tick2_'.$i.'"></label></div>';
			$tt=($this->MODE=='subscription')?$v['item'].' / Start: '.$v['start'].' / End: '.$v['end']:$v['grade'].' / '.$v['dojo'];
			if($sidebar){
				$row[$i]='<tr><td><a data-tooltip title="'.$tt.'" href="'.$this->PERMBACK.'member/view/'.$i.'" class="loadME" >'.$v['name'].'<br/><small class="text-dark-blue">'.$v['email'].'</small></a></td><td>'.$box.'</td></tr>';
			}else{
				$row[$i]='<tr><td>'.$v['name'].'<br/><small class="text-dark-blue">'.$v['email'].'</small></td><td>'.$v['item'].'<br/><small>Start: '.$v['start'].' / End: '.$v['end'].'</small></td><td>'.$box.'</td></tr>';
			}
		}
		$title.=' ('.count($row).')';
		$table='<table class="row_hilight"><thead><tr>'.$thead.'</tr></thead><tbody>'.implode('',$row).'</tbody></table>';
		if($sidebar) $table='<div style="max-height:500px; overflow-y:auto;">'.$table.'</div>';
		$buttons='<button class="small button button-olive  submitME" data-ref="form2"><i class="fi-check"></i> Update List</button>';
		$buttons.='<button class="small button button-red text-navy gotoME" data-ref="'.$this->PERMLINK.'reset_recipients" title="empty list"><i class="fi-trash"></i></button>';
		$table=$this->renderXForm($this->PERMLINK,'update_selected_recipients',$table.$hidden,'form2');
		return renderCard_active($title,$table,$buttons);		
	}
	
	private function getSelectedRecipients(){
		if(!$this->SELECTED_RECIPIENTS||empty($this->SELECTED_RECIPIENTS)){
			$data=$this->SLIM->Recipients->get('selected');
			$this->SELECTED_RECIPIENTS=($data)?sortArrayBy($data,'name'):[];
		}
		return $this->SELECTED_RECIPIENTS;
	}
	private function renderEmailPreview($email=false){
		$tpl=file_get_contents(TEMPLATES.'app/app.email_preview_ahk.html');
		$closer=$this->SLIM->closer;
		$title='Email Preview:&nbsp<span class="subheader text-dark-blue">'.$email['Subject'].'</span>';
		$rec=($email['Recipients'])?current($email['Recipients']):[];
		if($this->MODE==='subscription'){
			$rec['url']=URL.'page/?subs='.base64_encode('subs_ref_'.$rec['subs_id']);
		}else{
			$rec['url']=URL.'page/home';
		}
		$message=replaceMe($rec,$email['Message']);
		$parts=$this->PARTS;
		$parts['content']=$message;
		$content='<div>'.$this->fillMe($parts,$tpl).'</div>';
		echo renderCard_active($title,$content,$closer);
		die;

	}
	private function fillMe($parts,$tpl){
		foreach($parts as $i=>$v){
			$tpl=str_replace('{::'.$i.'::}',$v,$tpl);
		}
		return $tpl;
	}
	private function renderNewMessageForm($email=false){
		$title='New Message';
		$content='<label>Enter the subject for the email.<input type="text" name="subject" placeholder="enter a subject" /></label>';
		$content.='<input type="hidden" name="action" value="new_message"/>';
		$content.='<button type="submit" class="button button-olive expanded" ><i class="fi-check"></i> Add Message</button>';
		$form='<form name="ajaxForm" action="'.$this->PERMLINK.'" method="post">'.$content.'</form>';
		echo renderCard_active($title,$form,$this->SLIM->closer);
		die;
	}
	private function renderEmailForm($email=false){
		$editor_class="cl-edit";
		$this->SLIM->assets->set('js','JQD.ext.initEditor(".'.$editor_class.'");','editor');
		$JFORM = new jamForm;
		$JFORM->EDITOR_CLASS=$editor_class;
		$countdown='';
		$canSend=true;
		//message
		$args=array(
			'name'=>'subject',
			'attr_ar'=>array('value'=>$email['Subject']),
			'type'=>'text'
		);
		$message['subject']='<label>Subject'.$JFORM->get('input',$args).'</label>';
		$args=array(
			'name'=>'email',
			'value'=>$email['Message'],
			'attr_ar'=>array('id'=>'email_edit'),
		);
		$message['email']='<label>Message</label>'.$JFORM->get('editor',$args);
		$message['hidden']='<input type="hidden" name="id" value="'.$email['ID'].'" />';
		//sidebar
		$sent=($email['LastSent'])?validDate($email['LastSent']):'- not sent -';
		
		//add controls
		$button='<button class="small button button-navy loadME" data-ref="'.$this->PERMLINK.'email_preview"><i class="fi-mail"></i> Preview</button>';
		$button.='<button class="small button button-green submitME" data-ref="ajax_form"><i class="fi-check"></i> Save</button>';
		if($canSend){
			$button.='<button id="email-save-send" class="small button button-blue submitME" data-ref="ajax_form" data-act="email_send_save"><i class="fi-arrow-right"></i> Save & Send</button>';
		}		
		return array('message'=>$message,'buttons'=>$button);			
	}
	
	function saveEmail($mail=false){
		if($mail){
			$name='renewal_message_'.$this->SLIM->user['id'];
			$OP=$this->SLIM->db->Options()->where('OptionName',$name);
			setMySession('subs_message',1);
			if(count($OP)>0){
				$upd=array('OptionValue'=>$mail);
				$OP->update($upd);
			}else{
				$ins=array('OptionName'=>$name,'OptionGroup'=>'mail_message','OptionValue'=>$mail,'OptionDescription'=>1,'OptionID'=>$this->SLIM->user['id']);
				$OP->insert($ins);
			}
		}
	}
	
	function getEmail(){//load a message
		if(!$this->EMAIL){
			if($this->LOAD_MAIL){
				$name[]=$this->LOAD_MAIL;
			}else{
				$name[]='email_message';
			}
			$name[]='renewal_message_'.$this->SLIM->user['id'];

			$OP=$this->SLIM->db->Options()->where('OptionName',$name);
			$mail=false;
			if(count($OP)>0){
				$rec=renderResultsORM($OP);
				$rec=current($rec);
				$mail=compress($rec['OptionValue'],false);
			}
			$this->setEmail($mail);
		}
		return $this->EMAIL;
	}
	
	private function prepReminderData($recipients=[]){
		//add parts to fill email template
		$out=false;
		if($recipients){
			foreach($recipients as $i=>$v){
				$v['url']=URL.'page/?subs='.base64_encode('subs_ref_'.$i);
				$out[$i]=$v;
			}
		}
		return $out;		
	}
	
	function fixSectionTags($str=false){
		$pattern = "=^<p>(.*)</p>$=i";
		preg_match($pattern, $str, $matches);
		return issetCheck($matches,1);
	}
	private function sendEmail($mail=false,$recipients=false){
		$message=$mail;
		$lapsed=0;
		/*
		if($message['sent']!=='-'){
			$lapsed=getTimeLapsed($message['sent']);			
			$canSend=($lapsed>15)?true:false;
		}else{
			$canSend=true;
		}
		*/
		$canSend=true;
		if(!$canSend){
			return array('status'=>500,'message'=>'Sorry, you can\'t send emails right now... try again in '.(15-$lapsed).' minutes.');
		}
		if(!$message){
			return array('status'=>500,'message'=>'Sorry, you I can\'t find an email message with that ID...');
		}		
		if(!$recipients){
			$recipients=issetCheck($message,'recipients');
			if(!$recipients) $recipients=$this->getSelectedRecipients();
			if(!$recipients){
				return array('status'=>500,'message'=>'Sorry, I can\'t find any rcipients...');
			}
		}
		$header=$this->PARTS['header'];//$this->SLIM->language->getStandardContent('email_header');
		$footer=$this->PARTS['footer'];//$this->SLIM->language->getStandardContent('email_footer');
		$header=$this->fixSectionTags($header);
		$footer=$this->fixSectionTags($footer);
		//prep reminder emails
		if($this->MODE==='subscription'){
			$recipients=$this->prepReminderData($recipients);
		}
		$mailer_tpl=array(
			'to'=>false,
			'subject'=>false,
			'message'=>array(0=>'text',1=>'html'),
			'from'=>false,
			'logo'=>$this->PARTS['logo']
		);
		$state=500;
		$alts=array('name','email','dojo','grade','id','item');
		if($message['Subject'] && $message['Subject']!==''){
			if($message['Message'] && $message['Message']!==''){
				$ct=0;
				$stamp=date('Y-m-d H:i:s');				
				foreach($recipients as $i=>$v){
					$send=$mailer_tpl;
					if($v['email'] && $v['email']!=='' && strtolower($v['email'])!=='null'){
						$send['to']=$v['email'];
						$send['from']=$this->MAIL_BOT;
						$send['subject']='AKR: '.$message['Subject'];
						foreach($alts as $a){
							$send[$a]=issetCheck($v,$a);
						}
						$msg=replaceMe($v,$message['Message']);
						$send['message'][0]=strip_tags($msg);
						$send['message'][1]=$msg;
						$send['header']=$header;
						$send['footer']=$footer;
						$chk=$this->SLIM->Mailer->Process($send);
						if($chk){
							$ct++;
							if($this->LOG_SEND && $v['member_id']>0){
								$log['notes']=array('message'=>'email sent to user.','subs_id'=>$v['subs_id']);
								$log['subject']=$send['subject'];
								$this->setMemberLog($v['member_id'],$log);
							}
						}
					}
				}
				$msg='Okay, '.$ct.' Emails are being sent.';
				$state=200;
			}else{
				$msg='Sorry, the message seems to be blank.';
			}
		}else{
			$msg='Sorry, the subject line seems to be blank.';
		}
		return array('status'=>$state,'message'=>$msg);	
	}
	function sendPreppedMessages($mail=false,$log_msg='message sent to user'){
		preME('no!!',2);//not in use
		if(!is_array($mail)){
			return array('status'=>500,'message'=>'Sorry, no prepared mail supplied...');	
		}
		$stamp=date('Y-m-d H:i:s');				
		foreach($mail as $i=>$v){
			if($v['to'] && $v['to']!=='' && strtolower($v['to'])!=='null'){
				$subs=issetCheck($v,'subs_id');
				$log_type=issetCheck($v,'log_type');
				$member=issetCheck($v,'member_id');
				if($subs) unset($v['subs_id'],$v['log_type']);
				$insert=array('mq_Email'=>$v['to'],'mq_Date'=>date('Y-m-d H:i:s'),'mq_Message'=>serialize($v),'mq_Status'=>'pending','mq_Last'=>$stamp);
				$QUE=$this->SLIM->db->myp_mail_queue();
				$chk=$QUE->insert($insert);
				if($chk){
					if($member && $this->LOG_SEND){
						$log['notes']=array('message'=>$log_msg,'subs_id'=>$subs);
						$log['subject']=$v['subject'];
						$log['type']=$log_type;
						$this->setMemberLog($member,$log);
					}
				}
			}
		}		
	}
	function setMemberLog($member_id=0,$log=false){
		if($member_id && is_array($log)){
			$add=array(
				'MembersLogID'=>0,
				'MembersID'=>$member_id,
				'LogDate'=>time(),
				'LogSubject'=>issetCheck($log,'subject',__CLASS__.': no subject'),
				'LogDetails'=>serialize(issetCheck($log,'notes')),
			);
			$LG=$this->SLIM->db->MembersLog();
			$chk=$LG->insert($add);
		}
	}
	
	function reminderMail($lang='en'){
		return '<h2>Hello {name},</h2><p>This is just a quick note to let you know that your subscription will be expiring soon.</p><ul><li>{item}</li><li>Start Date: {start}</li><li><strong>Expires on: {end}</strong></li><strong></strong></ul><p><strong></strong></p><table><tbody><tr><td align="center"><p><a href="{url}" class="button">Login at our site to renew your subscription</a></p></td></tr></tbody></table><p><strong></strong></p>';
	}
	function getFooter($lang='en'){
		return $this->PARTS['footer'];
	}
	function getHeader($lang='en'){
		return $this->PARTS['header'];
	}
}
