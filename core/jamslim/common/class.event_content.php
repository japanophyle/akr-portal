<?php

class event_content{
	private $SLIM;
	private $CONTENT_PARTS;
	private $LANG;
	private $LANGS;
	private $USER;	
	private $EVENT_ID;
	private $EVENT_REC;	
	private $EVENT_CONTENTS;
	private $AJAX;
	public $ADMIN;
	public $LIB;
	function __construct($slim){
		$this->SLIM=$slim;
		$this->USER=$slim->user;
		$this->LANG=$slim->language->get('_LANG');
		$this->LANGS=$slim->language->get('_LANGS');
		$this->AJAX=$slim->router->get('ajax');
		$this->LIB=$slim->EventsLib;
		$this->CONTENT_PARTS=array(
			'content_above','invitation','date','location','participants','teacher','program','seminar_documents','options','accommodation','fee','payment','cancellation_and_reimbursement','getting_there','other_information','content_below'
		);
	}
	
	public function loadEvent($arg){
		if(is_numeric($arg)){
			$this->EVENT_ID=(int)$arg;
			$this->setEvent();
		}else if(is_array($arg)){
			$this->EVENT_ID=$arg['EventID'];
			$this->EVENT_REC=$arg;
		}
		$this->setEventContents();
	}
	private function setEventContents($args=false){
		$test=false;
		if(is_array($args)){//from class.EventItem
			if($attr=issetCheck($args,'attr_ar')){
				$value=$attr['value'];
			}
		}else{
			$value=issetCheck($this->EVENT_REC,'EventContent');
		}
		if(is_string($value) && $value!==''){
			$test=compress($value,false);
		}
		if(!is_array($test)){
			//return default record
			$test = $this->getPart('all',false,true);
		}
		$this->EVENT_CONTENTS=$test;
	}		
	public function getContent($what=false){
		if(!$what) $what='all';
		$title=issetCheck($this->EVENT_REC,'EventName');
		$parts=$this->getPart($what);
		$free=array('content_above'=>'','content_below'=>'');
		$out='';
		if($what!=='all'){
			if($parts){
				$cells='<div class="cell medium-2 text-purple">'.$parts['label'].'</div><div class="cell medium-auto">'.$parts['value'].'</div>';
				$out.='<div class="grid-x grid-padding-x ulME">'.$cells.'</div>';
			}
		}else{
			foreach($parts as $i=>$v){
				switch($i){
					case 'invitation':
						$value=$title;
						break;
					case 'location':
						$tmp=$this->SLIM->options->get('location_name',$this->EVENT_REC['EventAddress'],$v['value']);
						$value=$tmp;
						break;
					default:
						$value=$v['value'];
				}
				$label=($this->ADMIN)?'<button title="click to edit this section" class="button small button-lavendar expanded loadME" data-ref="'.URL.'admin/events/edit_contents/'.$this->EVENT_ID.'/'.$i.'">'.$v['label'].'</button>':$v['label'];
				$power=$this->checkPower($i);
				if($this->ADMIN){
					$class=(!$power)?'bg-light-gray':'bg-green';
					$cells='<div class="cell medium-2 text-purple">'.$label.'</div><div class="cell medium-auto '.$class.'">'.$value.'</div>';
				}else{
					if($i==='content_above'||$i==='content_below'){
						$free[$i]='<div class="grid-x grid-padding-x"><div class="cell">'.$value.'</div></div>';
						$cells=false;
					}else{
						$cells='<div class="cell medium-2 text-purple">'.$label.'</div><div class="cell medium-auto">'.$value.'</div>';
					}
					if(!$power) $cells=false;				
				}
				if($cells)	$out.='<div class="grid-x grid-padding-x ulME">'.$cells.'</div>';
			}
		}
		if($this->AJAX){
			return $out;
		}else{
			$tmp=$free['content_above'].$out.$free['content_below'];
			return (trim(strip_tags($tmp))=='')? '<hr/>': '<div class="callout">'.$tmp.'</div>';
		}		
	}
	private function checkPower($what=false){
		$test=issetCheck($this->EVENT_CONTENTS,$what);
		return (int)issetCheck($test,'power');
	}
	public function editContent($what=false){
		if($what){
			$data=$this->getPart($what,true,true);
			switch($what){
				case 'invitation':
					$value=issetCheck($this->EVENT_REC,'EventName');
					break;
				case 'location':
					$tmp=$this->SLIM->options->get('location_name',$this->EVENT_REC['EventAddress']);
					$value=$tmp;
					break;
				default: 
					$value=false;
			}
			//render tabs
			$title='Event Content: <span class="text-gray">'.ucME($what).'</span>';
			$content=$this->renderTabs($data,$value);
			$footer='<div class="button-group small expanded"><button class="button loadME button-teal" data-ref="'.URL.'admin/events/edit/'.$this->EVENT_ID.'"><i class="fi-arrow-left"></i> Edit Event</button><button class="button loadME button-lavendar" data-ref="'.URL.'admin/events/edit_contents/'.$this->EVENT_ID.'"><i class="fi-list"></i> Content Sections</button><button class="button button-olive" type="submit"><i class="fi-check"></i> Update</button></div>';
			$content='<form class="ajaxForm" id="event-contents" method="post" action="'.URL.'admin/events/edit_contents/'.$this->EVENT_ID.'/'.$what.'"><input type="hidden" name="action" value="save_event_content"/><input type="hidden" name="id" value="'.$this->EVENT_ID.'"/><input type="hidden" name="section" value="'.$what.'"/>'.$content.$footer.'</form>';
		}else{
			$title='Event Contents';
			$m=msgHandler('Click the titles to edit the sections content. Disabled sections have a grey background.','secondary',false);
			$content='<div class="tabs-content">'.$m.$this->getContent().'</div>';
			$content.='<div class="modal-footer"><div class="text-right"><button class="button loadME button-teal" data-ref="'.URL.'admin/events/edit/'.$this->EVENT_ID.'"><i class="fi-arrow-left"></i> Edit Event</button></div></div>';
		}
		$content='<div class="modal-body">'.$content.'</div>';
		return array('title'=>$title,'content'=>$content,'button'=>$this->SLIM->closer);
	}
	public function saveContent($post=false){
		$r=array('state'=>500,'type'=>'message','message_type'=>'alert');
		if(is_array($post)){
			$section=issetCheck($post,'section');
			if(in_array($section,$this->CONTENT_PARTS)){
				$rec=array('de'=>$post['lang']['de'],'fr'=>$post['lang']['fr'],'en'=>$post['lang']['en'],'power'=>(int)$post['power']);
				$this->EVENT_CONTENTS[$section]=$rec;
				$data=compress($this->EVENT_CONTENTS);
				$update=array('EventContent'=>$data);
				$DB=$this->SLIM->db->Events();
				$res=$DB->where('EventID',$this->EVENT_ID);
				$chk=$res->update($update);
				if($chk){
					$r['status']=200;
					$r['message']='Okay, the "'.$section.'" section has been updated.';
					$r['message_type']='success';
				}else{
					$r['status']=201;
					$r['message']='Okay, but nothing was updated...';
					$r['message_type']='primary';
				}
			}else{
				$r['message']='Sorry, the section is not valid...';
			}
		}else{
			$r['message']='Sorry, no details received...';
		}
		jsonResponse($r);
		die;
	}
	private function renderTabs($data,$value=false){
		$helps=array(
			'content_above'=>'Content entered here will appear before the main details.',
			'content_below'=>'Content entered here will appear after the main details.',
			'date'=>'If this is blank, the date will be taken from the event settings',
			'location'=>'If this is blank, the location will be taken from the event settings',
			'invitation'=>'If this is blank, the events title will be used.',		
		);
		$help=issetCheck($helps,$data['key']);
		if($help) $help=msgHandler('<i class="fi-info"></i> '.$help,'primary',false);
		$nav=$panels='';
		$active='is-active';
		$tab_id='event-contents';
		$ct=0;
		foreach($data['value'] as $i=>$v){
			if($i==='power'){
				$help='';
				$class=((int)$v)?'olive':'maroon';
				$selected=((int)$v)?'selected':'';
				$nav.='<li class="tabs-title '.$active.'"><a href="#panel_'.$i.'" aria-selected="'.$active.'"><span class="text-'.$class.'">Power</span></a></li>';
				$opts='<option value="0">Off</option><option value="1" '.$selected.'>On</option>';
				$tmp='<label>Power: <em class="text-olive">This controls the visibility of this section on the public site.</em><select name="power">'.$opts.'</select></label>';
			}else{
				$nav.='<li class="tabs-title '.$active.'"><a href="#panel_'.$i.'" aria-selected="'.$active.'">'.$this->LANGS[$i].'</a></li>';
				$tmp='<textarea name="lang['.$i.']" id="edit-'.$i.'" class="qedit" >'.$v.'</textarea>';
			}

			$panels.='<div class="tabs-panel '.$active.'" id="panel_'.$i.'">'.$help.$tmp.'</div>';
			$active='';
			$ct++;
		}
		$tabs='<ul class="tabs" data-tabs id="'.$tab_id.'-tabs">'.$nav.'</ul><div class="tabs-content" data-tabs-content="'.$tab_id.'-tabs">'.$panels.'</div>';
		if($this->AJAX) $tabs.='<script>jQuery("#'.$tab_id.'-tabs").foundation();JQD.ext.initEditor(".modal-body .qedit");</script>';
		return $tabs;
	}

	public function getPart($what,$defaults=true,$data=false){
		switch($what){
			case 'all':
				foreach($this->CONTENT_PARTS as $p){
					if($data){
						$k=($defaults)?$p:'x';
						$val=$this->getValue($k,$defaults,$data);
					}else{
						$val=($defaults)?$this->getValue($p,$defaults,$data):'';
					}
					$out[$p]=array('key'=>$p,'label'=>$this->SLIM->language->getStandard($p),'value'=>$val);
				}
				break;
			default:
				if($what){
					$p=in_array($what,$this->CONTENT_PARTS);
					if($data){
						$k=($defaults)?$what:'x';
						$val=$this->getValue($k,$defaults,$data);
					}else{
						$val=($defaults)?$this->getValue($what,$defaults,$data):'';
					}
					$out=array('key'=>$what,'label'=>$this->SLIM->language->getStandard($what),'value'=>$val);
				}else{
					$out=false;
				}
		}
		return $out;
	}
	private function getValue($what,$defaults=false,$data=false){
		$value=false;
		//check event record
		$values=issetCheck($this->EVENT_CONTENTS,$what);
		if($values){
			if($data){
				if(!array_key_exists('de',$values)){
					$values=$this->getDefault($what,$data);
				}
				$value=$values;
			}else{
				$value=issetCheck($values,$this->LANG);
			}
		}
		//det default
		if((!$value || $value==='') && $defaults){
			$value=$this->getDefault($what,$data);
		}
		return $value;
	}
	private function getDefault($what,$data=false){
		$out=array('de'=>'','fr'=>'','en'=>'','power'=>0);	
		switch($what){
			case 'participants':
				$out['de']='<p>Alle Aktivmitglieder des SVK</p>';
				$out['fr']='<p>Membres actifs de l’AHK</p>';
				$out['en']='<p>All active members of the AHK</p>';
				break;
			case 'teacher':
				$out['de']='<p>Shogo SVK</p>';
				$out['fr']='<p>Shogo AHK</p>';
				$out['en']='<p>Shogo AHK</p>';
				break;
			case 'seminar_documents':
				$out['de']='<p>1. Seminarregeln - Rules for Participants (<a href="/fichiers/1. Rules for Participants.pdf">PDF/41KB</a>)<br>2. Anweisungen zu stehenden Form - How to perform rissha (<a href="/fichiers/2. How to perform rissha.pdf" target="_blank">PDF/72KB</a>)</p>';
				$out['fr']='<p>1. Règles de séminaire pour les participants (<a href="/fichiers/1. Rules for Participants.pdf" target="_blank">PDF/41KB</a>)<br>2. Instructions pour l’exécution des sharei en rissha - How to perform rissha (<a href="/fichiers/2. How to perform rissha.pdf" target="_blank">PDF/72KB</a>)</p>';
				$out['en']='<p>1. Rules for Participants (<a href="/fichiers/1. Rules for Participants.pdf" target="_blank">PDF/41KB</a>)<br>2. Instructions on how to perform rissha (<a href="/fichiers/2. How to perform rissha.pdf" target="_blank">PDF/72KB</a>)</p>';
				break;
			case 'options':
				$out['de']='<p>Seminarteilnehmer/innen können aus folgenden Optionen wählen:<br><br>Option 1: Seminar ohne Unterkunft und Verpflegung <br>Option 2: Seminar mit Unterkunft und Verpflegung (Vollpension) im Doppelzimmer <br>Option 3: Seminar mit Unterkunft und Verpflegung (Vollpension) im Einzelzimmer <br><br>Teilnahme nur an einem Tag möglich. Einschreibung jeweils vor Seminarbeginn.</p>';
				$out['fr']='<p>Les participants peuvent opter pour les options suivantes :<br>Option 1: séminaire sans hébergement, ni repas <br>Option 2: séminaire avec hébergement et repas (pension complète) chambre double <br>Option 3: séminaire avec hébergement et repas (pension complète) chambre individuelle</p><p>La participation à un seul jour est également possible. A préciser lors de l’inscription.</p>';
				$out['en']='<p>Participants can opt for the following options:<br>Option 1: seminar without accommodation or meals <br>Option 2: seminar with accommodation and meals (full board) double room <br>Option 3: seminar with accommodation and meals (full board) single room</p><p>Participation on a single day is also possible and should be specified during registration.</p>';
				break;
			case 'accommodation':
				$out['de']='<p>Gilt nur für Option 2 und Option 3: Die Unterbringung der Seminarteilnehmer/innen erfolgt im Kurs- und Sportzentrum Magglingen. Für die Seminarteilnehmer/innen ist überwiegend eine Unterbringung in Doppelzimmern vorgesehen, da nur eine knappe Zahl von Einzelzimmern zur Verfügung steht. Vergabe der Einzelzimmer auf Anfrage&nbsp;nach Eingang der Anmeldung.</p>';
				$out['fr']='<p>Valable que pour les options 2 et 3. L’attribution des chambres est dépendante des disponibilités de l’OFSPO. Les réservations sont faites sur une base de chambre double. Sur demande, et en fonction des disponibilités, un nombre restreint de chambres individuelles peuvent être attribuées.</p>';
				$out['en']='<p>Valid only for options 2 and 3. The allocation of rooms is dependent on the availability of OFSPO. Reservations are made on a double room basis. On request, and depending on availability, a limited number of single rooms can be allocated.</p>';
				break;
			case 'fee':
				$out['de']='<p>Option 1: - (Seminar ohne Unterkunft und Verpflegung)<br>Option 2: CHF 110.- (Seminar mit Unterkunft und Verpflegung im Doppelzimmer. Bitte Zimmerpartner angeben.)<br>Option 3: CHF 180.- (Seminar mit Unterkunft und Verpflegung&nbsp;im Einzelzimmer)</p><p>Die einzelnen Gebühren verstehen sich als Pauschalpreise. Bei eintägiger Teilnahme gibt es keine Vergünstigung. Bei der Online-Anmeldung bitte die entsprechenden Option anklicken und den Anreisetag und Abreisetag&nbsp;auswählen.&nbsp;<br><br>Einzahlung auf:<br>PC-Konto: 90-112449-9<br>ASSOCIATION HELVETIQUE DE KYUDO, 2500 BIEL/BIENNE<br>oder<br>IBAN: CH82 0900 0000 9011 2449 9<br>SWIFT/BIC: POFICHBEXXX<br>Bank: Swiss Post,&nbsp; PostFinance Nordring 8,&nbsp; 3030 Bern</p>';
				$out['fr']='<p>Option 1: - (séminaire sans hébergement, ni repas)<br>Option 2: CHF 110.- (séminaire avec hébergement chambre double et repas. Préciser le voisin de chambre)<br>Option 3: CHF 180.- (séminaire avec hébergement chambre individuelle et repas)</p><p>Le prix est considéré comme une taxe de base et aucun rabais pour participation à une seule journée n’est possible. Lors de l’inscription, prière de compléter précisément le formulaire en indiquant les jours d’arrivée et de départ. </p><p>Paiement sur:<br>Compte postal: 90-112449-9<br>ASSOCIATION HELVETIQUE DE KYUDO, 2500 BIEL/BIENNE<br>ou<br>IBAN: CH82 0900 0000 9011 2449 9<br>SWIFT/BIC: POFICHBEXXX<br>Banque: Swiss Post,&nbsp; PostFinance Nordring 8,&nbsp; 3030 Bern</p>';
				$out['en']='<p>Option 1: - (seminar without accommodation or meals)<br>Option 2: CHF 110.- (seminar with double room accommodation and meals. Specify who you are sharing with)<br>Option 3: CHF 180.- (seminar with single room accommodation and meals)</p><p>The price is considered a basic fee and no discount for participation in a single day is possible. When registering, please fill in the form and indicate the days of arrival and departure.</p><p>Payment:<br>Postal Code: 90-112449-9<br>ASSOCIATION HELVETIQUE DE KYUDO, 2500 BIEL/BIENNE<br>or<br>IBAN: CH82 0900 0000 9011 2449 9<br>SWIFT/BIC: POFICHBEXXX<br>Bank: Swiss Post,&nbsp; PostFinance Nordring 8,&nbsp; 3030 Bern</p>';
				break;
			case 'payment':
				$out['de']='<p>Nach Anmeldeschluss der Online Anmeldung muss der Betrag innerhalb einer Woche überwiesen werden. Ist dies nicht der Fall, wird die Zimmerreservation automatisch aufgehoben und das Zimmer gemäss Warteliste weiter vergeben.</p>';
				$out['fr']='<p>Le montant correspondant à la réservation de chambre doit être versé dans un délai d’une semaine après la date limite d’inscription on line. Si ce n’est pas le cas, la réservation de chambre sera automatiquement annulée et proposée au premier des viennent ensuite de la liste d’attente.</p>';
				$out['en']='<p>The amount corresponding to the room reservation must be paid within one week after the online registration deadline. If this is not the case, the room reservation will be automatically canceled and offered at the first of the next come from the waiting list.</p>';
				break;
			case 'cancellation_and_reimbursement':
				$out['de']='<p>Wir machen darauf aufmerksam, dass die Anmeldung verbindlich ist. Insbesondere wird mit der on-line Anmeldung die Seminargebühr fällig. Nach Anmeldeschluss der Online Anmeldung können Abmeldungen /Absagen inkl. Rückvergütung nicht mehr berücksichtigt werden.</p>';
				$out['fr']='<p>Nous attirons l’attention des participants sur le fait que l\’inscription fait foi et revêt un caractère contraignant. L’inscription en ligne implique de s’acquitter des frais de séminaire en temps dû. Seuls les désistements annoncés jusqu’à la date de l\'inscription online donnent droit au remboursement des frais.</p>';
				$out['en']='<p>We point out that the registration is binding. In particular, with the on-line registration, the seminar fee is due. After the registration deadline for the online registration, cancellations / cancellations including refund can no longer be considered.</p>';
				break;
			case 'getting_there':
				$out['de']='<p>ÖV: Ab Bahnhof Biel/Bienne - 10 Minuten Fussweg zur&nbsp;Talstation «funic». Die Seilbahn «funic» führt in weiteren&nbsp;10 Minuten nach Magglingen. Das BASPO befindet sich direkt neben der Station.<br>Auto:&nbsp;Siehe <a href="/fichiers/situationsflyerscreen.pdf">Wegbeschreibung</a>&nbsp;(PDF/386KB) <br>Seminarteilnehmer/innen können beim Parkplatz eine Tageskarte für den Parkplatz der Sport-Toto-Halle lösen. Der Bezug einer Tageskarte ist obligatorisch. Die Parkplätze werden kontrolliert. Allfällige Bussen werden vom Verband nicht übernommen.</p>';
				$out['fr']='<p>Transports public: De la gare de Bienne, à pied en 10 minutes jusqu’à la station de départ du funiculaire Bienne / Macolin. Le trajet Bienne / Macolin a une durée de 10 minutes. L’OFSPO se trouve à côté de la station supérieure.<br>Voiture privée&nbsp;Voir <a href="/fichiers/situationsflyerscreen.pdf">l\'iitinéraire</a>&nbsp;(PDF/386KB) <br>Les participants peuvent parquer leur voiture sur les places de parc à côté de la halle Sport-Toto moyennant une carte journalière obligatoire à disposition à l’automate. Des contrôles sont régulièrement effectués et les éventuelles amendes ne sont pas prises en charge par l’AHK.</p>';
				$out['en']='<p>Public transport: From Biel station, walk 10 minutes to the funicular station Bienne / Macolin. The travel time is 10 minutes. The FOSPO is next to the upper station<br>By Car :<a href="/fichiers/situationsflyerscreen.pdf">route</a>&nbsp;(PDF/386KB)<br>Participants can park their car on the parking spaces next to the Sport-Toto hall with a mandatory day pass at the automaton. Checks are regularly carried out and any fines are not covered by the AHK.</p>';
				break;
			case 'other_information':
				$out['de']='<p><strong>On-line Anmeldung bis zum 27. Januar 2019</strong></p><p><strong>Alle Einzelzimmer sind bereits belegt. Es können nur noch Doppelzimmer gebucht werden (10.12.2018, 19.30 Uhr).</strong></p><p>Bis zum 25.2. ist nur noch eine Anmeldung ohne Unterkunft und Verpflegung über doodle möglich.</p><p>Weitere Auskünfte und Informationen bei <a href="info@kyudo.ch">info@kyudo.ch</a></p>';
				$out['fr']='<p><strong>On-line inscription jusqu\'au 27 janvier</strong></p><p><strong>Toutes les chambres individuelles sont actuellement réservations. Il ne reste plus que des chambres doubles (10/12/2018, 19h30).</strong></p><p>Jusqu’au 25 fevrier, il est toujours possible de s’inscrire avec doodle, mais sans hébergement, ni repas.</p>';
				$out['en']='<p><strong>On-line registration until 27 janvier</strong></p><p><strong>All single rooms are already occupied. Only double rooms can be booked (10/12/2018, 19h30).</strong></p><p>Until February 25, it is still possible to register with doodle, but without accommodation or meals</p>';
				break;
			case 'program':
				$out['de']='<dl><dt>Date 1</dt><dd>18h00-19h30 Sample 1</dd><dd>18h00 Sample 2</dd></dl><dl><dl><dt>Date 2</dt><dd>108h00 Sample 3</dd><dd>09h30 Sample 4</dd></dl><dl>';
				$out['fr']='<dl><dt>Date 1</dt><dd>18h00-19h30 Sample 1</dd><dd>18h00 Sample 2</dd></dl><dl><dl><dt>Date 2</dt><dd>108h00 Sample 3</dd><dd>09h30 Sample 4</dd></dl><dl>';
				$out['en']='<dl><dt>Date 1</dt><dd>18h00-19h30 Sample 1</dd><dd>18h00 Sample 2</dd></dl><dl><dl><dt>Date 2</dt><dd>108h00 Sample 3</dd><dd>09h30 Sample 4</dd></dl><dl>';
				break;
			case 'date':
				$start=$this->SLIM->language_dates->langDate($this->EVENT_REC['EventDate']);//validDate($rec['EventDate'],'D jS F Y');
				$end=$this->SLIM->language_dates->langDate($this->EVENT_REC['EventDuration']);//validDate($rec['EventDuration'],'D jS F Y');
				$out[$this->LANG]=$start;
				if($end && $end!=='') $out[$this->LANG].=' - '.$end;
				break;
			default:
							
		}
		return ($data)?$out:issetCheck($out,$this->LANG,'');
	}
	private function setEvent(){
		$event=[];
		if($this->EVENT_ID){
			$event=$this->LIB->get('event',$this->EVENT_ID);
		}
		$this->EVENT_REC=$event;
	}	
}
