<?php
//used for displaying shortcodes to the user
class shortcode_codex{
	private $SLIM;
	private $INDEX=array('textbox','playlist','sidebar','audio','video','image_list','slideshow','misc');
	private $OUTPUT=array('title'=>'oops!','content'=>'<h2 class="text-red">Content not set!</h2>');
	private $AJAX;
	private $TABS=array();
	function __construct($slim=null){
		if(!$slim) throw new Exception(__METHOD__.': no slim object!!');
		$this->SLIM=$slim;
		$this->AJAX=$slim->router->get('ajax');
	}
	function get($what=false,$vars=false){
		if(in_array($what,$this->INDEX)){
			$this->renderCodes($what,$vars);
		}else{
			$this->OUTPUT['title']='No Codes';
			$this->OUTPUT['content']=msgHandler('Sorry, no shortcodes found for ['.$what.']...',false,false);
		}
		return $this->OUTPUT;			
	}
	function render($what=false,$vars=false){
		if($what && !is_array($what)) $what=array($what);
		$js='$("#zurbModal .card").foundation();';
		foreach($what as $index){
			$this->get($index,$vars);
			$this->addTab($index);
			$js.='myFilter("#shortcodes_'.$index.'_filter","#shortcodes_'.$index.' tbody tr");';
		}
		$this->addHelpTab();
		$js.='$("button.copyME").on("click",function(e){
			e.preventDefault();
			var m={message:"",type:false,message_type:""};
			var d=$(this).data("ref");
			$(d)[0].select();
			try {
				var successful = document.execCommand("copy");
				if(successful){
					m.message="Okay, the item has been copied.";
					m.message_type="success";					
				}else{
					m.message="Sorry, the item could not be copied... maybe try manually.";
					m.message_type="info";					
				}
			} catch (err) {
					m.message="Sorry, the copy function is not working on this device.";
					m.message_type="alert";
			}
			console.log(m);
			JQD.utils.renderNotice(m);
			
		});';
		$tabs=$this->renderTabs();
		if($this->AJAX){
			echo renderCard_active('Shortcodes',$tabs,$this->SLIM->closer);
			echo '<script>'.$js.'</script>';
			die;
		}else{
			return renderCard_active('Shortcodes',$tabs);
			$this->SLIM->assets->set('js',$js,'shortlist');
		}
	}
	private function addTab($index){
		$active=($this->TABS)?'':'is-active';
		$this->TABS[$index]=array(
			'nav'=>'<li class="tabs-title '.$active.'"><a href="#panel_'.$index.'">'.$this->OUTPUT['title'].'</a></li>',
			'content'=>'<div class="tabs-panel '.$active.'" id="panel_'.$index.'">'.$this->OUTPUT['content'].'</div>'
		);
	}
	private function addHelpTab(){
		$content='<strong>What is a shortcode?</strong><p class="text-dark-green">A shortcode is a placeholder that allows you to easily add complex items to a page (like forms, embed files, or book lists) that would normally require a lot of code. When the page is displayed, the shortcode gets swapped with the complex content.</p>';
		$content.='<strong>How to use?</strong><p class="text-dark-green">Find the code you want from the available list then copy the code (or click the copy button) and paste it into the document wherever you like.  <span class="text-maroon">The only restriction is that the code should be on a line by itself.</span></p>';
		//$content.='<strong>Modifiers</strong><p class="text-dark-green">Modifiers let you change the display format of the selected list.<br/>The codes are (<span class="text-dark-blue">cards, heroes, table</span>) and are used like this:  [::booklist(sample<span class="text-dark-blue">,table</span>)::]</p>';
		$this->TABS['help']=array(
			'nav'=>'<li class="tabs-title"><a href="#panel_help">Help</a></li>',
			'content'=>'<div class="tabs-panel" id="panel_help">'.$content.'</div>'
		);
	}
	private function renderTabs(){
		$nav=$content='';
		foreach($this->TABS as $i=>$v){
			$nav.=$v['nav'];
			$content.=$v['content'];
		}
		$tabs='<div class="tabs-wrapper"><ul class="tabs" data-tabs id="shortcode-tabs">'.$nav.'</ul><div class="tabs-content" data-tabs-content="shortcode-tabs">'.$content.'</div></div>';
		return $tabs;
	}
	private function renderCodes($index,$args=false){
		$data=$this->getData($index,$args);
		$ct=0;
		$tbl=[];
		$mods=false;
		if(is_array($data)){
			foreach($data as $i=>$v){
				$buts='<button class="button button-navy copyME" data-ref="#sc-'.$i.'"><i class="fi-paperclip"></i> Copy</button>';
				$tbl[]=array(
					'ID'=>$i,
					'Name'=>$v['name'],
					'Code'=>'<input id="sc-'.$i.'" type="text" class="mini" value="[::'.$v['code'].'::]"/>',
					'Controls'=>'<div class="button-group small">'.$buts.'</div>',
				);
				$ct++;
			}
			$o['data']['data']=$tbl;
			$o['before']='filter';
			//modifiers
			switch($index){
				case 'playlist':
					$mods='<strong>list</strong> (fancy list), <strong>table</strong> (table format), <strong>text</strong> (a simple text list)';
					break;
			}
			if($mods) $mods=msgHandler('<em>Available Modifiers:</em> '.$mods,false,false);
			$this->OUTPUT['content']=$mods.dataTable($o,false,'shortcodes');
		}else{
			$this->OUTPUT['content']=msgHandler('No shortcodes found for <strong>'.$index.'</strong>',false,false);
		}
		$this->OUTPUT['title']=ucME($index).' ('.$ct.')';
	}

	private function getData($index,$args=false){
		$data=[];
		switch($index){
			case 'audio':			
				$recs=array(1=>'soundcloud',2=>'spotify');
				if($recs){
					foreach($recs as $i=>$v) $data[$i]=array('name'=>$v,'code'=>$index."($v)");
				}
				break;
			case 'image_list':
				$db=$this->SLIM->db->Options;
				$where['OptionGroup']='widget_medialists';
				$recs=$db->select('id,OptionName,OptionValue')->where($where)->limit(1);
				$recs=renderResultsORM($recs,'id');
				if($recs){
					foreach($recs as $i=>$v){
						$data[$i]=array('name'=>$v['OptionName'],'code'=>$index."({$i})");
					}
				}
				break;
			case 'slideshow':
				$db=$this->SLIM->db->myp_options;
				$where['opt_Name LIKE ?']='slideshow %';
				$where['opt_Active']=1;
				$recs=$db->select('opt_id,opt_Name')->where($where)->order('opt_Name');
				$recs=renderResultsORM($recs,'opt_id');
				if($recs){
					foreach($recs as $i=>$v){
						$n=str_replace('slideshow ','',$v['opt_Name']);
						$data[$i]=array('name'=>$n,'code'=>$index."($i)");
					}
				}
				break;
			case 'video':
				$OB = new youtube_data($this->SLIM);
				$list=$OB->get('shortcode_data');
				if($list){
					foreach($list as $i=>$v){
						$data[$i]=array('name'=>$v['name'],'code'=>$index."({$v['code']})");
					}
				}
				break;
			case 'textbox':
				$OB = new slim_text_box($this->SLIM);
				$list=$OB->get('shortcode_data');
				if($list){
					foreach($list as $i=>$v){
						$data[$i]=array('name'=>$v,'code'=>$index."($i)");
					}
				}
				break;
			case 'playlist':
				$OB = new slim_playlists($this->SLIM);
				$list=$OB->get('shortcode_data');
				if($list){
					foreach($list as $i=>$v){
						$data[$v['code']]=array('name'=>$v['name'],'code'=>$index."({$v['code']},list)");
					}
				}
				break;
			case 'sidebar':
				$db=$this->SLIM->db->Items;
				$where['itm_Type']='sidebar';
				$where['Status']='published';
				$recs=$db->select('ItemID,ItemTitle,ItemSlug')->where($where)->order('ItemTitle');
				$recs=renderResultsORM($recs,'ItemID');
				if($recs){
					foreach($recs as $i=>$v){
						$data[$i]=array('name'=>$v['ItemTitle'],'code'=>$index."({$v['itm_Slug']})");
					}
				}
				break;				
			case 'misc':
				$data[1]=['name'=>'Calendar','code'=>"event"];
				$data[]=['name'=>'Dojos','code'=>"dojos"];
				$data[]=['name'=>'Dojo Map','code'=>"dojomap"];
				$data[]=['name'=>'Events Widget','code'=>"events"];
				$data[]=['name'=>'Homepage Widget','code'=>"homepage"];
				$data[]=['name'=>'My Account Widget','code'=>"myhome"];
				$data[]=['name'=>'Signup Form','code'=>"signup"];
				$data[]=['name'=>'Google Embed','code'=>"google_embed(url,height,title)"];
				$data[]=['name'=>'Jottorm Embed','code'=>"jotform_embed(code,title,height)"];
				break;				
		}
		return $data;			
	}

}
