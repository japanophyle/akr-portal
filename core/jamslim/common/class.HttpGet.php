<?php
class HttpGet {
	public $url;
	public $getString;
	public $httpResponse;
	public $password;
	public $filename;
	private $fh;
	
	public $ch;
	
	/**
	 * Constructs an HttpGet object and initializes CURL
	 *
	 * @param url the url to be accessed
	 */
	public function __construct($url,$params=false) {
		if($params) $this->setGetData($params);
		if($this->getString) $url.='?'.$this->getString;
		$this->url=$url;
		$this->ch = curl_init( $this->url );
		curl_setopt( $this->ch, CURLOPT_FOLLOWLOCATION, true );
		curl_setopt( $this->ch, CURLOPT_HEADER, false );
		curl_setopt( $this->ch, CURLOPT_RETURNTRANSFER, true );
		curl_setopt( $this->ch, CURLOPT_CONNECTTIMEOUT, 10 );
	}
	
	/**
	 * shut down CURL before destroying the Http object
	 */
	public function __destruct() {
		if($this->ch) curl_close($this->ch);
	}
	public function setFilename($filepath=false){
		//init file transfer
		if(trim($filepath)==='') return;
		$this->fh = fopen($filepath, 'w+b');
		curl_setopt( $this->ch, CURLOPT_FILE, $this->fh );
	}
	public function setPassword($pass=false){
		//sets the curl password 
		$pass=trim($pass);
		if($pass!=='')	curl_setopt($this->ch, CURLOPT_USERPWD, $pass);  
	}
	/**
	 * Convert an incoming associative array into a GET string
	 * @param params an associative array of data pairs
	 */
	public function setGetData($params) {
		// http_build_query encodes URLs, which breaks POST data
		$this->getString = rawurldecode(http_build_query( $params ));
	}
	
	/**
	 * Make the GET request to the server
	 */
	public function send() {
		$this->httpResponse = curl_exec( $this->ch );
		if($this->fh) fclose( $this->fh );
	}
	/**
	 * Read the HTTP Response returned by the server
	 */
	public function getResponse() {
		return $this->httpResponse;
	}	
}
