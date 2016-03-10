<?php

class AdminController extends Controller {

	public function __construct() {
		parent::__construct();
		
		App::get_instance()->on('error', function($err=null) {
			//print_r(microtime(true));
			//var_dump($err);
		})->on('end', function() {
			//print_r(microtime(true));
			//var_dump(App::errors());
		});
	}

	public function index() {
		$config['default'] = array(
				'suffix'  => '.view.php',
				'template_path'  =>  ROOT_DIR . '/templates',
				'storage_path'  =>  'cache',
				'bucket' => 'templates',
				'engine'  =>  'default',
				'expire'  =>  5000,
		);
		
		$request = $this->request;
		$data = array();
		//$data['page_description'] = '';
		//$data['page_keywords'] = '';
		$data['page_title'] = '后台管理';
		$data['static_cdn'] = '/public';
		//var_dump($request::data());
		return Loader::view('admin/index', $data, $config);
	}
	
	public function direct($name) {
		$config['default'] = array(
				'suffix'  => '.view.php',
				'template_path'  =>  ROOT_DIR . '/templates',
				'storage_path'  =>  'cache',
				'bucket' => 'templates',
				'engine'  =>  'default',
				'expire'  =>  5000,
		);
		
		$request = $this->request;
		$data = array();
		//$data['page_description'] = '';
		//$data['page_keywords'] = '';
		$data['page_title'] = '后台管理';
		$data['static_cdn'] = '/public';
		//set_error_handler(array('Handler', 'html_handle_error'));
		return Loader::view('admin/'.$name, $data, $config);
		
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
	
	public function display($a=null, $b=null) {
		return $this->load->view('page', array(
				'foo' => $a,
				'bar' => $b
				
		));
	}

}
