<?php

class TestController extends Controller {

	public function __construct() {
		parent::__construct();
		App::get_instance()->on('start', function() {
			echo "[app-log] : start.\r\n";
		})->on('before', function() {
			echo "[app-log] : before.\r\n";
		})->on('end', function() {
			echo "[app-log] : end.\r\n";
		})->on('completed', function() {
			echo "[app-log] : completed.\r\n";
		})->on('error', function($err=null) {
			echo "[app-log] : error.\r\n";
			var_dump($err);
		});
	}

	public function index() {
		$request = $GLOBALS['app']->request();
		var_dump($request::data());
	}
	
	public function test($a=null) {
		//$GLOBALS['config']['template']['engine'] = false;
		return $this->load->view('test');
	}

	public function home($a=null) {
		return $this->load->view('home', array(
				'user' => array(
						'name' => $a,
						'role' => ''
					),
				'users' => array(
						'Name_1',
						'Name_2'
					)
			));
	}
	
	public function my($a=null) {
		return $this->load->view('my', array(
				'engine' => $a
		));
	}
	
	public function display($a=null, $b=null) {
		return $this->load->view('page', array(
				'foo' => $a,
				'bar' => $b
				
		));
	}

}
