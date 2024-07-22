<?php
class date_time {
	var $START=false;
	var $END=false;
	var $NAME=false;
	var $TIME=false;
	var $DATE=false;
	var $DATA=false;
	var $SET=false;
	var $FORSQL=false;
	var $FMT='d/m/Y';
	var $MONTHS=false;

	function __construct(){
		$this->MONTHS = array('', 'January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December');
	}
	
	private function init($args=false){
		if(is_array($args)){
			foreach($args as $i=>$v){
				$k=strtoupper($i);
				if(property_exists($this, $k))$this->$k=$v;
			}
		}
	}
	
	public function _get($function=false,$args=false){
		if($function && method_exists($this,$function)){
			$this->init($args);
			return $this->{$function}($args);
		}else{
			preME($function.' not found',2);
		}
	}
	
	
	function get_time_difference() {
		$start=$this->START;
		$end=$this->END;
		$uts['start'] = (!(int) $start) ? strtotime($start) : $start;
		$uts['end'] = (!(int) $end) ? strtotime($end) : $end;
		$err = $out = false;
		if ($uts['start'] !== -1 && $uts['end'] !== -1) {
			if ($uts['end'] >= $uts['start']) {
				$diff = $uts['end'] - $uts['start'];
				if ($days = intval((floor($diff / 86400))))
					$diff = $diff % 86400;
				if ($hours = intval((floor($diff / 3600))))
					$diff = $diff % 3600;
				if ($minutes = intval((floor($diff / 60))))
					$diff = $diff % 60;
				$diff = intval($diff);
				$out = array('days' => $days, 'hours' => $hours, 'minutes' => $minutes, 'seconds' => $diff);
			}else {
				$err = "Ending date/time($start) is earlier than the start date/time($end)";
			}
		} else {
			$err = "Invalid date/time data detected";
		}
		if ($err)
			$out = $err;
		return $out;
	}

	function foundationDatePicker(){
		$name=$this->NAME;
		$date=$this->DATE;
		$time=$this->TIME;
		$startdate=$this->START;
		$dp='<input type="text" class="FDP" id="fdp_'.$name.'" value="'.$date.'" name="'.$name.'" >';
		return $dp;
	}

	function datePicker() {
		// This function displays a dropdown date/time picker
		$name=$this->NAME;
		$date=$this->DATE;
		$time=$this->TIME;
		$startdate=$this->START;
		$months = $this->MONTHS;
		$startdate = ($startdate) ? strtotime($startdate) : time();
		$startyear = date("Y", $startdate);
		$date = ($date) ? strtotime(str_replace('GMT', '', $date)) : $startdate;
		$endyear = date("Y", $date) + 5;
		$year = date("Y", $date);
		$month = date("m", $date);
		$day = date("d", $date);
		$hour = date("H", $date);
		$min = date("i", $date);
		//months
		$opts = "";
		for ($i = 1; $i <= 12; $i++) {
			$selected = ($i == $month) ? 'selected' : '';
			$opts.="<option $selected value='$i'>$months[$i]</option>";
		}
		$htmlMonth = "<select id='dpMonth' name=\"" . $name . "[month]\" class=\"txtfeld\">" . $opts . "</select> ";
		//days
		$opts = "";
		for ($i = 1; $i <= 31; $i++) {
			$selected = ($i == $day) ? 'selected' : '';
			$opts.="<option $selected value='$i'>$i</option>";
		}
		$htmlDay = "<select id='dpDay' name=\"" . $name . "[day]\" class=\"txtfeld\">" . $opts . "</select> ";
		//years
		$opts = "";
		for ($i = $startyear; $i <= $endyear; $i++) {
			$selected = ($i == $year) ? 'selected' : '';
			$opts.="<option $selected value='$i'>$i</option>";
		}
		$htmlYear = "<select name=\"" . $name . "[year]\" class=\"txtfeld\">" . $opts . "</select>";
		//hour
		$opts = "";
		for ($i = 1; $i <= 24; $i++) {
			$selected = ($i == $hour) ? 'selected' : '';
			$opts.="<option $selected value='$i'>$i</option>";
		}
		$htmlHour = "<select name=\"" . $name . "[hour]\" class=\"txtfeld\">" . $opts . "</select>";
		//minutes
		$opts = "";
		$i = 0;
		while ($i <= 45) {
			$selected = ($i == $min) ? 'selected' : '';
			$opts.="<option $selected value='$i'>$i</option>";
			$i+=15;
		}
		$htmlMin = "<select name=\"" . $name . "[min]\" class=\"txtfeld\">" . $opts . "</select> ";
		$sz = ($time) ? 2 : 3;
		$out = '<div class="row collapse datetime"><div class="large-1 columns text-right"><span class="postfix dkgreen_grad">Date:</span></div><div class="large-' . $sz . ' columns">' . $htmlDay . '</div><div class="large-' . $sz . ' columns">' . $htmlMonth . '</div><div class="large-' . $sz . ' columns">' . $htmlYear . '</div>';
		if ($time) {
			$out.='<div class="large-1 columns text-right"><span class="postfix dkgreen_grad">Time:</span></div><div class="large-' . $sz . ' columns">' . $htmlHour . '</div><div class="large-' . $sz . ' columns">' . $htmlMin . '</div>';
		} else {
			$out.='<div class="large-1 columns"></div>';
		}
		$out.='</div>';
		return $out;
	}
	
	function getDateStamp() {
		$data=$this->DATA;
		$set=$this->SET;
		$forsql = $this->FORSQL;
		// This function formats the time for SQL and display
		$time = time();
		$mkDay = $data[$set . 'day'];
		$mkMonth = $data[$set . 'month'];
		$mkYear = $data[$set . 'year'];
		$mkHour = ($data[$set . 'hour']) ? $data[$set . 'hour'] : date('H', $time);
		$mkMin = ($data[$set . 'min']) ? $data[$set . 'min'] : date('i', $time);
		$mkDate = $mkYear . '-' . $mkMonth . '-' . $mkDay . ' ' . $mkHour . ':' . $mkMin . ':00';
		$stamp = ($forsql) ? $mkDate : date('d M Y', strtotime($mkDate));
		return $stamp;
	}

	function formatDate(){
		$xval=strtotime($this->DATE);
		$out=date($this->FMT,$xval);
		return $out;
	}

	function getMonthName(){
		$month=(int)$this->DATA;
		if($month>0 && $month<13){
			return $this->MONTHS[$this->DATA];
		}
		return false;			
	}

	function is_date(){
		 $stamp = strtotime($this->DATE);
		 if (!is_numeric($stamp)){
			return FALSE;
		 }
		 $month = date( 'm', $stamp );
		 $day   = date( 'd', $stamp );
		 $year  = date( 'Y', $stamp );
		 if (checkdate($month, $day, $year)) {
		   return TRUE;
		 }
		 return FALSE;
	}

	function getDateMetrics($args){
		$day=$month=$year=false;
		extract($args);
		if($month && $year){
			$t=strtotime($day.'-'.$month.'-'.$year);
		}else{
			$t=$day;
		}
		$date['daynum'] = date('N', $t);
		$date['weeknum'] = date('W', $t);
		$date['month'] = date('n', $t);
		$date['monthweek'] = $date['weeknum'] - date("W", strtotime(date("Y-m-01", $t))) + 1;
		$date['day'] = date('j', $t);
		$date['year'] = date('Y', $t);
		$date['stamp'] = $t;
		return $date;
	}

	function getRecurringMonthDay($stamp){
		$m=date('F',$stamp);
		$y=date('Y',$stamp);
		$d=date('l',$stamp);
		$pos=array(1=>'first',2=>'second',3=>'third',4=>'fourth',5=>'fifth');
		$out=false;
		foreach($pos as $i=>$v){
			$chk=strtotime("$v $d Of $m $y");
			if($chk==$stamp) $out=array('day'=>$d,'pos'=>$v,'stamp'=>$chk);
		}
		return $out;	
	}
	function nextRecurringDateStamp($args){
		$start_date=false;$interval_days=1;
		extract($args);
		$start = (is_numeric($start_date))?$start_date:strtotime($date);
		$end = strtotime(date('Y-m-d'));
		$days_ago = ($end - $start) / 24 / 60 / 60;
		if($days_ago < 0)return date($output_format,$start);
		$remainder_days = $days_ago % $interval_days;
		if($remainder_days > 0){
			$new_date_string = "+" . ($interval_days - $remainder_days) . " days";
		} else {
			$new_date_string = date('Y-m-d',$start);//"today";
		}
		return strtotime($new_date_string);
	}

	function nextRecurringDateStamp_day($args){
		$date=false;$when='this';$period='month';$pos='auto';$format=0;
		extract($args);
		//$whens=array(1=>'this',2=>'last',3=>'next');
		//$periods=array(1=>'week',2=>'month',3=>'year');
		
		$stamp=(is_numeric($date))?$date:strtotime($date);
		$dayname=date('l',$stamp);
		$daynum=date('N',$stamp);
		
		$ct=array(1=>'first',2=>'second',3=>'third',4=>'fourth',5=>'fifth');
		
		$pos=($pos==='auto')?$ct[$daynum]:$pos;
		$glue=($pos==='auto')?' of ':' ';
		$str=$pos.' '.$dayname.$glue.$when.' '.$period;
		$t=strtotime($str);
		if($format) $t=date('d/m/Y',$t);
		return $t;
	}

	function makeHolidays($args){
		//make a new holiday array
		$year=false;$old=false;
		extract($args);
		$y=((int)$year)?$year:date('Y');
		$h=$this->getBankHolidays($y);		
		$data['year']=$y;
		$data['bank']=false;
		$data['hols']=(isset($old['hols']))?$old['hols']:array();
		
		$b=false;
		$ct=1;
		foreach($h as $i=>$v){
			if(is_array($v)){
				foreach($v as $x=>$y){
					$t=strtotime($y);
					$b[$ct][$t]=ucME($i);
					$ct++;
				}
			}else{
				$t=strtotime($v);
				$b[$ct][$t]=ucME($i);
				$ct++;
			}
		}
		$data['bank']=$b;
		return $data;
	}

	function getBankHolidays($yr) {
	/*
	*    Function to calculate which days are British bank holidays (England & Wales) for a given year.
	*
	*    USAGE:
	*    array getBankHolidays(int $yr)
	*
	*    ARGUMENTS
	*    $yr = 4 digit numeric representation of the year (eg 1997).
	*
	*    RETURN VALUE
	*    Returns an array of strings where each string is a date of a bank holiday in the format "yyyy-mm-dd".
	*/
		$bankHols = Array();
		// New year's:
		switch ( date("w", strtotime("$yr-01-01 12:00:00")) ) {
			case 6:
				$bankHols['new_year'] = "$yr-01-03";
				break;
			case 0:
				$bankHols['new_year'] = "$yr-01-02";
				break;
			default:
				$bankHols['new_year'] = "$yr-01-01";
		}

		// Good friday:
		$bankHols['good_friday'] = date("Y-m-d", strtotime( "+".(easter_days($yr) - 2)." days", strtotime("$yr-03-21 12:00:00") ));

		// Easter Monday:
		$bankHols['easter_monday'] = date("Y-m-d", strtotime( "+".(easter_days($yr) + 1)." days", strtotime("$yr-03-21 12:00:00") ));

		// May Day:
		if ($yr == 1995) {
			$bankHols['may_day'] = "1995-05-08"; // VE day 50th anniversary year exception
		} else {
			switch (date("w", strtotime("$yr-05-01 12:00:00"))) {
				case 0:
					$bankHols['may_day'] = "$yr-05-02";
					break;
				case 1:
					$bankHols['may_day'] = "$yr-05-01";
					break;
				case 2:
					$bankHols['may_day'] = "$yr-05-07";
					break;
				case 3:
					$bankHols['may_day'] = "$yr-05-06";
					break;
				case 4:
					$bankHols['may_day'] = "$yr-05-05";
					break;
				case 5:
					$bankHols['may_day'] = "$yr-05-04";
					break;
				case 6:
					$bankHols['may_day'] = "$yr-05-03";
					break;
			}
		}

		// Whitsun:
		if ($yr == 2002) { // exception year
			$bankHols['whitsun'][] = "2002-06-03";
			$bankHols['whitsun'][] = "2002-06-04";
		} else {
			switch (date("w", strtotime("$yr-05-31 12:00:00"))) {
				case 0:
					$bankHols['whitsun'] = "$yr-05-25";
					break;
				case 1:
					$bankHols['whitsun'] = "$yr-05-31";
					break;
				case 2:
					$bankHols['whitsun'] = "$yr-05-30";
					break;
				case 3:
					$bankHols['whitsun'] = "$yr-05-29";
					break;
				case 4:
					$bankHols['whitsun'] = "$yr-05-28";
					break;
				case 5:
					$bankHols['whitsun'] = "$yr-05-27";
					break;
				case 6:
					$bankHols['whitsun'] = "$yr-05-26";
					break;
			}
		}

		// Summer Bank Holiday:
		switch (date("w", strtotime("$yr-08-31 12:00:00"))) {
			case 0:
				$bankHols['summer'] = "$yr-08-25";
				break;
			case 1:
				$bankHols['summer'] = "$yr-08-31";
				break;
			case 2:
				$bankHols['summer'] = "$yr-08-30";
				break;
			case 3:
				$bankHols['summer'] = "$yr-08-29";
				break;
			case 4:
				$bankHols['summer'] = "$yr-08-28";
				break;
			case 5:
				$bankHols['summer'] = "$yr-08-27";
				break;
			case 6:
				$bankHols['summer'] = "$yr-08-26";
				break;
		}

		// Christmas:
		switch ( date("w", strtotime("$yr-12-25 12:00:00")) ) {
			case 5:
				$bankHols['christmas'][] = "$yr-12-25";
				$bankHols['christmas'][] = "$yr-12-28";
				break;
			case 6:
				$bankHols['christmas'][] = "$yr-12-27";
				$bankHols['christmas'][] = "$yr-12-28";
				break;
			case 0:
				$bankHols['christmas'][] = "$yr-12-26";
				$bankHols['christmas'][] = "$yr-12-27";
				break;
			default:
				$bankHols['christmas'][] = "$yr-12-25";
				$bankHols['christmas'][] = "$yr-12-26";
		}

		// Millenium eve
		if ($yr == 1999) {
			$bankHols['millenium'] = "1999-12-31";
		}
		return $bankHols;
	}
}
