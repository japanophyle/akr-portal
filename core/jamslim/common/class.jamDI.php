<?php
use Pimple\Container;
class jamDI extends Container{
	public $memberNav;// should be in the container??
	public function get($what=false){
		if (!$this->offsetExists($what)){
			 throw new Exception(sprintf('Identifier "%s" is not defined.', $what));
		}
		try{
			return $this->offsetGet($what);
		}catch(\InvalidArgumentException  $e){
			 if ($this->exceptionThrownByContainer($e)) {
				throw new Exception(sprintf('Container error while retrieving "%s".', $what));
			 }else{
				throw new Exception($e);
			 }
		}
	}	
	public function set($what=false,$value=false){
		return $this->offsetSet($what,$value);
	}	
    private function exceptionThrownByContainer(\InvalidArgumentException $exception){
		$e=$exception->getTrace();
		$trace = $e[0];
		$chk=false;
		if($trace['class'] === PimpleContainer::class && $trace['function'] === 'offsetGet') $chk=true;
		if($chk) return $trace;
        //for php5.5+
        //$trace = $exception->getTrace()[0]; 
        //return $trace['class'] === PimpleContainer::class && $trace['function'] === 'offsetGet';
    }

    /**
     * Returns true if the container can return an entry for the given identifier.
     * Returns false otherwise.
     *
     * @param string $id Identifier of the entry to look for.
     *
     * @return boolean
     */
    public function has($id){
        return $this->offsetExists($id);
    }


    /********************************************************************************
     * Magic methods for convenience
     *******************************************************************************/

    public function __get($name){
        return $this->get($name);
    }

    public function __isset($name){
        return $this->has($name);
    }
    /********************************************************************************
     * static methods for convenience
     *******************************************************************************/
    public static function getDI($name){
        return self::get($name);
    }
}
