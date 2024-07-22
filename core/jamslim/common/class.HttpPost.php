<?php
class HttpPost {
	public $url;
	public $postString;
	public $httpResponse;
	public $password;
	private $copts=[
		CURLOPT_FOLLOWLOCATION=>false,
		CURLOPT_HEADER=>false,
		CURLOPT_RETURNTRANSFER=>true
	];
	
	public $ch;
	
	/**
	 * Constructs an HttpPost object and initializes CURL
	 *
	 * @param url the url to be accessed
	 */
	public function __construct($url) {
		$this->url = $url;
		$this->ch = curl_init( $this->url );
		//allow self signed cert on dev server
		//if(strpos($this->url,'//jamserver')!==false) $this->copts[CURLOPT_SSL_VERIFYPEER]=false;
	}
	
	/**
	 * shut down CURL before destroying the HttpPost object
	 */
	public function __destruct() {
		if($this->ch) curl_close($this->ch);
	}
	public function setPassword($pass=false){
		//sets the curl password 
		$pass=trim($pass);
		if($pass!=='') $this->copts[CURLOPT_USERPWD]=$pass;  
	}
	public function setSSLChecks($check=true){
		$this->copts[CURLOPT_SSL_VERIFYHOST]=$check;
		$this->copts[CURLOPT_SSL_VERIFYPEER]=$check;		
	}
	/**
	 * Convert an incoming associative array into a POST string
	 * for use with our HTTP POST
	 *
	 * @param params an associative array of data pairs
	 */
	public function setPostData($params) {
		// http_build_query encodes URLs, which breaks POST data
		$this->postString = rawurldecode(http_build_query( $params ));
		$this->copts[CURLOPT_POST]=true;
		$this->copts[CURLOPT_POSTFIELDS]=$this->postString;
	}
	
	/**
	 * Make the POST request to the server
	 */
	public function send() {
		curl_setopt_array($this->ch, $this->copts);
		$chk =curl_exec( $this->ch );		
		$this->httpResponse = ($chk)?$chk:'Curl error: ' . curl_error($this->ch );
	}
	/**
	 * Read the HTTP Response returned by the server
	 */
	public function getResponse($raw=false) {
		if($raw) return $this->httpResponse;
		$r=ltrim($this->httpResponse,'url');
		return $r;
	}	
}
