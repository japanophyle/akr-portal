<?php

class slimMailer {
	private $SLIM;
	private $USER;
	private $OPTIONS;
	private $MAIL;
	private $MAILER;
	private $STATUS; //binary checklist
	private $USE_TEMPLATE=true;
	private $TEMPLATE_PARTS;
	private $MAIL_BOT;
	private $TEMPLATE_FILE='app/app.email_ahk.html';
	private $EMBED_IMAGES=true;
	private $PARTS;

	function __construct($slim=null){
		$this->SLIM=$slim;
		$this->USER=$slim->user;
		$this->OPTIONS=$slim->Options;
		$this->TEMPLATE_PARTS=array('logo','header','content','footer');
		$this->PARTS=$slim->EmailParts;
		$this->MAILER = new PHPMailer(true);
		$this->MAIL_BOT=$slim->Options->get('mailbot');
		$this->MAILER->setFrom($this->MAIL_BOT, 'mailbot');
		$this->MAILER->CharSet="UTF-8";
		$this->MAILER->isHTML(true);
		$this->MAILER->Encoding='base64';
		//smtp settings - see site options
		$smtp=$slim->Options->get('email_smtp_power','value');
		if((int)$smtp){
			$smtp_conf=$slim->Options->get('email_smtp_config','value');
			$smtp_conf=compress($smtp_conf,false);
			if(is_array($smtp_conf)){
				$this->MAILER->isSMTP();
				$this->MAILER->Host = $smtp_conf['host'];
				$this->MAILER->SMTPAuth = $smtp_conf['authenticate'];
				$this->MAILER->Username = $smtp_conf['user'];
				$this->MAILER->Password = $smtp_conf['pass'];
				$this->MAILER->SMTPSecure = $smtp_conf['encryption'];
				$this->MAILER->Port = $smtp_conf['port'];
			}
		}
	}
	
	public function Process($mail=[]){
		if($mail && is_array($mail)){
			try{
				$this->MAIL=$mail;
				$this->MAILER->ClearAllRecipients();
				$this->setEmailTemplate();
				$this->setTo();
				$this->setSubject();
				$this->setMessage();
				$this->setAttachments();
				if($this->checkState()>=7){
					$this->MAILER->send();
					return true;
				}
				if($this->USER['access']==30){
					//continue
				}else{
					return false;
				}
			}catch(Exception $e) {
				if($this->USER['access']==5){
					echo 'Message could not be sent. Mailer Error: ', $this->MAILER->ErrorInfo;
				}else{
					return false;
				}
			}
		}
	}
	
	private function setTo(){
		if($email=issetCheck($this->MAIL,'to')){
			if(is_array($email)){
				$rec=current($email);
				$name=issetCheck($rec,'name');
				$email=issetCheck($rec,'email');
			}else{
				$name=issetCheck($this->MAIL,'name');
			}
			$this->MAILER->addAddress($email, $name);
			$this->STATUS[1]=1;
		}
	}
	private function setSubject(){
		if($subject=issetCheck($this->MAIL,'subject')){
			$this->MAILER->Subject=$subject;
			$this->STATUS[2]=1;
		}
	}
	private function setMessage(){
		if($message=issetCheck($this->MAIL,'message')){
			if(is_array($message)){
				$message=issetCheck($message,1,$message[0]);
			}
			$message=($this->USE_TEMPLATE)?$this->getTemplate():$message;
			if($this->EMBED_IMAGES) $message=$this->embedImages($message);
			$this->MAILER->Body=$message;
			$message=str_replace(array('<br>','<br/>'),' '.PHP_EOL,$message);
			$this->MAILER->AltBody = strip_tags($message);
			$this->STATUS[4]=1;
		}
	}
	private function setAttachments(){
		if($att=issetCheck($this->MAIL,'attachments')){
			foreach($att as $p){
				$this->MAILER->addAttachment($p);
			}
			$this->STATUS[8]=1;
		}
	}
	private function checkState(){
		$state=0;
		foreach($this->STATUS as $i=>$v){
			if($v==1)$state+=(int)$i;
		}
		return $state;
	}

	private function setEmailTemplate(){		
 		$tpl['logo']=issetCheck($this->MAIL,'logo',$this->PARTS['logo']);
		$tpl['header']=issetCheck($this->MAIL,'header',$this->PARTS['header']);
		$tpl['footer']=issetCheck($this->MAIL,'footer',$this->PARTS['footer']);
		if(is_array($this->MAIL['message'])){
			$tpl['content']=issetCheck($this->MAIL['message'],1,'??? no message ???');
		}else{
			$tpl['content']=issetCheck($this->MAIL,'message');
		}
		$this->MAIL['template']=$tpl;
	}
	private function getTemplate(){
		$tpl=file_get_contents(TEMPLATES.$this->TEMPLATE_FILE);
		foreach($this->TEMPLATE_PARTS as $p){
			$v=issetCheck($this->MAIL['template'],$p,'');
			$tpl=str_replace('{::'.$p.'::}',$v,$tpl);
		}
		return $tpl;
	}
	private function embedImages($html){
		/* $pattern='~<img.*?src=.([\/.a-z0-9:_-]+).*?>~si'; */
		$pattern='/< *img[^>]*src *= *["\']?([^"\']*)/i';
		preg_match_all($pattern,$html,$matches);
		$i = 0;
		$paths = false;
		foreach ($matches[1] as $img) {
			$img_old = $img;
			if(strpos($img, "http") ===false) {
				$path=str_replace(URL,'/',$img);
				$path=str_replace('../','/',$path);
				$path = FILE_ROOT.'public/'.$path;
				$path=str_replace('//','/',$path);
				$path=str_replace('//','/',$path);//again to make sure
				if(!file_exists($path)) preME([parse_url($img),$path],2);
				$name= basename($path);
				$content_id = md5($img);
				$this->MAILER->AddEmbeddedImage($path,$content_id,$name,'base64');
				$html = str_replace($img_old,'cid:'.$content_id,$html);
			}
		}
		return $html;		
	}	
}
