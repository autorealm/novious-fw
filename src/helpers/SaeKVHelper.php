<?php

class SaeKVHelper {
	private static $_storage;
	public static $cache = array();
	protected $handler;
	private $config = array (
		'prefix' => '',
		'expire' => 7200,
		'length' => 0,
		'access_key' => '',
		'secret_key' => ''
		);

	public function __construct() {
		if (! self::$_storage) {
			self::$_storage = new SaeKV();
		}
		$this->handler = self::$_storage;
	}

	public function connect($options=array()) {
		if (is_array($options))
			$this->config = array_merge($this->config, $options);
		
		return $this->handler->init();;
	}
	
	public function query($limit=100, $prefix='', $start='') {
		$this->handler->pkrget($prefix, $limit, $start);
	}
	
	public function put($key, $value) {
		self::$cache[$key] = $value;
		return $this->handler->add($key, $value);
	}
	
	public function get($key) {
		if (array_key_exists($key, self::$cache)) return self::$cache[$key];
		$value = $this->handler->get($key);
		self::$cache[$key] = $value;
		return $value;
	}
	
	public function mget($keys) {
		return $this->handler->mget($keys);
	}
	
	public function set($key, $value) {
		self::$cache[$key] = $value;
		return $this->handler->set($key, $value);
	}
	
	public function remove($key) {
		unset(self::$cache[$key]);
		return $this->handler->delete($key);
	}
	
	public function replace($key, $value) {
		unset(self::$cache[$key]);
		self::$cache[$value] = $key;
		return $this->handler->replace($key, $value);
	}
	
	public function clear() {
		self::$cache = array();
		$ret = $this->handler->pkrget('', 100);
		while (true) {
			end($ret);
			$start_key = key($ret);
			$i = count($ret);
			foreach ($ret as $k => $v) {
				$this->remove($k);
			}
			if ($i < 100) break;
			$ret = $this->handler->pkrget('', 100, $start_key);
		}
		return true;
	}
	
	/**
	 * 队列缓存
	 */
	protected function queue($key, $value) {
		if (!$value) {
			$value = array();
		}
		if (!array_search($key, $value)) array_push($value, $key);
		if (count($value) > $this->config['length']) {
			$key = array_shift($value);
			$this->remove($key);
		}
		
		return true;
	}
	
	public function __call($method, $args) {
		if(method_exists($this->handler, $method)) {
			return call_user_func_array(array($this->handler,$method), $args);
		} else {
			return false;
		}
	}

}