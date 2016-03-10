<?PHP
//namespace APP;

class Router {
	public $routes = array();
	public $blueprints = array();
	protected  $params = array();
	protected $middleware = array();
	protected $callback = null;
	private static $query_path = '';
	
	public function __construct() {
		if (empty(self::$query_path))
			self::$query_path =  $_SERVER['REQUEST_URI'];
	}
	
	private function __clone() {
	
	}
	
	public function get($pattern, $callback) {
		if (is_array($callback)) {
			$callback['method'] = ['GET'];
		} else {
			$callback = array(
				'controller' => $callback,
				'method' => ['GET']
			);
		}
		$this->route($pattern, $callback);
	}
	
	public function post($pattern, $callback) {
		if (is_array($callback)) {
			$callback['method'] = ['POST'];
		} else {
			$callback = array(
				'controller' => $callback,
				'method' => ['POST']
			);
		}
		$this->route($pattern, $callback);
	}
	
	public function delete($pattern, $callback) {
		if (is_array($callback)) {
			$callback['method'] = ['DELETE'];
		} else {
			$callback = array(
				'controller' => $callback,
				'method' => ['DELETE']
			);
		}
		$this->route($pattern, $callback);
	}
	
	public function route($pattern, $callback, $method=null) {
		$pattern = str_replace(array('{:int}', '{:id}', '{:num}'), '(\d+)', $pattern);
		$pattern = str_replace(array('{:str}', '{:value}', '{:field}'), '([\w\.\-\_]+)', $pattern);
		$pattern = str_replace(array('{:xid}', '{:sid}'), '(\w+\d+)', $pattern);
		$pattern = str_replace(array('{:any}', '{:all}'), '(.*?)', $pattern);
		$pattern = '/^(' . str_replace('/', '\/', $pattern) . ')(\?[^#]*)?(#.*)?$/';
		if (!is_null($method)) {
			if (!is_array($method)) $method = (array) $method;
		} else {
			$method = ['POST', 'GET'];
		}
		if (!is_array($callback)) {
			$callback = array(
				'as' => null,
				'controller' => $callback,
				'middleware' => [],
				'method' => $method
			);
		}
		$this->routes[$pattern] = $callback;
	}
	
	public function group($pattern, Router $router=self) {
		if ($router instanceof Router) {
			$this->blueprints[$pattern] = $router;
		} else {
			//pass
		}
	}
	
	/**
	 * 开始路由功能
	 * @param Application $app
	 */
	public function execute($app) {
		self::before_route($app);
		if ($this->match($app)) {
			$this->dispatch($app);
		}
		self::completed($app);
		
	}

	public function match($app=null) {
		$uri = self::$query_path;
		$uri = rtrim($uri, '/');
		if (stripos($uri, '/') !== 0) $uri = '/' . $uri;
		foreach ($this->blueprints as $pattern => $router) {
			if (stripos($uri, $pattern) === 0) {
				self::$query_path = substr(self::$query_path, strlen($pattern));
				if (isset($router->blueprints[$pattern])) {
					//unset($router->blueprints[$pattern]);
				}
				return $router->execute($app);
			}
		}
		foreach ($this->routes as $pattern => $callback) {
			if (preg_match($pattern, $uri, $params)) {
				$middleware = [];
				$request_method = $_SERVER['REQUEST_METHOD'];
				if (is_array($callback)) {
					$method = $callback['method'];
					$middleware = $callback['middleware'];
					$callback = $callback['controller'];
					if (is_array($method)) {
						if (!in_array($request_method, $method)) {
							self::badrequest($app);
							return 0;
						}
					} else {
						if ($request_method != $method) {
							self::badrequest($app);
							return 0;
						}
					}
				}
				unset($params[0]);
				array_shift($params);
				$this->params = $params;
				
				if (! is_array($middleware))
					$middleware = [$middleware];
				$this->middleware = array_merge($this->middleware, $middleware);
				
				$this->callback = $callback;
				return 1;
			}
		}
		self::notfound($app);
		return 0;
	}

	public function dispatch($app) {
		$data = (array) self::parse();
		$data['args'] = $this->params;
		$app->request()->set($data);
		
		$app->on_obtain($this);
		
		foreach ($this->middleware as $mw) {
			if ($mw == null) continue;
			if (! is_object($mw))
				if (class_exists($mw)) $mw = new $mw();
			if (method_exists($mw, 'handle')) {
				$mw->handle($app);
				//call_user_func(array($mw, 'handle'), $app);
			}
		}

		$return = false;
		if ($this->callback instanceof \Closure) {
			$return = call_user_func_array($this->callback, array_values($this->params));
		} else {
			$calls = explode('@', $this->callback);
			if (count($calls) <= 1) {
				if ($this->params[0]) {
					$calls[1] = $this->params[0];
					unset($this->params[0]);
				} else {
					$calls[1] = 'call';
				}
			}
			$callback = $calls[1];
			try {
				if (class_exists($calls[0])) $controller = new $calls[0]();
			} catch (Exception $e) {
				//print $e->getMessage();
			}
			if ($controller and method_exists($controller, $callback)) {
				$return = call_user_func_array(array($controller, $callback), array_values($this->params));
			} else {
				trigger_error(sprintf('控制器调用出错：%s@%s', $calls[0], $calls[1]), E_USER_WARNING);
				$return = false;
			}
		}
		
		if ($return === false) self::error($app);
		else if (! empty($return)) {
			$app->on_present($return);
			$app->response()->display($return);
		}
		
		return ($return === false) ? false : true;
	}

	/**
	 * 设置并返回路由中间件
	 * @param string $middleware
	 * @return Middleware
	 */
	public function middleware($middleware=null) {
		if (isset($middleware))
			$this->middleware[] = $middleware;
		
		return $this->middleware;
	}
	
	/**
	 * 解析URL地址
	 * @param string $url
	 * @return multitype:string mixed multitype:unknown
	 */
	public static function parse($url=null) {
		$request_uri = $_SERVER['REQUEST_URI'];
		$query_string = $_SERVER['QUERY_STRING'];
		if (!isset($url) or $url == null)
			$url = $request_uri;
		$url_query = parse_url($url);
		$path = $url_query['path'];
		$query = (isset($url_query['query']) ? ''.$url_query['query'] : '');
		$fragment = (isset($url_query['fragment']) ? ''.($url_query['fragment']) : '');
		$params = array();
		
		$arr = (!empty($query)) ? explode('&', $query) : array();
		if (count($arr) > 0) {
			foreach ($arr as $a) {
				$tmp = explode('=', $a);
				if (count($tmp) == 2) {
					$params[$tmp[0]] = $tmp[1];
				}
			}
		}
		
		//$pos = array_search('action', $fields);
		
		if (isset($pos)) {
			$field = $fields[$pos + 1];
			$value = $fields[$pos + 2];
		}
		if (isset($value)) {
			$action = isset($_GET['act']) ? $_GET['act'] : 'index';
			$class = new ReflectionClass('Goods');
			$goods = $class->newInstance();
			$m = $class->getMethod($action);
			$m->invoke($goods, 54934);
		}
		
		return array (
			'path' => $path,
			'params' => $params,
			'fragment' => $fragment
		);
	}

	public static function before_route($app=null) {
		if ($app != null) $app->on_before_route();
	}
	
	public static function completed($app=null) {
		if ($app != null) $app->on_completed();
	}
	
	public static function notfound($app=null) {
		@header('HTTP/1.1 404 Not Found');
		@header("status: 404 Not Found");
		if ($app != null) $app->on_notfound();
	}
	
	public static function badrequest($app=null) {
		@header('HTTP/1.1 400 Bad Request');
		@header("status: 400 Bad Request");
		if ($app != null) $app->on_badrequest();
	}
	
	public static function error($app=null) {
		@header('HTTP/1.1 500 Internal Server Error');
		@header("status: 500 Internal Server Error");
		$last_error = error_get_last();
		if ($app != null) $app->on_error($last_error);
	}
	
	public static function redirect($path) {
		@header('Location: '.$path, true, 302);
		
	}

}