<?php
//namespace APP;

if (file_exists('app/init.php')) {
	require 'app/init.php';
} else {
	throw new \Exception(sprintf('Initialize failed in %s.', $_SERVER('SCRIPT_FILENAME')));
}

abstract class Application {
	//abstract class
}

class Novious extends Application {
	private static $_instance;
	private static $_libs = array();
	private static $_config = array();
	private static $_request = null;
	private static $_response = null;
	private $listeners = array();
	private $events = ['start', 'before', 'obtain', 'present', 
			'completed', 'error', 'notfound', 'badrequest', 'next', 'end'];
	public static $loads = array();
	public $router = null;
	public $loader = null;
	public $marker = array();
	public $urls = array();
	
	public function __construct() {
		register_shutdown_function(array($this, '__shutdown'));
		self::$_instance = $this;
		$this->marker['_start'] = microtime();
		$this->init();
		
	}
	
	public function __destruct() {
		$this->marker['_end'] = microtime();
		$this->loader->unregister();
		$this->on_end();
	}
	
	private function init() {
		if ($this->loader == null) {
			$this->loader = new Loader();
		}
		$this->loader->register();
		$this->loader->custom_finder(function($class) {
			if (preg_match('/.*Controller$/', $class)) {
			 	return CONTROLLER_PATH.'/'.$class.'.php';
			} else if (preg_match('/.*(?:Model|Module)$/', $class)) {
				return MODEL_PATH.'/'.$class.'.php';
			} else if (preg_match('/.*Middleware$/', $class)) {
				return MIDDLEWARE_PATH.'/'.$class.'.php';
			} else if (preg_match('/.*Helper$/', $class)) {
				return HELPER_PATH.'/'.$class.'.php';
			} else if (is_file(LIBRARY_PATH.'/'.$class.'.php')) {
				return LIBRARY_PATH.'/'.$class.'.php';
			}
			
			return APP_PATH.'/'.str_replace('\\', '/', strtolower($class)).'.php';
		});
		
		if ($this->router == null) {
			$this->router = new Router();
		}
		self::$_libs = array (
			'App' => APP_PATH.'/novious.php',
			'Controller' => CONTROLLER_PATH.'/BaseController.php',
			'Middleware' => MIDDLEWARE_PATH.'/BaseMiddleware.php',
			'Model' => MODEL_PATH.'/BaseModel.php',
		);
		
		$this->loader->import(self::$_libs);
		
		set_error_handler(array('Handler', 'handle'));
		
		self::$_request = Request::get_instance();
		self::$_response = Response::get_instance();
	}
	
	/**
	 * 获取应用实例
	 * @return Novious
	 */
	public static function get_instance() {
		if (! self::$_instance)
			self::$_instance = new self();
		return self::$_instance;
	}
	
	public function load($file) {
		if (! is_file($file)) {
			throw new \InvalidArgumentException('Can load non-exists file "' . $file . '"');
		}

		if (isset(self::$loads[$file])) {
			return self::$loads[$file];
		}

		return self::$loads[$file] = include($file);
	}
	
	/**
	 * 设置路由器
	 * @param Router $router
	 */
	public function set_router($router) {
		if ($router instanceof Router)
			$this->router = $router;
	}
	
	/**
	 * 注册蓝图，分配子路由器
	 * @param unknown $pattern 路径适配表达式
	 * @param Router $router
	 */
	public function register_blueprint($pattern, $router) {
		$this->router->group($pattern, $router);
	}
	
	/**
	 * 路由
	 * @param string $pattern 路径适配表达式
	 * @param mixed $callback 回调函数或数组参数
	 */
	public function route($pattern, $callback) {
		$this->router->route($pattern, $callback);
	}
	
	/**
	 * 记录
	 * @param string $key
	 * @param string $value
	 * @return multitype:|Ambigous <multitype:, mixed, string>
	 */
	public function mark($key=null, $value=null) {
		if (isset($key)) {
			if (isset($value))
				$this->marker[$key] = $value;
			else
				return $this->marker[$key];
		}
		
		return $this->marker;
	}
	
	/**
	 * 
	 * @param unknown $config
	 * @return multitype:
	 */
	public function config($config=array()) {
		if (! empty($config)) {
			self::$_config = array_merge(self::$_config, (array) $config);
		}
		return self::$_config;
	}
	
	public function request() {
		return self::$_request;
	}
	
	public function response() {
		return self::$_response;
	}
	
	public static function errors() {
		return Handler::errors();
	}
	
	public static function get_last_error() {
		return Handler::get_last_error();
	}
	
	/**
	 * 注册某事件监听器
	 * @param string $event 事件名称
	 * @param mixed $callback 回调函数或数组参数
	 * @return Novious
	 */
	public function on($event, $callback) {
		$event = strtolower($event);
		if (in_array($event, $this->events)) {
			$this->listeners[$event][] = $callback;
			//return true;
		} else {
			//return false;
		}
		return $this;
	}
	
	/**
	 * 注册一次性的某事件监听器
	 * @param string $event 事件名称
	 * @param mixed $callback 回调函数或数组参数
	 * @return boolean|Novious
	 */
	public function once($event, $callback) {
		$event = strtolower($event);
		if (in_array($event, $this->events)) {
			$this->listeners[$event][] = array($callback, array('times' => 1));
			
		} else {
			return false;
		}
		
		return $this;
	}
	
	/**
	 * 取消某事件的监听器
	 * @param string $event 事件名称
	 * @param mixed $callback 回调函数或数组参数
	 * @return Novious
	 */
	public function off($event, $callback=null) {
		$event = strtolower($event);
		if (!empty($this->listeners[$event])) {
			if (is_null($callback)) {
				$this->listeners[$event] = array();
			} else if (($key = array_search($callback, $this->listeners[$event])) !== false) {
				unset($this->listeners[$event][$key]);
			}
		}
		return $this;
	}
	
	protected function trigger($event, $params=null) {
		$event = strtolower($event);
		if ($params !== null) {
			$params = array_slice(func_get_args(), 1);
		} else {
			$params = array();
		}
		if (empty($this->listeners[$event])) return false;
		foreach ($this->listeners[$event] as $callback) {
			if (is_array($callback)) {
				$extras = $callback[1];
				$callback = $callback[0];
				if ($extras and is_array($extras)) {
					$times = & $extras['times'];
					$times = intval($times);
				}
			}
			if (isset($times)) {
				if ($times === 0 or $times < 0) {
					unset($callback);
					return true;
				} else $times--;
			}
			if ($callback instanceof \Closure) {
				$return = call_user_func_array($callback, $params);
			} else {
				$callback = (string) $callback; 
				$calls = explode('@', $callback);
				if (count($calls) <= 1) {
					if (function_exists($calls[0])) {
						$return = call_user_func_array($calls[0], $params);
					} else {
						$return = false;
					}
				} else {
					$method = $calls[1];
					if (class_exists($calls[0])) $callback = new $calls[0]();
					else $callback = false;
					if ($callback and method_exists($callback, $method)) {
						$return = call_user_func_array(array($callback, $method), $params);
					} else {
						$return = false;
					}
				}
			}
		}
		return true;
	}
	
	/**
	 * 应用开始
	 */
	public function on_start() {
		$this->trigger('start');
	}
	
	/**
	 * 应用执行路由前
	 */
	public function on_before_route() {
		$this->trigger('before');
	}
	
	/**
	 * 应用路由已匹配请求
	 * @param Router $args 路由
	 */
	public function on_obtain($args=null) {
		$this->trigger('obtain', $args);
	}

	/**
	 * 应用开始回应请求
	 * @param string $args 回应视图文本
	 */
	public function on_present($args=null) {
		$this->trigger('present', $args);
	}
	
	/**
	 * 应用已完成回应
	 */
	public function on_completed() {
		$this->trigger('completed');
	}
	
	/**
	 * 应用发生内部错误
	 * @param array $args
	 */
	public function on_error($args=null) {
		if (is_null($args)) $args = self::get_last_error();
		$this->trigger('error', $args);
	}
	
	/**
	 * 应用发生404错误
	 */
	public function on_notfound() {
		$this->trigger('notfound');
	}
	
	/**
	 * 应用发生400错误
	 */
	public function on_badrequest() {
		$this->trigger('badrequest');
	}
	
	/**
	 * 应用请求开始分发给下一个中间件
	 * @param string $args 中间件
	 */
	public function on_next($args=null) {
		$this->trigger('next', $args);
	}
	
	/**
	 * 应用结束
	 */
	public function on_end() {
		$this->trigger('end');
	}
	
	/**
	 * 计算消耗的时间（秒）
	 * @param string $point1
	 * @param string $point2
	 * @param number $decimals
	 * @return string
	 */
	function elapsed_time($point2 = '', $point1 = '', $decimals = 4) {
		if (empty($this->marker[$point1])) {
			$point1 = '_start';
		}
	
		if (empty($this->marker[$point2])) {
			$this->marker[$point2] = microtime();
		}
	
		list($sm, $ss) = explode(' ', $this->marker[$point1]);
		list($em, $es) = explode(' ', $this->marker[$point2]);
	
		return number_format(($em + $es) - ($sm + $ss), $decimals);
	}
	
	function microtime_float($time=null) {
		if (! $time) $time = microtime();
		list($usec, $sec) = explode(' ', $time);
		return ((float)$usec + (float)$sec);
	}
	
	/**
	 * 运行应用
	 * @access      public
	 * @param       array   $config
	 */
	public function run() {
		$this->on_start();
		$this->router->execute($this);
	}

	/**
	 * 中断并结束应用
	 * @param unknown $code
	 * @param string $msg
	 */
	public function abort($code, $msg=null) {
		
		exit($msg);
	}

	public function __shutdown() {
		$last_error = error_get_last();
		if (! is_null($last_error)) {
			if (in_array($last_error['type'], array(E_ERROR, E_WARNING))) {
				
			}
			//不检查通知及自定义的错误
			if ($last_error['type'] === E_NOTICE || $last_error['type'] === E_USER_ERROR
					|| $last_error['type'] === E_USER_DEPRECATED
					|| $last_error['type'] === E_USER_WARNING
					|| $last_error['type'] === E_USER_NOTICE) {
				return;
			}
			$this->on_error($last_error);
		}
	}
	
}

class App extends Novious {

	function write_log($level='warning', $msg='') {
		$time = date('Y-m-d H:i:s');
		if (class_exists('SaeKV')) {
			sae_set_display_errors(false);
			sae_debug('['. strtoupper($level) .']: '. $msg);
			sae_set_display_errors(true); 
			return true;
		}
	
		return false;
	}

}
