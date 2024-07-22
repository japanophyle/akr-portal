<?php

class tabCalc{
	private $TAB=[];
	private $COUNTER;
	private $TOTAL;
	private $QTY;
	private $BALANCE;
	private $PAID;
	private $DISCOUNT;
	private $DISCOUNT_VALUE;
	private $DISCOUNTED;
	private $DISCOUNT_ITEMS=['discount'];
	private $POSTAGE;
	
	function __construct(){
		$this->reset();
	}
	function reset(){
		$this->TAB=[];
		$this->COUNTER=0;
		$this->TOTAL=0;
		$this->QTY=0;
		$this->BALANCE=0;
		$this->PAID=0;
		$this->DISCOUNT=0;
		$this->DISCOUNT_VALUE=0;
		$this->DISCOUNTED=0;
		$this->POSTAGE=0;
	}
	function add($val=0,$name=false,$qty=1,$discount=0){
		$pennies=toPennies($val);
		$this->COUNTER++;
		if(!$name||$name==='') $name='Item '.($this->COUNTER+1);
		$this->TAB[]=['val'=>$pennies,'qty'=>$qty,'op'=>'add','name'=>$name,'discount'=>$discount];
	}
	function subtract($val=0,$name=false,$qty=1){
		$pennies=toPennies($val);
		$this->COUNTER++;
		if(!$name||$name==='') $name='Item '.($this->COUNTER+1);
		$this->TAB[]=['val'=>$pennies,'qty'=>$qty,'op'=>'subtract','name'=>$name];
	}
	function line($tab=false){
		//returns the calculated line value
		$val=0;
		if(!$tab){
			if($this->TAB) $tab=end($this->TAB);
		}
		if($tab){
			$val=($tab['val']>0)?($tab['val'] * $tab['qty']):$tab['val'];
		}
		return $val;
	}
	function discount($val=0){
		$this->DISCOUNT=$val;
	}
	function discount_value($val=0){
		$this->DISCOUNT_VALUE=toPennies($val);
	}
	function discount_item($val=false){
		if($val) $this->DISCOUNT_ITEMS=$val;
	}
	function postage($val=0){
		$this->POSTAGE=$val;
	}
	function tab($total=false){
		$out=$this->TAB;
		if($total){
			$out+=$this->totals();
		}
		return $out;
	}
	function totals(){
		$this->total();
		return ['total'=>$this->TOTAL,'paid'=>$this->PAID,'balance'=>$this->BALANCE,'discount'=>$this->DISCOUNT,'discounted'=>$this->DISCOUNTED,'qty'=>$this->QTY];
	}
	function total($format=false){
		$this->calculate();
		return ($format)?toPounds($this->TOTAL):$this->TOTAL;
	}
	function calculate_discount($value=0,$discount=0,$as_array=false){
		$out=false;
		if($value && $discount){
			$d = $discount / 100;
			$dt = round($d * $value);
			$balance=($value - $dt);
			$out=($as_array)?['value'=>$dt,'balance'=>$balance]:$balance;
		}
		return $out;
	}
	private function calculate(){
		$total=$paid=$balance=$qty=$dt=$line_discount=0;
		foreach($this->TAB as $tab){
			$cval=$this->line($tab);
			switch($tab['op']){
				case 'add':
					if($tab['discount']>0){
						$tmp=$this->calculate_discount($cval,$tab['discount']);
						$line_discount+=$tmp['value'];
					}
					if($cval<0){
						$line_discount+=($cval-($cval *2));
					}else{
						$total += $cval;
						$balance += $cval;
						$qty += $tab['qty'];
					}
					break;
				case 'subtract':
					if($tab['name']==='discount'){
						$line_discount+=$cval;
					}else{
						$balance -= $cval;
						$paid += $cval;
						if($balance<0) $balance=0;
					}
					break;
			}
		}
		if($line_discount){
			$balance=($total - $line_discount);
			$dt=$line_discount;
		}else if($this->DISCOUNT_VALUE){
			$balance=($total - $this->DISCOUNT_VALUE);
			$dt=$this->DISCOUNT_VALUE;
		}else if($this->DISCOUNT){
			$discount=$this->calculate_discount($total,$this->DISCOUNT,1);
			$balance=$discount['balance'];
			$dt=$discount['value'];
		}
		$total+=$this->POSTAGE;
		$this->TOTAL=$total;
		$this->BALANCE=$balance;
		$this->DISCOUNTED=$dt;
		$this->QTY=$qty;
		$this->PAID=$paid;
		return $total;
	}	
}
