<?php
//namespace APP\Controllers;

class Controller {
	private static $instance;
	protected $_middlewares = array();
	public $request;
	public $respone;
	public $load;
	
	function __construct() {
		self::$instance = & $this;
		$this->load = Loader::get_instance();
		$this->request = Request::get_instance();
		$this->response = Response::get_instance();
		
	}
	
	public function __call($name, $arguments) {
		
	}
	
	public static function & get_instance() {
		if (! self::$instance)
			self::$instance = new self();
		return self::$instance;
	}
	
	public function register($sub) {
		$this->_middlewares[] = $sub;
	}

	public function trigger() {
		if(!empty($this->_middlewares)) {
			foreach($this->_middlewares as $observer) {
				$observer->handle();
			}
		}
	}

}
