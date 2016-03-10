<?php
//namespace APP;

/**
 * 加载类
 */
final class Loader {
	protected $classmap = [];
	protected $finder = [];
	private static $instance;
	
	public function __construct() {
		self::$instance = & $this;
		/*try {
			$this->init();
		} catch (InvalidArgumentException $iae) {
			exit($iae->getMessage());
		}*/
	}
	private function __clone() {}
	
	public static function get_instance() {
		if (! self::$instance) self::$instance = new self();
		return self::$instance;
	}
	
	/**
	 * 注册加载器至命名空间（SPL）自动加载栈
	 * @param boolean Whether to prepend the autoloader or not
	 * @access public
	 */
	public function register($loader=null, $prepend=false) {
		if ($loader instanceof \Closure) {
			return spl_autoload_register($loader, true, (boolean) $prepend);
		}
		return spl_autoload_register([$this, 'load'], true, $prepend);
	}
	
	/**
	 * 卸载加载器从命名空间（SPL）自动加载栈
	 * @access public
	 */
	public function unregister($loader=null) {
		if ($loader instanceof \Closure) {
			return spl_autoload_unregister($loader);
		}
		return spl_autoload_unregister([$this, 'load']);
	}

	/**
	 * 自定义查找器
	 * @access public
	 */
	public function custom_finder($finder) {
		if ($finder instanceof \Closure) {
			$this->finder[] = $finder;
		} else if (is_array($finder)) {
			$this->finder = $finder;
		}
	}

	/**
	 * {@inheritdoc}
	 */
	public function add($name, $path) {
		$this->classmap[$name] = $path;
		return $this;
	}
	
	/**
	 * {@inheritdoc}
	 */
	public function import(array $maps) {
		foreach ($maps as $name => $path) {
			$this->add($name, $path);
		}
		return $this;
	}
	
	/**
	 * {@inheritdoc}
	 */
	public function has($name) {
		return (array_key_exists($name, $this->classmap));
	}
	
	/**
	 * 检查一个  class, interface 或者 trait 是否已经加载
	 * @param  string $class
	 * @return boolean
	 */
	protected function is_loaded($class) {
		return (
				class_exists($class, false) ||
				interface_exists($class, false) ||
				trait_exists($class, false)
		);
	}
	
	/**
	 * 加载类或接口调用的方法
	 * @param string class name
	 * @return boolean true if class loaded or false in otherwise
	 */
	public function load($class) {
		if ($this->is_loaded($class)) {
			return true;
		}
		if (! empty($this->finder) && is_array($this->finder)) {
			foreach ($this->finder as $finder) {
				if ($file = call_user_func($finder, $class)) {
					if (file_exists($file)) {
						require($file);
						return true;
					}
				}
			}
			
		}
		if ($file = $this->search($class)) {
			if (is_file($file)) {
				include($file);
				return true;
			}
		}
		return false;
	}
	
	/**
	 * Search for classes
	 * @param string class name
	 * @access private
	 * @return string class path or false in otherwise
	 */
	private function search($class) {
		// Firstly: if class exists in map return to class path
		if (array_key_exists($class, $this->classmap)) {
			return $this->classmap[$class];
		}
		
		// Secondly: if class not exists in map
		// Checking if class loaded as PSR-0 standard or Set class name with .php suffix
		$position = strrpos($class, '\\');
		$classpath = (false !== $position) ? $this->find($class) : $class.'.php';
		
		return $classpath;
	}
	
	public function find_by_psr($class, $path) {
		return rtrim($path, '/') . DIRECTORY_SEPARATOR . str_replace(['_', '/', '\\'], DIRECTORY_SEPARATOR, $class) . '.php';
		
	}
	
	public function find($classname) {
		$parts = explode('\\', ltrim($classname, '\\'));
		if (false !== strpos(end($parts), '_')) {
			array_splice($parts, -1, 1, explode('_', current($parts)));
		}
		$filename = implode(DIRECTORY_SEPARATOR, $parts) . '.php';
		
		if ($filename = stream_resolve_include_path($filename)) {
			return $filename;
		} else {
			return false;
		}
	}
	
	public function __invoke($classname) {
		require $this->find($classname);
	}
	
	/**
	 * 创建一个命名空间别名
	 * @param string  The original class
	 * @param string  The new namespace for this class
	 * @param boolean Put original class name with alias by default false
	 * @access public
	 */
	public function namespace_alias($original, $alias=null, $with_original=false) {
		$alias = ((isset($alias)) ? rtrim($alias, '\\') : $alias);
		if ($with_original) {
			// Get clean class name without any namespaces
			$exp = explode('\\', $original);
			$alias = array_pop($exp).'\\'.$alias;
		}
		class_alias($original, $alias);
	}
	
	public static function memcache($config=array()) {
		if (is_null($config)) $config = array();
		if (array_key_exists('memcache', $GLOBALS['config']))
			$config = array_merge((array) $GLOBALS['config']['memcache'], (array) $config);
		else
			$config = (array) $config;
		return new MemcacheHelper($config);
	}
	
	/**
	 * 加载视图
	 * @param string $path view名
	 * @param array $args 传递给view的变量
	 * @return bool view 是否载入成功
	 */
	public static function view($path, $args=array(), $config=array()) {
		
		if (func_num_args() == 0) {
			trigger_error(sprintf('未指定视图路径：%s', $path), E_USER_WARNING);
			return false;
		}
		
		$cache = false; //(array_key_exists('memcache', $GLOBALS['config']));
		if ($cache) {
			$memcache = self::memcache();
			$key = Request::url() . '#' . $path;
			if ($memcache and $cache = $memcache->get($key))
				return $cache;
		}
		
		if (is_null($config)) $config = array();
		if (array_key_exists('template', $GLOBALS['config']))
			$config = array_merge((array) $GLOBALS['config']['template'], (array) $config);
		else
			$config = (array) $config;
		$view = new View($config);
		$return =  $view->show($path, $args);
		if ($cache and $memcache and $return) $memcache->add($key, $return);
		
		return $return;
	}


}