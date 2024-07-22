<?php

class slimFaux {

    //this class provides the content for faux pages, like the payment responder
    private $PARTS;
    private $SETUP;
    private $ROUTE;
    private $TYPE;
    public $OUTPUT;
    public $FORM_REF;//array
    private $PRODUCTS;//array for payment response
    private $MESSAGE;
    private $SLIM;
    private $ARGS;
    private $TEMPLATES;
    private $AJAX;
    private $USER;
    private $PERMBASE;
    private $AdminMail;
    private $CSMail;

    public function __construct($slim) {
		if(!$slim) throw new Exception('no slim object!!');
		$this->SLIM=$slim;
        $this->USER=$slim->user;
        $this->SETUP = $slim->config;
        $this->ROUTE = $slim->router->get('route');
        $this->AJAX = $slim->router->get('ajax');
        $this->PERMBASE = URL.'page/';
        $this->PARTS = array('mainTitle', 'pageTitle', 'mainContent', 'mainExtra', 'mainArticles', 'js','jqd', 'mainSocial','message','mainImage');
        $this->AdminMail = $slim->Options->get('admin_email');
        $this->CSMail = $this->AdminMail;
     }

    public function Process($args) {
        $this->TYPE = issetCheck($args,'type');
        $this->ARGS=$args;
        $this->setData();
        return $this->OUTPUT;
    }
    
    private function getTemplate($args,$template=false){
		$defs=array('message'=>'','ref_no'=>'','customer_services'=>'','name'=>'','products'=>'','home_url'=>$this->PERMBASE.'home','login_url'=>'','retry'=>'');
	    if(!$template) $template=$this->TYPE;
	    $tmp=issetCheck($this->TEMPLATES,$template);
	    if(!$tmp){
			$tmp=file_get_contents(TEMPLATES.'parts/tpl.'.$template.'.html');
			if($tmp) $this->TEMPLATES[$template]=$tmp;
		}	    
	    if(!$tmp) $tmp=$this->TEMPLATES['default'];
	    return replaceME($args,$tmp);
	}
	function renderPaymentResponse(){
		$RSP = new paypal_response($this->SLIM);
		$out= $RSP->Process($this->TYPE);
		$o=array('title'=>'Payment Error?','content'=>msgHandler('Sorry, no response from the paypal bot...',false,false).'<p>Please contact customer services stating code:<strong>PPR1</strong></p>');
		if($out){
			$o['title']=$out['title'];
			$o['content']=$out['html'];
			$o['cart_ref']=$out['rec']['cart_ref'];
			$RSP->emptySessionVars();//clears session cart & system response			
		}
		return $o;
	}
	private function renderCheckout(){
		$RSP = new slim_checkout_cart($this->SLIM);
		$out= $RSP->Process();
		$o=array('title'=>'Payment Error?','content'=>msgHandler('Sorry, no response from the paypal bot...',false,false).'<p>Please contact customer services stating code:<strong>PPR1</strong></p>');
		if($out){
			$o['title']=$out['title'];
			$o['content']=$out['html'];
			$o['cart_ref']=$out['cart_ref'];
		}
		return $o;
	}
    private function getVerifyAccount() {
        $code = issetCheck($_REQUEST,'vc');
        $output = false;
        $cs=customerServices('account activation',$this->CSMail);
        if($code){
			$rec=$this->SLIM->Users->get('verify',$code);
            if ($rec) {
                $user['id'] = $rec['usr_ID'];
                $user['access'] = $rec['usr_Clearance'];
                $user['name'] = $rec['usr_Name'];
                $user['uname'] = $rec['usr_Username'];
                $user['expire']=time()+3600;
                session_regenerate_id(TRUE);
                /* erase data carried over from previous session */
                $_SESSION = array();
                $_SESSION["userArray"] = $user;
                /* clear verify code */
                $opts['ID']=$rec['usr_ID'];
                $opts['usr_Options']='';
                $opts['usr_Active']='1';//set as active
                $opts['usr_Clearance']='1';//set as customer
                $chk=$this->SLIM->Users->set('verified',$opts);
 				$url = URL.'page/my-home';
                if($chk){
					$msg=msgHandler('Okay, the account has been activated.');
					$output = $msg.'<h2>Hello '.$user['name'] .',</h2><p>Thanks for jumping through our security hoops...<br/><br/>Your account is now active and we have logged you in.<br/>Please click the button below to go to your account page, from there you can manage your details and gain to access our online features.<br/><br/><a class="button button-olive" href="' . $url. '">My Merlin</a></p><p></p>';
				}else{
					$msg=msgHandler('Sorry, there was a problem activating the account.');
					$output = $msg.'<h2>Hello '.$user['name'] .',</h2><p>Thanks for jumping through our security hoops...<br/><br/>Unfortunatley, there was a server error which prevented us from activating the account.<br/>Please try the link again...</p><p></p>';
				}
            } else {
                $output = msgHandler('Sorry, that code is invalid.');
            }
        } else {
            $output =msgHandler('Hmmm, I don\'t think you should be here....');
        }
        $output.='<p>If you are having any problems with this process please contact our '.$cs.' who will be happy to help.<br/><br/><strong>Team TBS</strong></p>';
        return $output;
    }
	
	private function getMailingList(){
		switch($this->TYPE){
			case 'unsubscribe':
				$out=file_get_contents(TEMPLATES.'parts/tpl.subscribe.html');
				if($out){
					//skip
				}else{
					$msg="Sorry, I can't find any subscription details to remove...<br/>Please try again or contact our ".customerServices('Mailing List Unsubscribe',$this->CSMail)." Team.";
					$out=msgHandler($out);
				}
				break;
			case 'subscribe':
				$out=file_get_contents(TEMPLATES.'parts/tpl.unsubscribe.html');
				if($out){
					//skip
				}else{
					$msg="Sorry, I can't find any subscription details to remove...<br/>Please try again or contact our ".customerServices('Mailing List Unsubscribe',$this->CSMail)." Team.";
					$out=msgHandler($out);
				}
				break;
			case 'mlist_manager':
				if($this->USER['access']>=4){
					$out=msgHandler('Manager Content Here...');
				}else{
					$out=msgHandler('Sorry, you don\'t have access to that...');	
				}
				break;
			default:
				$out=msgHandler("Sorry, I don't know what to do...");			
		}
		return $out;
	}
	private function renderLogin(){
		$type=$this->TYPE;
		if($this->USER['access']>0){
			$type='logout';
		}else if($type==='login-reminder'){
			$type='reset';
		}		
		$method=$type.'Form';
		$LGN=$this->SLIM->Login;
		$res=$LGN->$method();
		$p['form']=$res[$type];
		$p['xss']=1234;
		$p['url']=URL.'page/'.$type;
		$p['action']=$type;
		$p['hidden']='';
		
		$frm=file_get_contents(TEMPLATES.'parts/tpl.form_ajax.html');
		$frm=replaceMe($p,$frm);
		if(isset($res['js'])) $frm.=$res['js'];
		return $frm;		
	}
	private function renderResetPassword(){
		$type=$this->TYPE;
		$RST= new reset_password($this->SLIM);
		$rsp=$RST->render();
		$p['form']=$rsp['content'];
		$p['xss']=1234;
		$p['url']=URL.'page/'.$type;
		$p['action']=$type;
		$p['hidden']='';		
		$frm=file_get_contents(TEMPLATES.'parts/tpl.form_ajax.html');
		$frm=replaceMe($p,$frm);
		return $frm;		
	}
	private function renderPostageZones(){
		$LIB= new postagePackage($this->SLIM);
		$content=$LIB->get('zones','public');
		return $content;		
	}
	private function renderViewMedia(){
		$ref=issetCheck($this->ROUTE,2);
		$ML=new Medialist_widget($this->SLIM);
		$content=$ML->get('view',$ref);
		return $content;
	}	
	private function renderViewVideo(){
		$o['id']=issetCheck($this->ROUTE,2);
		$o['viewer']=1;
		$ML=new youtube_data($this->SLIM);
		$content=$ML->render($o);
		if($this->AJAX){
			echo $content;
			die;
		}
		return $content;
	}	
	private function renderViewTour(){
		$o['id']=issetCheck($this->ROUTE,2);
		$o['viewer']=1;
		$ML=new shortcode_tour($this->SLIM);
		$content=$ML->renderTour($o);
		if($this->AJAX){
			echo $content;
			die;
		}
		return $content;
	}	
	private function renderViewDojo(){
		$o=issetCheck($this->ROUTE,2);
		$ML=new shortcode_dojos($this->SLIM);
		$content=$ML->render($o);
		if($this->AJAX){
			echo $content;
			die;
		}
		return $content;
	}	
	private function renderMyDojo(){
		//get Dojo Page
		$dojo_id=0;
		$dojo_lock=issetCheck($this->USER,'dojo_lock',[]);
		$ref=issetCheck($this->ROUTE,2);		
		if($ref && is_numeric($ref)){
			$dojo=$this->SLIM->Options->get('clubs_name',$ref);
			$dojo_id=$ref;
			$ref=$dojo['ShortName'];
		}else if($ref && $ref!==''){
			$dojos=$this->SLIM->Options->get('clubs_all');
			foreach($dojos as $i=>$dojo){
				if($ref===$dojo['ShortName']){
					$dojo_id=$i;
					break;
				}
			}
		}else{
			$dojo_id=(int)issetCheck($this->USER,'DojoID',0);
			if(!$dojo_id){
				if($dojo_lock) $dojo_id=current($dojo_lock);
			}
			if($dojo_id){
				$dojo=$this->SLIM->Options->get('clubs_name',$dojo_id);
				$ref=$dojo['ShortName'];
			}
		}
		//check access
		if($dojo_id && $this->USER['access']<$this->SLIM->AdminLevel){
			if(!in_array($dojo_id,$dojo_lock)) $dojo_id=0;
		}		
		$img=false;
		if($ref==='menu'){
			$links='';
			if($this->USER['access']>=$this->SLIM->AdminLevel){
				$recs=$this->SLIM->db->Items->where('ItemSlug LIKE ?','%-members')->and('ItemStatus','published')->select('ItemID,ItemTitle,ItemSlug');
				if($recs=renderResultsORM($recs)){
					foreach($recs as $i=>$v){
						$url=$this->PERMBASE.$v['ItemSlug'];
						$links.='<button class="button button-lavendar gotoME" data-ref="'.$url.'"><i class="fi-target icon-x3"></i><span class="button-text">'.ucME($v['ItemSlug']).'</span></button>';
					}
				}
			}else{
				foreach($dojo_lock as $i=>$v){
					$dojo=$this->SLIM->Options->get('clubs_name',$v);
					$url=$this->PERMBASE.'my-dojo/'.$dojo['ShortName'];
					$links.='<button class="button button-lavendar gotoME" data-ref="'.$url.'"><i class="fi-target icon-x3"></i><span class="button-text">'.$dojo['ClubName'].'</span></button>';
				}
			}
			$links='<div class="button-group stacked expanded ui-square-2">'.$links.'</div>';
			$content=renderCard_active('My Dojo',$links,$this->SLIM->closer);
		}else if($ref && is_string($ref) && $ref!==''){
			$rec=$this->SLIM->db->Items->where('ItemSlug',$ref.'-members')->and('ItemStatus','published')->select('ItemID,ItemTitle,ItemSlug');
			if($pg=renderResultsORM($rec)){
				$url=$this->PERMBASE.$ref.'-members';
				setSystemResponse($url);
			}else if($dojo_id){
				$title='My Dojo';
				$content=msgHandler('Sorry, we don\'t seem to have a page for your dojo yet...',false,false);
				$img;
			}else{
				$title='Hold Up';
				$content=msgHandler('I don\'t think you are meant to be here... on this page.',false,false) ;
			}
		}else if($dojo_id){
preME('why am I here? '.$dojo_id,2);
			$dojo=$this->SLIM->Options->get('clubs_name',$dojo_id);
			$dojo_name=strtolower($dojo['ShortName']);
			$title=$dojo['ClubName'].' Members';
			$rec=$this->SLIM->db->Items->where('ItemSlug',$dojo_name.'-members');
			if($pg=renderResultsORM($rec)){
				$pg=current($pg);
				$meta=json_decode($pg['ItemShort'],1);
				$img=issetCheck($meta,'page_main_image');
				$content=html_entity_decode($pg['ItemContent']);
			}else{
				$content=msgHandler('Sorry, we don\'t seem to have a page for your dojo yet...',false,false);
			}
		}else{
			$title='Hold Up';
			$content=msgHandler('I don\'t think you are meant to be here... on this page.',false,false) ;
		}	
		if($this->AJAX){
			echo $content;
			die;
		}
		return ['title'=>$title,'content'=>$content,'image'=>$img];
	}	
	private function renderLanguagePage(){
		$ML=new shortcode_language($this->SLIM);
		$content=$ML->renderLang();
		if($this->AJAX){
			echo $content;
			die;
		}
		return $content;
	}	
	private function renderListen(){
		//for open spotify
		$o['ref']=issetCheck($this->ROUTE,2);
		$o['theme']=issetCheck($this->ROUTE,3);
		$content['title']='Listen on Spotify';
		if($o['ref']){
			$url='https://open.spotify.com/embed/track/'.$o['ref'].'?utm_source=generator';
			if($o['theme']==1) $url.='&theme=0';
			$content['content']='<div class="media bg-gbm-dark-blue"><iframe style="height:20rem; width:100%;background:000;" frameBorder="0" allowfullscreen="" allow="autoplay; clipboard-write; encrypted-media; fullscreen; picture-in-picture" src="'.$url.'"></iframe></div>';
		}else{
			$content['content']=msgHandler('Sorry, I don\'t know what you want to listen to...',false,false);
		}
		if($this->AJAX){
			echo renderCard_active($content['title'],$content['content'],$this->SLIM->closer);
			die;
		}
		return $content;
	}	
	private function getFormMailer(){
		$form_id=getRequestVars('mfid');
		$form_action=getRequestVars('mfact');
		$FRM=new public_mailer;
		$out['content']=$FRM->Process($form_action,array('form_id'=>$form_id));
		$out['title']='Mailer';
		$out['form_name']=$FRM->get('title');
		return $out;
	}
	
    private function getResponse(){
		$mainTitle=false;
        $pageTitle = 'Response';
        //check for response
         $content=getRequestVars('faux_response','session');
        if(!$content){
			$mainTitle='Sorry...';
			$content=msgHandler('No response found...');
		}else{
			//nothing
		}
       
 		$mainContent='<div class="panel">'.$content.'</div>';
		$out=array(
			'mainTitle'=>$mainTitle,
			'pageTitle'=>$pageTitle,
			'content'=>$content,
			'mainContent'=>$mainContent
		);
		return $out;
	}
	private function renderVerifyCaptcha(){
		$cap_type=issetCheck($this->ROUTE,2);
		$cap_val=issetCheck($this->ROUTE,3);
		$app=false;
		switch($cap_type){
			case 'mc':
				$cap_val=explode('::',base64_decode($cap_val));
				if(count($cap_val)==2) $app=new mathcap($this->SLIM);
				break;
			case 'wc': 
				$app=new wordcap; 
				break;
		}
		if($app){
			$res=$app->check($cap_val[1],$cap_val[0]);
			jsonResponse($res,1);
		}else{
			echo 'invalid captcha request.';
		}
		die;
	}

    private function getCatalogueSearchResults() {
		$lib=(int)issetCheck($_GET,'itemtype',248);//get catalog type
		if($lib==291){
			$MPE= new cataloguePublic_audio();
		}else{
			$MPE= new cataloguePublic_books();
		}
        $widget = $MPE->Process();
        return $widget;
    }
    
    private function setData() {
        $js = $mainExtra = $mainArticles = $msg= $jqd=$mainSocial=$message=$mainImage=false;
        switch ($this->TYPE) {
           case 'search-results':
                $widget = $this->getCatalogueSearchResults();
                $mainContent = $widget['main_content'];
                $js = $widget['js'];
                $mainTitle = issetCheck($widget,'title','Search Results');
                $pageTitle = 'results';
                break;
            case 'unsubscribe':
            case 'subscribe':
            case 'list_manager':
                $mainTitle = 'Mailinglist: <span class="subheader">'.ucME($this->TYPE).'</span>';
                $pageTitle = strtolower($this->TYPE);
                $content=$this->getMailingList();
 			    $mainContent='<div class="panel">'.$content.'</div>';
 			    break;
			case 'response'://general response from the system
				$_parts=$this->getResponse();
				extract($_parts);
				break;				
 			case 'mailer':
                $content=$this->getFormMailer();
                $mainTitle = 'Contact Mailer:<br/><span class="subheader">'.$content['form_name'].'</span>';
                $pageTitle = $content['title'];
 			    $mainContent=$content['content'];
 			    break;
 			case 'login':
 			case 'logout':
				$tp=($this->TYPE==='login-reminder')?'Reset Password':$this->TYPE;				
                $mainTitle = $this->SLIM->language->getStandard(ucMe($tp));
                $pageTitle = $this->TYPE;
                $mainContent=$this->renderLogin();
                break;
  			case 'login-reminder':
  			case 'reset-password':
				$tp=($this->TYPE==='login-reminder')?'Reset Password':$this->TYPE;				
                $mainTitle = $this->SLIM->language->getStandard(ucMe($tp));
                $pageTitle = $this->TYPE;
                $mainContent=$this->renderResetPassword();
                break;
            case 'paypalgood':
            case 'paypalfail':
            case 'payments':
				if($this->TYPE==='payments'){
					$this->TYPE=($this->ROUTE[3]==='success')?'paypalgood':'paypalfail';
				}
				$_parts=$this->renderPaymentResponse();
				$mainTitle=$_parts['title'];
				$pageTitle = ($this->TYPE==='paypalgood')?'Payment Successful':'Payment Failed';
				$mainContent=$_parts['content'];
				break;
			case 'checkout':
				$_parts=$this->renderCheckout();
				$mainTitle=$_parts['title'];
				$pageTitle = 'Payment Checkout';
				$mainContent=$_parts['content'];
				break;
             case 'verify':
                $mainContent = $this->getVerifyAccount();
                $mainTitle = 'Account Activation';
                $pageTitle = 'verify';
                break;
             case 'postage_zones':
                $mainContent = $this->renderPostageZones();
                $mainTitle = 'Postal Rate Information';
                $pageTitle = 'Postal Rates';
                break;
             case 'media-view':
                $mainContent = $this->renderViewMedia();
                $mainTitle = 'Image';
                $pageTitle = 'Image';
                break;
             case 'video':
                $mainContent = $this->renderViewVideo();
                $mainTitle = 'Video';
                $pageTitle = 'Video';
                break;
             case 'listen':
				$content=$this->renderListen();
                $pageTitle =  $mainTitle = $content['title'];
 			    $mainContent=$content['content'];
                break;
             case 'tours':
                $mainContent = $this->renderViewTour();
                $mainTitle = 'Tour';
                $pageTitle = 'Tour';
                break;
             case 'dojo':
                $mainContent = $this->renderViewDojo();
                $mainTitle = 'Dojo';
                $pageTitle = 'Dojo';
                break;
             case 'lang':
                $mainContent = $this->renderLanguagePage();
                $mainTitle = 'Language';
                $pageTitle = 'Language';
                break;
             case 'captcha':
				$this->renderVerifyCaptcha();
				//should exit before this.
				die();
				break;
			 case 'my-dojo':
				$cnt=$this->renderMyDojo();
                $mainContent = $cnt['content'];
                $mainTitle = $pageTitle = $cnt['title'];
                $mainImage=$cnt['image'];
                break;
            default:
                $mainTitle = 'Oops!';
                $pageTitle = 'error';
                if(!$msg) $msg=' <p class="callout alert radius"><span>Sorry,I could not find the content for "'.$this->TYPE.'"...</span></p>';
 				$cs='<a type="email" href="email:'.$this->CSMail.'">Website Issue</a>';
                $mainContent=$this->getTemplate(array('message'=>$msg ,'ref_no'=>false,'customer_services'=>$cs),'page-faux-error');
                break;
        }
        foreach ($this->PARTS as $key)  $out[$key] = $$key;
        $this->OUTPUT = $out;
    }

}

