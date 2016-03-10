<?php
use sinacloud\sae\Storage as Storage;

class StorageHelper {
	const STORAGE_TYPE_MIXED = 0;
	const STORAGE_TYPE_FILE = 1;
	const STORAGE_TYPE_STRING = 2;
	private static $_storage;
	public static $cache = array();
	protected $handler;
	private $bucket = '';
	private $config = array (
		'prefix' => '',
		'delimiter' => null,
		'expire' => 7200,
		'length' => 0,
		'bucket' => '',
		'path'  => '/assets/cache/',
		'access_key' => '',
		'secret_key' => ''
	);

	public function __construct() {
		if (! self::$_storage) {
			self::$_storage = new Storage();
		}
		$this->handler = self::$_storage;
	}
	
	public static function make() {
		return new self();
	}
	
	public function connect($options=array()) {
		if (is_array($options))
			$this->config = array_merge($this->config, $options);
		
		//$handler->setAuth($this->config['access_key'], $this->config['secret_key']);
		try {
			return $this->handler->getBucketInfo($this->bucket);
		} catch (ErrorException $e) {
			//exit($e->getMessage());
			return true;
		}
		
	}

	public function query($limit=20, $start=null) {
		return $this->handler->getBucket($this->bucket, $this->config['prefix'], $start, $limit, $this->config['delimiter']);
	}
	
	public function get($key, $full=false) {
		if ($full) {
			return $this->handler->getObject($this->bucket, $key);
		} else {
			if (array_key_exists($key, self::$cache)) return self::$cache[$key .'@'. $this->bucket];
			$content = $this->body($key);
			self::$cache[$key .'@'. $this->bucket] = $content;
			return $content;
		}
	}

	public function put($key, $value, $type=self::STORAGE_TYPE_STRING) {
		if ($type == self::STORAGE_TYPE_FILE) {
			return $this->handler->putObjectFile($value, $this->bucket, $key);
		} else if ($type == self::STORAGE_TYPE_STRING) {
			self::$cache[$key .'@'. $this->bucket] = $value;
			return $this->handler->putObjectString($value, $this->bucket, $key);
		} else {
			return $this->handler->putObject($value, $this->bucket, $key);
		}
	}
	
	public function with($bucket, $check=true) {
		//检查 Bucket 是否存在，否则自动创建，此操作较耗时。
		if ($check) {
			try {
				$ret = $this->handler->getBucket($bucket, $this->config['prefix'], null, 1, $this->config['delimiter']);
			} catch (ErrorException $e) {
				//exit($e->getMessage());
			}
			if (! $ret) $ret = $this->handler->putBucket($bucket, Storage::ACL_PUBLIC_READ);
		}
		$this->config['bucket'] = $bucket;
		$this->bucket = $bucket;
		return $this->handler;
	}
	
	public function remove($key) {
		return $this->handler->deleteObject($this->bucket, $key);
	}
	
	public function clear($delete=false) {
		self::$cache = array();
		$objects = $this->handler->getBucket($this->bucket, $this->config['prefix'], null, 9999, $this->config['delimiter']);
		foreach ($objects as $key => $value) {
			$this->remove($key);
		}
		if ($delete) $this->handler->deleteBucket($this->bucket);
		return true;
	}
	
	public function copy($from, $to) {
		return $this->handler->copyObject($this->bucket, $from, $this->bucket, $to);
	}
	
	public function get_info($key) {
		return $this->handler->getObjectInfo($this->bucket, $key);
	}
	
	public function get_url($key) {
		return $this->handler->getUrl($this->bucket, $key);
	}
	
	public function exists($key) {
		return (array_key_exists($key .'@'. $this->bucket, self::$cache) or $this->get_info($key)) ? true : false;
	}
	
	public function time($key) {
		if ($info = ($this->get_info($key))) {
			$mtime = $info['time'];
			return $mtime;
		} else {
			return 0;
		}
	}
	
	public function size($key) {
		if ($info = ($this->get_info($key))) {
			$msize = $info['size'];
			return $msize;
		} else {
			return false;
		}
	}
	
	public function type($key) {
		if ($info = ($this->get_info($key))) {
			$mtype = $info['type'];
			return $mtype;
		} else {
			return false;
		}
	}
	
	public function body($key) {
		if ($info = $this->handler->getObject($this->bucket, $key)) {
			$mbody = $info->body;
			return $mbody;
		} else {
			return false;
		}
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
