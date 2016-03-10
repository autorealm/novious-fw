<?php
//namespace APP;

/**
 * Http 请求类
 */
class Request {
	const OVERRIDE = 'HTTP_X_HTTP_METHOD_OVERRIDE';
	
	protected static $proxies = array(); //受信任的IP地址数组
	protected static $resolvers = array(); //请求分解数组
	protected static $input = array(); //请求数据
	public static $data = array(); //附加数据
	
	public static $_instance = null;
	
	private function __construct() {}
	
	private function __clone() {}
	
	/**
	 * 单例获取类实例
	 */
	public static function get_instance() {
		if(! (self::$_instance instanceof self) ) {
			self::$_instance = new self;
		}
		return self::$_instance;
	}
	
	/**
	 * 获取请求参数
	 */
	public static function request($name, $default = null) {
		return static::lookup($_REQUEST, $key, $default);
	}
	
	/**
	 * 设置额外请求数据
	 */
	public static function set($data, $overwrite=true) {
		if ($overwrite)
			self::$data = $data;
		else
			self::$data = array_merge(self::$data, (array) $data);
	}
	
	/**
	 * 返回额外请求数据
	 */
	public static function data() {
		return self::$data;
	}
	
	/**
	 * 返回一个 GET 请求数据
	 * @param string $key
	 * @param string $default
	 */
	public static function get($key = NULL, $default = NULL) {
		return static::lookup($_GET, $key, $default);
	}
	
	/**
	 * 返回一个 POST 请求数据
	 * @param string $key
	 * @param string $default
	 */
	public static function post($key = NULL, $default = NULL) {
		return static::lookup($_POST, $key, $default);
	}
	
	/**
	 * 返回一个 STREAM 请求数据
	 */
	protected static function stream($key, $default) {
		if (Request::overridden())
			return static::lookup($_POST, $key, $default);
	
		parse_str(file_get_contents('php://input'), $input);
		return static::lookup($input, $key, $default);
	}
	
	/**
	 * 返回一个 PUT 请求数据
	 */
	public static function put($key = NULL, $default = NULL) {
		return static::method() === 'PUT' ?
		static::stream($key, $default) : $default;
	}
	
	/**
	 * 返回一个 DELETE 请求数据
	 */
	public static function delete($key = NULL, $default = NULL) {
		return static::method() === 'DELETE' ?
		static::stream($key, $default) : $default;
	}
	
	/**
	 * 返回一个请求数据
	 */
	public static function input($key = NULL, $default = NULL) {
		return static::lookup(static::submitted(), $key, $default);
	}
	
	/**
	 * 获取 文件
	 */
	public static function files($key = NULL, $default = NULL) {
		return static::lookup($_FILES, $key, $default);
	}
	
	/**
	 * 判断是否包含该键
	 */
	public static function has($keys) {
		foreach ((array) $keys as $key) {
			if (trim(static::input($key)) == '') return FALSE;
		}
		return TRUE;
	}
	
	/**
	 * 返回仅包含指定键的请求数组
	 */
	public static function only($keys) {
		return array_intersect_key(
				static::input(), array_flip((array) $keys)
		);
	}
	
	/**
	 * 返回不包含指定键的请求数组
	 */
	public static function except($keys) {
		return array_diff_key(
				static::input(), array_flip((array) $keys)
		);
	}
	
	/**
	 * 获取请求方式
	 */
	public static function method() {
		$method = static::overridden() ? (isset($_POST[static::OVERRIDE]) ?
				$_POST[static::OVERRIDE] : $_SERVER[static::OVERRIDE]) :
				$_SERVER['REQUEST_METHOD'];
		return strtoupper($method);
	}
	
	/**
	 * 是否指定的请求方式
	 */
	public static function is($method) {
		if (is_null($method)) return false;
		foreach ((array) $method as $rm) {
			if (strtoupper($rm) == self::method())
				return true;
		}
		return false;
	}
	
	/**
	 * 获取一个 SESSION 值
	 */
	public static function session($key = NULL, $default = NULL) {
		return static::lookup($_SESSION, $key, $default);
	}
	
	/**
	 * 获取一个 COOKiE 值
	 */
	public static function cookie($key = NULL, $default = NULL) {
		return static::lookup($_COOKIE, $key, $default);
	}
	
	/**
	 * 获取一个 SERVER 值
	 */
	public static function server($key = NULL, $default = NULL) {
		return static::lookup($_SERVER, $key, $default);
	}
	
	/**
	 * 获取一个 HEADER 值
	 */
	public static function header($key, $default = null) {
		$key = 'HTTP_' . strtoupper(str_replace('-', '_', $key));
		return static::lookup($_SERVER, $key, $default);
	}
	
	/**
	 * 获取用户代理字符串
	 */
	public static function agent($default = NULL) {
		return static::server('HTTP_USER_AGENT', $default);
	}

	public static function resolvers($resolvers = array()) {
		if ($resolvers || empty(static::$resolvers)) {
			static::$resolvers = $resolvers +
			array(
					'PATH_INFO',
					'REQUEST_URI' => function($uri) {
						return parse_url($uri, PHP_URL_PATH);
					},
					'PHP_SELF',
					'REDIRECT_URL'
							);
		}
	
		return static::$resolvers;
	}
	
	/**
	 * 获取请求完整URL地址
	 */
	public static function url() {
		return static::scheme(TRUE).static::host()
		.static::port(TRUE).static::uri().static::query(TRUE);
	}
	
	/**
	 * 获取请求URI字符串
	 */
	public static function uri() {
		foreach (static::resolvers() as $key => $resolver) {
			$key = is_numeric($key) ? $resolver : $key;
			if (isset($_SERVER[$key])) {
				if (is_callable($resolver)) {
					$uri = $resolver($_SERVER[$key]);
					if ($uri !== FALSE) return $uri;
				} else {
					return $_SERVER[$key];
				}
			}
		}
	}
	
	/**
	 * 获取请求查询数组
	 */
	public static function query($default = NULL) {
		return parse_url(static::server('REQUEST_URI', '/'), PHP_URL_QUERY);
	}
	
	/**
	 * 获取请求内容类型
	 */
	public static function type($default = NULL, $strict = FALSE) {
		$type = static::server('HTTP_CONTENT_TYPE',
				$default ?: 'application/x-www-form-urlencoded');
		if ($strict) return $type;
	
		$types = preg_split('/\s*;\s*/', $type);
		return $types;
	}
	
	public static function scheme($decorated = FALSE) {
		$scheme = static::secure() ? 'https' : 'http';
		return $decorated ? "$scheme://" : $scheme;
	}
	
	public static function secure() {
		if (strtoupper(static::server('HTTPS')) == 'ON')
			return TRUE;
	
		if (!static::entrusted()) return FALSE;
	
		return (strtoupper(static::server('SSL_HTTPS')) == 'ON' ||
				strtoupper(static::server('X_FORWARDED_PROTO')) == 'HTTPS');
	}
	
	/**
	 * 获取一个请求路径分段值
	 */
	public static function segment($index, $default = NULL) {
		$segments = explode('/', trim(parse_url(static::server('REQUEST_URI', '/'), PHP_URL_PATH) ?: array(), '/'));;
	
		if ($index < 0) {
			$index *= -1;
			$segments = array_reverse($segments);
		}
	
		return static::lookup($segments, $index - 1, $default);
	}
	
	public static function host($default = NULL) {
		$keys = array('HTTP_HOST', 'SERVER_NAME', 'SERVER_ADDR');
	
		if (static::entrusted() &&
				$host = static::server('X_FORWARDED_HOST')) {
					$host = explode(',', $host);
					$host = trim($host[count($host) - 1]);
				} else {
					foreach($keys as $key) {
						if (isset($_SERVER[$key])) {
							$host = $_SERVER[$key];
							break;
						}
					}
				}
	
				return isset($host) ?
				preg_replace('/:\d+$/', '', $host) : $default;
	}
	
	/**
	 * 获取客户IP地址
	 */
	public static function ip($trusted = TRUE) {
		$keys = array(
				'HTTP_X_FORWARDED_FOR', 'HTTP_X_FORWARDED',
				'HTTP_FORWARDED_FOR', 'HTTP_FORWARDED'
		);
	
		$ips = array();
	
		if ($trusted && isset($_SERVER['HTTP_CLIENT_IP']))
			$ips[] = $_SERVER['HTTP_CLIENT_IP'];
	
		foreach ($keys as $key) {
			if (isset($_SERVER[$key])) {
				if (static::entrusted()) {
					$parts = explode(',', $_SERVER[$key]);
					$ips[] = trim($parts[count($parts) - 1]);
				}
			}
		}
	
		foreach ($ips as $ip) {
			if (filter_var($ip, FILTER_VALIDATE_IP,
					FILTER_FLAG_IPV4 || FILTER_FLAG_IPV6 ||
					FILTER_FLAG_NO_PRIV_RANGE || FILTER_FLAG_NO_RES_RANGE)) {
						return $ip;
					}
		}
	
		return static::server('REMOTE_ADDR', '0.0.0.0');
	}
	
	/**
	 * 获取请求体
	 * @param string $default
	 */
	public static function body($default = NULL) {
		return file_get_contents('php://input') ?: $default;
	}
	
	protected static function overridden() {
		return isset($_POST[static::OVERRIDE]) || isset($_SERVER[static::OVERRIDE]);
	}
	
	public static function proxies($proxies) {
		static::$proxies = (array) $proxies;
	}
	
	public static function entrusted() {
		return (empty(static::$proxies) || isset($_SERVER['REMOTE_ADDR'])
				&& in_array($_SERVER['REMOTE_ADDR'], static::$proxies));
	}
	
	/**
	 * 合并请求数组
	 * @return multitype:|number
	 */
	protected static function submitted() {
		if (static::$input !== NULL) return static::$input;
	
		parse_str(static::body(), $input);
		return static::$input = (array) $_GET + (array) $_POST + $input;
	}
	
	/**
	 * 获取一个全局数据中的数据
	 */
	protected static function lookup($array, $key, $default) {
		if ($key === NULL) return $array;
		return isset($array[$key]) ? $array[$key] : $default;
	}
	
	/**
	 * 获取请求端口
	 */
	public static function port($decorated = FALSE) {
		$port = static::entrusted() ?
		static::server('X_FORWARDED_PORT') : NULL;
	
		$port = $port ?: static::server('SERVER_PORT');
	
		return $decorated ? (
				in_array($port, array(80, 443)) ? '' : ":$port"
		) : $port;
	}

}