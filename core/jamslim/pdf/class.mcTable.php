<?php
class mcTable extends FPDF{
	var $widths=array();
	var $aligns=array();
	var $Borders=array();
	var $Fills=array();
	var $NewLine=false;
	var $MaxHeight=50;
	var $MaxWidth=150;// for single column rows
	var $DefAlign='J';
	var $useiconv=true;
	
	var $PHeader=array(
		'Border'=>array('Width'=>0.1,'Color'=>false),
		'Fill'=>array('Color'=>false),
		'TextAlign'=>'J',
		'Columns'=>4,
		'Client'=>'This is a very long<br/>This is a very long<br/>This is a very long<br/>ok',
		'Metrics_labels'=>'Ref.<br/>Date:<br/>Status:<br/>Paid Date:',
		'Metrics'=>'This is a very long<br/>This is a very long<br/>This is a very long<br/>ok',
		'Logo'=>array('img'=>'gfx/akr/akr_logo_badge_email.png','text'=>'AKR<br/>USA'),
		'Widths'=>array(80,15,65,32),
		'Aligns'=>array('L','L','L','R'),
		'NewLine'=>PHP_EOL,
	);

	var $Table = array(
		'Border'=>array('Width'=>0.1,'Color'=>false),
		'Fill'=>array('Color'=>false),
		'TextAlign'=>'J',
		'Columns'=>5,
		'Rows'=>5,
		'NewLine'=>PHP_EOL,
		'RowHeader'=>false,
		'Widths'=>array(80,80,40),
		'Aligns'=>array('L','L','C')
	);

	public function SetColumns($args){
		$this->widths=$args;
	}
	public function SetAligns($args){
		$this->aligns=$args;
	}
	function Hex2RGB($couleur = "#000000"){
		$R = substr($couleur, 1, 2);
		$rouge = hexdec($R);
		$V = substr($couleur, 3, 2);
		$vert = hexdec($V);
		$B = substr($couleur, 5, 2);
		$bleu = hexdec($B);
		$tbl_couleur = array();
		$tbl_couleur['R']=$rouge;
		$tbl_couleur['G']=$vert;
		$tbl_couleur['B']=$bleu;
		return $tbl_couleur;
	}

	//conversion pixel -> millimeter at 72 dpi
	function px2mm($px){
		return $px*25.4/72;
	}

	function txtentities($html){
		$trans = get_html_translation_table(HTML_ENTITIES);
		$trans = array_flip($trans);
		return strtr($html, $trans);
	}
	function convert($s) {
		if ($this->useiconv) {
			return iconv('UTF-8','windows-1252',$s); 
		}else{ 
			return $s;
		}
	}
	function Row($_xdata,$rh=5){
		if(!$rh) $rh=5;
		$xnb=0;$xdata=[];$cols=0;
		foreach($_xdata as $i=>$v){
			$nb=$this->NbLines($v,$i);
			$chk=$nb['rows'];
			if($chk>$xnb) $xnb=$chk;
			$xdata[$i]=$nb['str'];
			$cols++;
		}
		$xh=($xnb*$rh);
		if($xh>$this->MaxHeight) $xh=$this->MaxHeight;
		$this->CheckPageBreak($xh);
		foreach($xdata as $i=>$v){
			$xw=($cols===1)?$this->MaxWidth:$this->widths[$i];
			$xx=$this->GetX();
			$xy=$this->GetY();
			
			$color=issetCheck($this->Fills,'Color');
			if($color==='') $color=false;
			if ($this->Borders['Width']>0||$color){
				$xstyle=false;
				$this->SetLineWidth($this->Borders['Width']);
				if($this->Borders['Color']){
					$RGB = $this->Hex2RGB($this->Borders['Color']);
					$this->SetDrawColor($RGB["R"],$RGB["G"],$RGB["B"]);
					$xstyle.='D';
				}
				if($this->Fills['Color']){
					$RGB = $this->Hex2RGB($this->Fills['Color']);
					$this->SetFillColor($RGB["R"],$RGB["G"],$RGB["B"]);
					$xstyle.='F';
				}
				$this->Rect($xx,$xy,$xw,$xh,$xstyle);
			}
			$RGB = $this->Hex2RGB('#000000');
			$this->SetTextColor($RGB["R"],$RGB["G"],$RGB["B"]);
			$align=issetCheck($this->aligns,$i,$this->DefAlign);
			$this->MultiCell($xw,$rh,$v,0,$align);
			$this->SetXY($xx+$xw,$xy);
		}
		$this->Ln($xh);
	}
	
	function CheckPageBreak($xh){
		$chk=$this->GetY();
		$chk+=$xh;
		if($chk > $this->PageBreakTrigger) {
			$this->AddPage($this->CurOrientation);
			$this->RowHeader();
		}
	}

	function NbLines($xtxt,$col){
		$arr= preg_split('/<br[^>]*>/i', $xtxt);
		if(is_array($arr) && !empty($arr)){
			$txt=implode("\n",$arr);
			$txt=$this->convert($txt);
			$ct=count($arr);
			$l=strlen($txt);
			$w=$this->widths[$col];
			if($ct==1 && $l>$w) {
				$ct=ceil(($l/$w)*2)/2;
			}
		}else{
			$l=strlen($xtxt);
			$w=$this->widths[$col];
			$txt=$xtxt;
			$ct=($l>$w)?ceil(($l/$w)*2)/2:1;
		}
		return array('rows'=>$ct,'str'=>$txt);
	}
	
	function RowHeader(){
		$this->Borders=$this->Table['Border'];
		$this->Fills=array('Color'=>'#cccccc');
		$this->SetFont('Arial','B',10);
		$this->widths=$this->Table['RowHeader']['Widths'];
		$this->aligns=$this->Table['RowHeader']['Aligns'];
		$this->Row($this->Table['RowHeader']['Labels']);
		$this->SetFont('Arial','',10);
		$this->Fills=$this->Table['Fill'];;
	}
	function SectionHeader($str=''){
		$this->Borders=$this->Table['Border'];
		$this->Fills=array('Color'=>'#cccccc');
		$this->SetFont('Arial','B',10);
		$this->widths=array(150);
		$this->aligns=array('L');
		$this->Row(array($str));
		$this->SetFont('Arial','',10);
		$this->Fills=$this->Table['Fill'];;
		$this->widths=$this->Table['RowHeader']['Widths'];
		$this->aligns=$this->Table['RowHeader']['Aligns'];
	}
	
	//logo & header
    function LogoHeader($ht=40) {
		if((int)$ht<20) $ht=20;//header top position
		$this->SetFont('Arial','',12);
		$this->SetY(9);
		$this->SetTextColor(255,255,255);
		$this->SetFillColor(0);
		$this->Cell(192,5,$this->PHeader['Title'],0,0,'C',1);
		$this->SetTextColor(0);
		$this->SetFontSize(10);
		$this->SetFillColor(255,255,255);
		$this->ln();
		
 		$this->widths=$this->PHeader['Widths'];
		$this->aligns=$this->PHeader['Aligns'];
		$this->Fills=$this->PHeader['Fill'];
		$this->Borders=$this->PHeader['Border'];	
		$this->Sety($ht);
		$this->Row(array($this->PHeader['Client'],$this->PHeader['Metrics_labels'],$this->PHeader['Metrics']));
		$tmp_X=$this->GetX();
		$tmp_Y=$this->GetY();
		
		//place image & address
        $this->SetFontSize(8);
         if(issetCheck($this->PHeader['Logo'],'img')){
			$this->Image($this->PHeader['Logo']['img'],179,14,25,25);
		}
		if(issetCheck($this->PHeader['Logo'],'text')){
			$this->SetX(9);
			$this->Sety(14);
			$txt=str_replace('<br/>',"\n",$this->PHeader['Logo']['text']);
			$this->MultiCell(168, 4, $txt, 0, 'R');
		}
        $this->SetFontSize(10);
        
        //reset
        $this->SetX($tmp_X);
        $this->SetY($tmp_Y);
		$this->ln(2);
    }
    
    function PutAddressHeading($text) {
		if(!$text|| $text==='') return;
        $this->SetFontSize(10);
        $this->MultiCell(200, 5, $text, 0, "L");
        $this->SetY(51);
        $this->Ln(1);
        $this->SetTextColor(0, 0, 0);
    }
	
}
