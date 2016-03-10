<?php
//namespace APP;

/**
 * Http 回应类
 *
 */
class Response {
	private static $instance;
	public $headers = array();
	public $status = 200;
	public $buffer = true;
	
	protected $http_status_codes = [
			100 => 'Continue',
			101 => 'Switching Protocols',
			102 => 'Processing',
			200 => 'OK',
			201 => 'Created',
			202 => 'Accepted',
			203 => 'Non-Authoritative Information',
			204 => 'No Content',
			205 => 'Reset Content',
			206 => 'Partial Content',
			207 => 'Multi-Status',
			300 => 'Multiple Choices',
			301 => 'Moved Permanently',
			302 => 'Found',
			303 => 'See Other',
			304 => 'Not Modified',
			305 => 'Use Proxy',
			306 => 'Switch Proxy',
			307 => 'Temporary Redirect',
			400 => 'Bad Request',
			401 => 'Unauthorized',
			402 => 'Payment Required',
			403 => 'Forbidden',
			404 => 'Not Found',
			405 => 'Method Not Allowed',
			406 => 'Not Acceptable',
			407 => 'Proxy Authentication Required',
			408 => 'Request Timeout',
			409 => 'Conflict',
			410 => 'Gone',
			411 => 'Length Required',
			412 => 'Precondition Failed',
			413 => 'Request Entity Too Large',
			414 => 'Request-URI Too Long',
			415 => 'Unsupported Media Type',
			416 => 'Requested Range Not Satisfiable',
			417 => 'Expectation Failed',
			418 => 'I\'m a teapot',
			422 => 'Unprocessable Entity',
			423 => 'Locked',
			424 => 'Failed Dependency',
			425 => 'Unordered Collection',
			426 => 'Upgrade Required',
			449 => 'Retry With',
			450 => 'Blocked by Windows Parental Controls',
			500 => 'Internal Server Error',
			501 => 'Not Implemented',
			502 => 'Bad Gateway',
			503 => 'Service Unavailable',
			504 => 'Gateway Timeout',
			505 => 'HTTP Version Not Supported',
			506 => 'Variant Also Negotiates',
			507 => 'Insufficient Storage',
			509 => 'Bandwidth Limit Exceeded',
			510 => 'Not Extended'
	];
	
	protected $body;
	protected $length;
	
	public $parse_exec_var = true; //是否对输出再解析变量
	
	
	public function __construct() {
		/*$secret = "deadc0de";
	
		$handler = new Cookie($secret);
		session_set_save_handler($handler, true);
		session_start();
		
		$_SESSION["foo"] = "bar";*/
	}
	
	public static function & get_instance() {
		if (! self::$instance)
			self::$instance = new self();
		return self::$instance;
	}
	
	public function header_no_cache() {
		@header("Pragma: no-cache");
		@header('Cache-Control: no-store, no-cache');
	}
	
	public function header_allow_access() {
		@header('Access-Control-Allow-Origin:*');
	}
	
	public function header($name, $value=null) {
		if (! is_null($value)) {
			$this->headers[$name] = $value;
			//$this->headers->set($name, $value);
		}
		
		return $this->headers->get($name);
	}
	
	public function set_cookie($key, $value, $expires = null, $domain = '-', $path = '/') {
		$domain = strtolower($domain);
		if (substr($domain, 0, 1) === '.') {
			$domain = substr($domain, 1);
		}
		if (!isset($this->cookies[$domain])) {
			$this->cookies[$domain] = [];
		}
		if (!isset($this->cookies[$domain][$path])) {
			$this->cookies[$domain][$path] = [];
		}
		$list = &$this->cookies[$domain][$path];
		if ($value === null || $value === '' || ($expires !== null && $expires < time())) {
			unset($list[$key]);
		} else {
			$value = rawurlencode($value);
			$list[$key] = ['value' => $value, 'expires' => $expires];
		}
	}

	public function clear_cookie($domain = '-', $path = null) {
		if ($domain === null) {
			$this->cookies = [];
		} else {
			$domain = strtolower($domain);
			if ($path === null) {
				unset($this->cookies[$domain]);
			} else {
				if (isset($this->cookies[$domain])) {
					unset($this->cookies[$domain][$path]);
				}
			}
		}
	}
	
	public function get_cookie($key, $domain = '-') {
		$domain = strtolower($domain);
		if ($key === null) {
			$cookies = [];
		}
		while (true) {
			if (isset($this->cookies[$domain])) {
				foreach ($this->cookies[$domain] as $path => $list) {
					if ($key === null) {
						$cookies = array_merge($list, $cookies);
					} else {
						if (isset($list[$key])) {
							return rawurldecode($list[$key]['value']);
						}
					}
				}
			}
			if (($pos = strpos($domain, '.', 1)) === false) {
				break;
			}
			$domain = substr($domain, $pos);
		}
		return $key === null ? $cookies : null;
	}
	
	public function cookie($host, $path) {
		$now = time();
		$host = strtolower($host);
		$cookies = [];
		$domains = ['-', $host];
		while (strlen($host) > 1 && ($pos = strpos($host, '.', 1)) !== false) {
			$host = substr($host, $pos + 1);
			$domains[] = $host;
		}
		foreach ($domains as $domain) {
			if (!isset($this->cookies[$domain])) {
				continue;
			}
			foreach ($this->cookies[$domain] as $_path => $list) {
				if (!strncmp($_path, $path, strlen($_path))
					&& (substr($_path, -1, 1) === '/' || substr($path, strlen($_path), 1) === '/')
				) {
					foreach ($list as $k => $v) {
						if (!isset($cookies[$k]) && ($v['expires'] === null || $v['expires'] > $now)) {
							$cookies[$k] = $k . '=' . $v['value'];
						}
					}
				}
			}
		}
		
		return $cookies;
	}
	
	public function set_status($code=200) {
		if (!is_null($status)) {
			$this->status = (int) $status;
		}
		
		return $this->status;
	}
	
	public function headers(array $headers=null) {
		if (is_null($headers)) return $this->headers;
		foreach ((array) $headers as $name => $value) {
			$this->header($name, $value);
		}
	}
	
	public function body($body=null) {
		if (is_null($body)) return $this->body;
		$this->body = $body;
	}
	
	/**
	 * 输出回应
	 */
	public function display($output='') {
		http_response_code(200);
		if (count($this->headers) > 0) {
			foreach ($this->headers as $name => $val) {
				@header($name, $val);
			}
		}
		if (! empty($output)) $output = ltrim($output);
		
		$elapsed = $GLOBALS['app']->elapsed_time();
		$memory = round(memory_get_usage() / 1024 / 1024, 2).'MB';
		
		if ($this->parse_exec_var) {
			$output = str_replace(array('{elapsed_time}', '{memory_usage}'), array($elapsed, $memory), $output);
		}
		
		echo $output;
		
		return;
	}
	
	/**
	 * 重定向
	 */
	public function redirect($url, $status = 302) {
		$this->set_status($status);
		$this->headers->set('Location', $url);
	}
	
	public function write($body, $append = false) {
		if ($append) {
			$this->body .= (string)$body;
		} else {
			$this->body = $body;
		}
		$this->length = strlen($this->body);
	
		return $this->body;
	}
	
	/**
	 * 通用接口方法
	 * @param $code
	 * @param string $message
	 * @param array $data
	 * @param string $type
	 */
	public static function response($code , $message = '', $data = array(), $type = 'json') {
		if(!is_numeric($code)) {
			return;
		}
		$type = isset($_GET['format']) ? strtolower($_GET['format']) : 'json';
		$result = array(
			'code' => $code,
			'message' => $message,
			'data' => $data,
		);
		if($type == 'json') {
			self::json($code, $message, $data);
			exit;
		} else if($type == 'array') {
			var_dump($result);
		} else if($type == 'xml'){
			self::xml($code, $message, $data);
			exit;
		} else {
			//TODO
		}
	}
	
	/**
	 *
	 * 按json方式返回
	 * @param $code  返回代码
	 * @param string $message 提示信息
	 * @param array $data  数据
	 * @return string|void
	 */
	public static function json($code, $message = '', $data = array()) {
		if(!is_numeric($code)) {
			return;
		}
		$result = array (
			'code' => $code,
			'message' => $message,
			'data' => $data,
		);
		echo json_encode($result);
		exit;
	}
	/**
	 *
	 * 按xml方式返回
	 * 有三种方式转化为xml
	 * @param $code
	 * @param string $message
	 * @param array $data
	 */
	public static function xml($code, $message = '', $data = array()) {
		if(!is_numeric($code)) {
			return ;
		}
		$result = array(
			'code' => $code,
			'message' => $message,
			'data'  => $data,
		);
		header("Content-Type:text/xml");
		$xml = "<?xml version='1.0' encoding='UTF_8'?>";
		$xml .= "<root>";
		$xml .= self::xml_encode($result);
		$xml .="</root>";
		echo $xml;
	   // exit;
	}
	
	public static function xml_encode($data) {
		$xml = $attr = "";
		foreach($data as $key => $value) {
			//处理xml不识别数字节点
			if(is_numeric($key)) {
				$attr = " id='{$key}'";
				$key = "item";
			}
			$xml .= "<{$key}{$attr}>";
			$xml .= is_array($value) ? self::xml_encode($value) : $value; //递推处理
			$xml .= "</{$key}>\n";
		}
		return $xml;
	}

}