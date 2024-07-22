<?php

class slimPDF{
	var $SLIM;
	var $DEBUG=false;
	function __construct($slim){
		$this->SLIM=$slim;
	}
	
	function render($args,$what=false){
		switch($what){
			case 'invoice': $this->renderInvoice($args); break;
			case 'signup': $this->renderSignup($args); break;
			default: $this->renderPage($args);
		}
	}
	function renderPage($args=[]){
		$PDF = new mcTable;
		$PDF->AddPage();
		//header config
		$PDF->PHeader['Client']=$args['user'].'<br/>Reg. Date: '.date('Y-m-d',$args['date']).'<br/>Ref: '.$args['reference_code'].'<br/>'.$args['sub_title'];
		$PDF->PHeader['Metrics']=$PDF->PHeader['Metrics_labels']='';
		$PDF->PHeader['Logo']=array('img'=>'gfx/akr/akr_logo.jpg','text'=>'American Kyudo Renmei<br/>USA');
		$PDF->PHeader['Widths']=array(137,20,35);
		$PDF->PHeader['Aligns']=array('L','R','L');
		$PDF->PHeader['Border']['Color']='#ffffff';
		$PDF->PHeader['Title']=$args['title'];

		$PDF->Table['Border']['Color']='#000000';
		$PDF->Table['TextAlign']='L';
		$PDF->Table['NewLine']="<br/>";
		$PDF->Table['Fill']['Color']='#ffffff';
		$PDF->Table['RowHeader']=array(
			'Widths'=>array(50,100),
			'Aligns'=>array('L', 'L'),
			'Labels'=>array("Item","Value")
		);

		// --render header
		$PDF->LogoHeader();

		// --render rows
		$lb='<br/>';
		$PDF->Fills=$PDF->Table['Fill'];
		$PDF->Borders=$PDF->Table['Border'];		
		$tot=$qty=0;
		$order['personal']=array('name','birthday','age','email','id');
		$order['dojo']=array('dojo','grade','grade_date','form');
		$order['seminar']=array('item','shinsa','ikyf','item2','arrive','depart','price','paid','paid_date','notes');
		foreach($order as $sect=>$section){
			$PDF->SectionHeader($args['html']['section_label_'.$sect]);
			foreach($section as $k){
				$label=issetCheck($args['html'],'member_'.$k.'_label');
				$value=issetCheck($args['html'],'member_'.$k);
				if($label){
					$rv=array($label,$value);
					$PDF->Row($rv);
				}
			}
		}
		if(isset($args['html']['bank_details'])){
			$PDF->SectionHeader('Bank Details');
			$bank=str_replace('&nbsp;',' ',$args['html']['bank_details']);
			$bank=strip_tags($bank,'<br><br/>');
			$rv=array($bank);
			$PDF->Row($rv);
		}
		if($this->DEBUG) $args['render_type']='I';
		if($args['render_type']==='F'||$args['render_type']==='D'){
			$PDF->Output($args['docname'],$args['render_type']);
		}else{
			$PDF->Output();
		}
		if($this->DEBUG) die;
	}
	function renderInvoice($args){
		$PDF = new mcTable;
		$PDF->AddPage();
		//header config
		$PDF->PHeader['Client']=$args['member_info'];
		$PDF->PHeader['Metrics']=$args['invoice_no'].'<br/>'.$args['invoice_date'].'<br/>'.$args['status'];
		$PDF->PHeader['Logo']=array('img'=>'gfx/akr/akr_logo.jpg','text'=>'American Kyudo Renmei<br/>USA');
		$PDF->PHeader['Widths']=array(137,20,35);
		$PDF->PHeader['Aligns']=array('L','R','L');
		$PDF->PHeader['Border']['Color']='#ffffff';
		$PDF->PHeader['Title']='INVOICE';
		//table config
		$PDF->Table['Border']['Color']='#000000';
		$PDF->Table['TextAlign']='L';
		$PDF->Table['NewLine']="<br/>";
		$PDF->Table['Fill']['Color']='#ffffff';
		$PDF->Table['RowHeader']=array(
			'Widths'=>array(142,15,15,20),
			'Aligns'=>array('L', 'C', 'R','R'),
			'Labels'=>array("Item","Qty.","Price","Total")
		);

		// --render header
		$PDF->LogoHeader();

		// --render rows
		$PDF->RowHeader();
		$lb='<br/>';
		$PDF->Fills=$PDF->Table['Fill'];
		$PDF->Borders=$PDF->Table['Border'];		
		$tot=$qty=0;
		foreach($args['rows'] as $i=>$v){
			$PDF->Row($v);
		}
		// --render totals
		$PDF->ln(2);
		$PDF->SetFont('Arial','B',10);
		$PDF->Fills['Color']='#e5e5e5';
		$PDF->Row(array('Totals',$args['qty'],'',$args['total']));
		$PDF->Row(array('Paid','','',$args['paid']));
		$PDF->Row(array('Balance','','',strip_tags($args['balance'])));

		if($args['render']==='F'||$args['render']==='D'){
			$PDF->Output($args['docname'],$args['render']);
		}else{
			$PDF->Output();
		}
	}
	
	function renderSignup($args){
		$PDF = new mcTable;
		$PDF->AddPage();
		//header config
		$PDF->PHeader['Client']=$args['user'].'<br/>Date: '.$args['date'].'<br/>Ref: '.$args['reference_code'].'<br/>Member ID: '.$args['member_id'].' / User ID: '.$args['user_id'].'<br/>'.$args['sub_title'];
		$PDF->PHeader['Metrics']=$PDF->PHeader['Metrics_labels']='';
		$PDF->PHeader['Logo']=array('img'=>'gfx/akr/akr_logo.jpg','text'=>'American Kyudo Renmei<br/>USA');
		$PDF->PHeader['Widths']=array(137,20,35);
		$PDF->PHeader['Aligns']=array('L','R','L');
		$PDF->PHeader['Border']['Color']='#ffffff';
		$PDF->PHeader['Title']=$args['title'];

		$PDF->Table['Border']['Color']='#000000';
		$PDF->Table['TextAlign']='L';
		$PDF->Table['NewLine']="<br/>";
		$PDF->Table['Fill']['Color']='#ffffff';
		$PDF->Table['RowHeader']=array(
			'Widths'=>array(50,100),
			'Aligns'=>array('L', 'L'),
			'Labels'=>array("Item","Value")
		);

		// --render header
		$PDF->LogoHeader(20);

		// --render rows
		$lb='<br/>';
		$PDF->Fills=$PDF->Table['Fill'];
		$PDF->Borders=$PDF->Table['Border'];
		$tot=$qty=0;
		$order['account']=array('username','password');
		$order['personal_reg']=array('first_name','last_name','dob','gender','email','phone','language','address','town','city','country','post_code');
		$order['dojo']=array('dojo','grade','grade_date','zasha');
		$order['grade_history']=array(1,2,3,4,5,6,7,8,9,10);

		foreach($order as $sect=>$section){
			$PDF->SectionHeader($args['html']['section_label_'.$sect]);
			if($sect==='grade_history'){
				foreach($section as $k){
					$label=issetCheck($args['html'][$sect],'member_exam'.$k.'_label');
					$value=issetCheck($args['html'][$sect],'member_exam'.$k.'_date');
					$op=issetCheck($args['html'][$sect],'member_exam'.$k.'_options');
					if(trim($value)!==''){
						$rv=array($label,$op.' - '.$value);
						$PDF->Row($rv);
					}
				}
			}else{
				foreach($section as $k){				
					$label=issetCheck($args['html'][$sect],'member_'.$k.'_label');
					if($label){
						$value=issetCheck($args['html'][$sect],'member_'.$k);
						$rv=array($label,$value);
						$PDF->Row($rv);
					}
				}
			}
		}
		if(isset($args['html']['extra_info'])){
			$PDF->SectionHeader($args['html']['section_label_extra_info']);
			$bank=str_replace('&nbsp;',' ',$args['html']['extra_info']);
			$bank=strip_tags($bank,'<br><br/>');
			$rv=array($bank);
			$PDF->Row($rv);
		}
		if($this->DEBUG) $args['render_type']='I';
		if($args['render_type']==='F'||$args['render_type']==='D'){
			$PDF->Output($args['docname'],$args['render_type']);
		}else{
			$PDF->Output();
		}
		if($this->DEBUG) die;
	}
	
}
