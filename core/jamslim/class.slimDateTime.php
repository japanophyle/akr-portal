<?php
class slimDateTime extends DateTime{
    /**
     * @param int|null $year
     * @param int|null $month
     * @param int|null $day
     *
     * @return $this
     */
    public function setDate($year, $month, $day){
        if (null == $year) {
            $year = $this->format('Y');
        }
        if (null == $month) {
            $month = $this->format('n');
        }
        if (null == $day) {
            $day = $this->format('j');
        }
        $daysInMonth = cal_days_in_month(CAL_GREGORIAN, $month, $year);
        $day = $day > $daysInMonth ? $daysInMonth : $day;
        $return = parent::setDate($year, $month, $day);
        return $return;
    }

    /**
     * @inheritdoc
     */
    public function modify($modify){
        $pattern = '/( ?[-+]?\d?\w* months?)?( ?[-+]?\d?\w* years?)?/i';
        $modify = preg_replace_callback(
            $pattern,
            function($matches) use ($pattern) {
                if (empty($matches[0])) {
                    return;
                }
                $orDay = $this->format('j');
                $this->setDate(null, null, 1);
                if (!parent::modify($matches[0])) {
                    return;
                }
                $this->setDate(null, null, $orDay);
                return;
            },
            $modify
        );
        if ($modify = trim($modify)) {
			if(strlen($modify)>1)  return parent::modify($modify);
        }
        return $this;
    }    
}
