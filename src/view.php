<?php

/**
 * 视图类
 */
class View {
	protected static $config = array();
	private $data = array();
	private $path;
	private $engine;
	private $engine_config = array();
	public $cache = false;
	public $storage;
	
	public function __construct($config=array()) {
		if (! self::$config or empty(self::$config)) {
			self::$config = array();
			self::$config['default'] = array(
					'suffix'  => '.view.php',
					'template_path'  =>  ROOT_DIR . '/views', //模板文件夹路径
					'storage_path'  =>  'cache', //模板文件夹缓存路径
					'bucket' => 'templates',
					'engine'  =>  'default', //模板引擎名称
					'expire'  =>  5000, //过期时间（秒）
			);
			self::$config['nature'] = array(
					'suffix'  => '.php',
					'template_path'  =>  ROOT_DIR . '/views', //模板文件夹路径
					'storage_path'  =>  'cache', //模板文件夹缓存路径
					'bucket' => 'templates',
					'engine'  =>  false, //模板引擎名称
					'expire'  =>  0, //过期时间（秒）
			);
		}
		$config = (array) $config;
		$isdis = false; //判断是否是二维数组
		foreach($config as $c) {
			if (is_array($c)) $isdis = true;
			break;
		}
		if (! $isdis) $_config['engine'] = $config;
		else $_config = $config;
		self::$config = array_merge(self::$config, $_config);
		
		$this->storage = StorageHelper::make();
	}
	
	protected function init_engine($engine) {
		if (! is_array($engine)) {
			$this->engine = null;
			return;
		}
		$this->engine_config = $engine;
		$bucket = (array_key_exists('bucket', $engine)) ? $engine['bucket'] : 'views';
		$this->storage->with($bucket, false);
		
		/* 使用 blade 模板 */
		if (strtolower($engine['engine']) == 'blade') {
			
			if ( ! defined('CRLF')) {
				define('CRLF', "\r\n");
			}
			if ( ! defined('DS')) {
				define('DS', DIRECTORY_SEPARATOR);
			}
			if ( ! defined('BLADE_EXT')) {
				$blade_ext = ($engine['suffix']) ? $engine['suffix'] : '.blade.php';
				define('BLADE_EXT', $blade_ext);
			}
			if ( ! defined('TEMPLATE_PATH')) {
				$template_path = ($engine['template_path']) ? $engine['template_path'] : '/templates';
				define('TEMPLATE_PATH', $template_path);
			}
			
			$storage_path = ($engine['storage_path']) ? $engine['storage_path'] : 'views';
			set_storage_path($storage_path);
			set_storage($this->storage);
			
			$this->engine = $this->blade();
			
		} else if ($engine['engine']) {
				
			$this->engine = $engine['engine'];
		} else {
			
			$this->engine = null;
		}
	}
	
	public function __get($name) {
		return $this->data[$name];
	}
	
	public function __set($name, $value) {
		$this->data[$name] = $value;
	}
	
	public static function make($template, $data=array(), $config=array()) {
		$view = new self($config);
		
		return $view->show($template, $data);
	}
	
	private function blade() {
		$libraries = array( 'laravel/blade', 'laravel/section', 'laravel/view', 'laravel/event' );
		foreach ( $libraries as $filename ) {
			$file = rtrim(LIBRARY_PATH, '/') . '/' . $filename . '.php';
			require_once($file);
		}
		
		Laravel\Blade::sharpen();
		
		return 'blade';
	}
	
	public function show($template, $data=array()) {
		$engine = $this->path($template, false);
		if ($engine) $engine = self::$config[$engine];
		else $engine = null;
		$this->init_engine($engine);
		
		$this->data = array_merge($this->data, $data);
		$data = $this->data;
		$this->path = $this->path($template, true);
		//echo $template; var_dump($this->path);
		
		if (starts_with($template, ['http:', 'https:', 'ftp:', 'file:'])) {
			$this->path = $template;
			return  file_get_contents($template);
		}
		
		if ($this->path == false) {
			trigger_error(sprintf('模板加载出错：%s', $template), E_USER_WARNING);
		}
		
		$cache = (array_key_exists('cache', $this->engine_config)) ? $this->engine_config['cache'] : $this->cache;
		if ($cache) {
			$compiled_path = $this->compiled();
			if ($this->path and ! $this->expired()) {
				return $this->storage->get($compiled_path);
			}
		}
		
		if(! file_exists($this->path)) {
			trigger_error(sprintf('未找到模板文件：%s', $this->path), E_USER_WARNING);
			return false;
		}
		
		if ($this->engine == 'default') {
			$view = ViewEngine::make( $this->path, $data );
			$view->config($engine);
			
			$output = $view->render();
			
		} else if ($this->engine == 'blade') {
			$view = Laravel\View::make( 'path: '.$this->path, $data );
			
			$output = $view->render();
			
			$this->data = $view->data();
			$this->path = $this->url($view->path);
			
		} else if ($this->engine == 'tonic') {
			$tpl = new Tonic();
			$tpl = $tpl->load($this->path);
			if ($tpl) {
				$tpl->setContext($data);
				$output = $tpl->render();
			} else {
				$output = false;
			}
		} else if ($this->engine == 'text') {
			if (is_file($this->path)) {
				$output = file_get_contents($this->path);
			} else {
				$output = false;
			}
		} else if ($this->engine) {
			
			var_dump(self);
			
		} else {
			ob_start() and ob_clean();
			
			include ($this->path);
			
			$output = ob_get_clean();
		}
		
		if ($cache and $output) $this->storage->put($compiled_path, $output);
		return $output;
	}
	
	public function display($template, $date=array()) {
		$content = $this->show($template, $date);
		if ($content === false) {
			exit();
		} else {
			echo $content;
			@ob_flush();
		}
	}
	
	public function exists($path) {
		return $this->storage->exists($path);
	}
	
	public function path($path, $rte=true) {
		$_path = trim($path, DIRECTORY_SEPARATOR);
		foreach (self::$config as $name => $config) {
			$suffix = (array_key_exists('suffix', $config)) ? $config['suffix'] : '';
			$template_path = (array_key_exists('template_path', $config)) ? $config['template_path'] : '';
			$suffixs = (array) $suffix;
			foreach ($suffixs as $suffix) {
				if (! empty($ext = pathinfo($_path, PATHINFO_EXTENSION))) {
					$ext = '.'.$ext;
					if (starts_with($suffix, $ext))
						$suffix = str_ireplace($ext, '', $suffix);
				}
				$file = $template_path . DIRECTORY_SEPARATOR . $_path . $suffix;
				if (file_exists($file)) {
					if ($rte) return $file;
					else return $name;
				}
			}
		}
		
		if ($rte) return $path;
		else return false;
		
	}
	
	public function url($path) {
		return $this->storage->get_url($path);
	}
	
	public function load($path) {
		return $this->storage->body($path);
	}
	
	public function compiled($path=null) {
		$storage_path = (array_key_exists('storage_path', $this->engine_config)) ? $this->engine_config['storage_path'] : 'views';
		if (! is_null($path))
			return $storage_path.'/'.md5($path);
		else
			return $storage_path.'/'.md5(Request::url());
	}
	
	public function expired($path=null) {
		$expire = (array_key_exists('expire', $this->engine_config)) ? $this->engine_config['expire'] : '0';
		$expire = intval($expire);
		$time = $this->storage->time($this->compiled($path));
		//echo "\n{$time} : {$expire} : ".time();
		if ($expire > 0) {
			if (! $time) return true;
			if ($time + $expire < time()) return true;
			else if (is_null($path)) return false;
		}
		if (is_null($path)) {
			if ($expire <= 0) return false;
			else return true;
		}
		$ftime = filemtime($path);
		//echo " : {$ftime}\n";
		return $ftime > $time;
	}
	
}

/**
 * 模板引擎类
 */
class ViewEngine implements \IteratorAggregate {
	public static $default_extension='.html';
	private static $modifiers = array();
	private static $obbonce = true;
	private static $data = array();
	
	private $_vars = array();
	private $_config = array();
	private $_trace = array();
	
	private $file;
	private $path = '';
	private $cache = false;
	private $cache_path = '';
	private $expire = 0;
	protected $content = '';
	
	public function __construct($config=array()) {
		$_vars = new ArrayObject(array(), ArrayObject::ARRAY_AS_PROPS);
		
		if(!isset($config) && file_exists(ROOT_PATH.'/config/templates.xml')){
			//获取系统变量
			$_sxe = simplexml_load_file(ROOT_PATH.'/config/templates.xml');
			$_tagLib = $_sxe->xpath('/root/taglib');
			foreach ($_tagLib as $_tag) {
				$this->_config["{$_tag->name}"] = $_tag->value;
			}
		} else {
			//$this->_config = $config;
			$this->_config = $config + $this->_config;
		}
		
		
	}
	
	public static function make($file, $data=array(), $config=array()) {
		$self = new self($config);
		$self->file = $file;
		$self->assign($data);
		
		return $self;
	}
	
	/**
	 * 获取变量循环迭代器
	 * @return \ArrayIterator
	 */
	public function getIterator() {
		return $this->_vars->getIterator();
	}

	/**
	 * @param string|int $name
	 * @return mixed
	 */
	public function __get($name)
	{
		return $this->_vars[$name];
	}

	/**
	 * @param string|int $name
	 * @param mixed $value
	 */
	public function __set($name, $value)
	{
		$this->_vars[$name] = $value;
	}

	/**
	 * @param string|int $name
	 * @return bool
	 */
	public function __isset($name)
	{
		return isset($this->_vars[$name]);
	}

	// 接收要注入的变量
	public function assign($key, $value=null) {
		if (is_array($key) or ($key instanceof \Traversable)) {
			foreach ($key as $k => $v) {
				$this->_vars[$k] = $v;
			}
		} else if(isset($key) && !empty($key)) {
			$this->_vars[$key] = $value;
			
		}

		return $this;
	}
	
	public function config($config=null) {
		if (is_array($config)) {
			$this->_config = array_merge($this->_config, $config);
		}
		return $this->_config;
	}
	
	public function data($thiss=true) {
		if ($thiss) {
			return $this->_vars;
		} else {
			self::$data = array_merge(self::$data, $this->vars);
			return self::$data;
		}
		
	}
	
	public function file() {
		return $this->file;
	}
	
	public function path() {
		if (array_key_exists('template_path', $this->_config))
			$this->path = $this->_config['template_path'];
		$this->path = rtrim($this->path, '/') . '/';
		return $this->path;
	}
	
	public function cache_path() {
		if (array_key_exists('cache_path', $this->_config))
			$this->path = $this->_config['cache_path'];
		$this->cache_path = rtrim($this->cache_path, '/') . '/';
		return $this->cache_path;
	}
	
	public function expire() {
		if (array_key_exists('expire', $this->_config))
			$this->expire = $this->_config['expire'];
		return $this->expire;
	}
	
	public function cache() {
		if (array_key_exists('cache', $this->_config))
			$this->cache = $this->_config['cache'];
		return $this->cache;
	}
	
	public static function exists($file, $path='', $suffix='') {
		if (file_exists($file)) return $file;
		$ext = (! empty($suffix)) ? $suffix : '.view.php';
		$exts = (array) $ext;
		foreach ($exts as $ext) {
			if (! empty($_ext = pathinfo($file, PATHINFO_EXTENSION))) {
				$_ext = '.'.ltrim($_ext, '.');
				if (starts_with($ext, $_ext))
					$ext = str_ireplace($_ext, '', $ext);
			}
			$file = $path . $file . $ext;
			if (file_exists($file)) break;
		}
		if (! file_exists($file))
			return false;
		else
			return $file;
	}
	
	public function fetch($file=null, $include=true) {
		if ($file != null)
			$this->file = $file;
		if (empty($this->file)) return false;
		if ($file = self::exists($this->file, $this->path(), $this->_config['suffix'])) {
			$this->file = $file;
		} else {
			return false;
		}
		
		if ($include) {
			ob_start() and ob_clean();     // Start output buffering
			extract($this->_vars);         // Extract the vars to local namespace
			include $this->file;           // Include the file
			$contents = ob_get_contents(); // Get the contents of the buffer
			ob_end_clean();                // End buffering and discard
		} else {
			$contents = file_get_contents($this->file);
		}
		return $contents;              // Return the contents
	}
	
	function parse_import() {
		
	}
	
	public function render($file=null, $onlyparse=false) {
		$this->_trace['begin'] = microtime();
		
		$contents = $this->fetch($file, true);
		
		// 读取模板文件
		$_tplfile = $this->file;
		if(!file_exists($_tplfile)) {
			trigger_error(sprintf('未找到模板文件：%s', $_tplfile), E_USER_WARNING);
			return false;
		}
		// 生成缓存文件
		$_cacheFile = $this->cache_path().md5($file).'.html';
		if ($this->cache()) {
			//判断是否存在缓存文件
			if (file_exists($_cacheFile)) {
				if (filemtime($_cacheFile) >= filemtime($_tplfile)) {
					include $_cacheFile;
					return true;
				}
			}
		}
		
		// 载入模板解析类
		$parser = new ViewParser($contents, $this);
		//if (self::$obbonce) { self::$obbonce = false;
		$contents = $parser->parse();
		if ($onlyparse) return $contents;
		
		ob_start() and ob_clean();
		
		extract($this->data(), EXTR_OVERWRITE);
		
		try {
			eval('?>'.$contents);
		} catch (\Exception $e) {
			ob_end_clean(); throw $e;
		}
		
		if ($this->cache()) {
			//获取缓冲区内的数据，并且创建缓存文件
			file_put_contents($_cacheFile, ob_get_contents());
		}
		
		$contents = ob_get_clean();
		//} else { $contents = $parser->parse(); }
		$this->_trace['end'] = microtime();
		
		$this->content = $contents;
		return $contents;
	}
	
	public function render_end() {
		
	}
	
	public static function extend_modifier($name, $func) {
		if(empty($name))
			return false;
		if(!is_callable($func))
			return false;
		self::$modifiers[$name] = $func;
		return true;
	}
	
	/*
	 *清理缓存的html文件
	 *@param null $path
	 */
	public function clean($path=null) {
		if ($path == null) {
			$path = $this->cache_path();
			$path = glob($path . '.*.html');
		} else {
			$path = $this->cache_path().md5($path) . '.html';
		}
		foreach ((array) $path as $file) {
			unlink($file);
		}
	}
	
}

/**
 * 模板解析类
 */
class ViewParser {
	private $engine = null;
	private $content = '';
	private $_prefix = '\{\{[\s]{0,}';
	private $_suffix = '[\s]{0,}\}\}';
	private $_prefix_2 = '\{\%[\s]{0,}';
	private $_suffix_2 = '[\s]{0,}\%\}';
	private $_prefix_3 = '\{#[\s]{0,}';
	private $_suffix_3 = '[\s]{0,}#\}';
	private $_patten = [];
	private $_match = [];
	private $_patten_extends = '';
	private $_match_extends = '';
	private $_patten_yield = '';
	private $_patten_section = '';
	private $_patten_literal = '';
	private $_patten_comment = '';
	
	private $_patten_import = '';
	private $_patten_macro = '';
	private $_patten_filter = '';
	
	private static $sections = array();
	private $macros = array();
	
	public function __construct($contents, $engine=null) {
		$this->engine = $engine;
		$this->content = $contents;
		$this->init();
	}
	
	private function init() {
		$_prefix = & $this->_prefix;
		$_suffix = & $this->_suffix;
		$_prefix_2 = & $this->_prefix_2;
		$_suffix_2 = & $this->_suffix_2;
		$_patten = & $this->_patten;
		$_match = & $this->_match;
		
		$_patten[] = '/' .$_prefix. 'if\s+(.*)' .$_suffix. '/isU';
		$_match[] = "<?php if ($1) {?>";
		
		$_patten[] = '/' .$_prefix. 'else' .$_suffix. '/i';
		$_match[] = "<?php } else {?>";
		
		$_patten[] = '/' .$_prefix. 'end[\s]{0,}[\w]+' .$_suffix. '/i';
		$_match[] = "<?php }?>";
		
		$_patten[] = '/' .$_prefix. 'else[\s]{0,}if\s+(.*)' .$_suffix. '/isU';
		$_match[] = "<?php } elseif ($1) {?>";
		
		$_patten[] = '/' .$_prefix. 'foreach\s+\$([\w\_\-\>\:\d]+)\(([\w\_\d]+),([\w\_\d]+)\)' .$_suffix. '/isU';
		$_match[] = "<?php foreach(\$$1 as \$$2=>\$$3) { ?>";
		
		$_patten[] = '/' .$_prefix. '(?:foreach.*|for.*|switch.*|while.*|do.*)' .$_suffix. '/isU';
		$_match[] = "<?php $1 { ?>";
		
		$_patten[] = '/' .$_prefix. 'include\s+[\"|\']([\w\.\-\_\d]+)[\"|\']' .$_suffix. '/i';
		$_match[] = "<?php include '$1';?>";
		
		//【注意】: 使用 ([\s\S]+) 比  (.*) 更耗时。
		$_patten[] = '/'.$_prefix. '(\$[\w\_\-\>\:\d]+)(?:\s(.+)?|\s*?)[\s]{1,}or[\s]{1,}(.+)?' .$_suffix. '/i';
		$_match[] = "<?php if ($1 $2) { echo $1; } else { echo $3; }?>";
		
		$_patten[] = '/[ \t]{0,}'.$_prefix. '(\$.+?)' .$_suffix. '/';
		$_match[] = "<?php echo $1;?>";
		
		$_patten[] = '/'.$_prefix. '(.+?)' .$_suffix. '/';
		$_match[] = "<?php $1;?>";
		
		$_patten[] = '/<!--\{([\w]+)\}-->/';
		$_match[] = "<?php echo\$GLOBALS['$1'];?>";
		
		$this->_patten_extends = '/' .$_prefix_2. 'extends\s+file=\"([\w\/\.\-\_\d]+)\"(?:\s+as=\"(.*)\"|)' .$_suffix_2. '/iU';
		$this->_match_extends = '/<!--[\s]{0,}' .$_prefix_2. '#([\w\/\.\-\_\d]+)' .$_suffix_2. '[\s]{0,}-->/';
		
		$this->_patten_include = '/[ \t]{0,}' .$_prefix_2. 'include\s+file=\"([\w\/\.\-\_\d]+)\"(?:\s+as=\"(.*)\"|)(?:\s+with=(\{.*\})|)(?:\s+(only)|)' .$_suffix_2. '/iU';
		$this->_match_include = '';
		
		$this->_patten_yield = '/[ \t]{0,}' .$_prefix_2. 'yield\s+name=\"([\w\/\.\-\_\d]+)\"' .$_suffix_2. '/i';
		$this->_patten_section = '/' .$_prefix_2. 'section\s+name=\"([\w\/\.\-\_\d]+)\"' .$_suffix_2. '([\s\S]*?)' .$_prefix_2. 'endsection' .$_suffix_2. '/i';
		
		$this->_patten_literal = '/' .$_prefix_2. 'literal(?:\s+as=\"(.*)\"|)' .$_suffix_2. '([\s\S]*?)' .$_prefix_2. 'endliteral' .$_suffix_2. '/i';
		
		$this->_patten_comment = '/' .$this->_prefix_3. '(.*)' .$this->_suffix_3. '/U';
		
		$this->_patten_import = '/' .$_prefix_2. 'import\s+file=\"([\w\/\.\-\_\d]+)\"\s+as=\"([\w\_\d]+)\"' .$_suffix_2. '/iU';
		$this->_patten_macro = '/' .$_prefix_2. 'macro\s+([\w\_\d]+)\((?:(.+)?|)\)' .$_suffix_2. '([\s\S]*?)' .$_prefix_2. 'endmacro' .$_suffix_2. '/i';
		$this->_patten_filter = '/' .$_prefix. '(.*?)\|(\w+)(?:\:([^\"].*?[^\"])|)(?:\|(\w+)(?:\:([^\"].*?[^\"])|)|)' .$_suffix. '/';
		
	}
	
	public static function import($body) {
		if (empty($body)) return [];
		$self = new self($body);
		$self->parse_macro();
		
		return $self->macros;
	}
	
	/**
	 * 解析模板内容
	 */
	public function parse($content=null) {
		if (! empty($content))
			$this->content = $content;
		
		$this->parse_literal();
		
		$this->parse_comment();
		
		$this->parse_section();

		$this->parse_extends();
		$this->parse_include();
		
		$this->parse_yield();
		
		$this->parse_import();
		
		$this->parse_content();
		
		//file_put_contents($_parfile, $this->_tpl);
		
		//unset($sections);
		//if ($this->content) $this->content = ltrim($this->content);
		return $this->content;
	}

	
	private function parse_extends() {

		if (preg_match($this->_patten_extends, $this->content, $math)) {
			$this->content = preg_replace($this->_patten_extends, '', $this->content);
			//var_dump($math);
			if ($this->engine != null) $data = & $this->engine->data();
			$temp = $math[1];
			$as = $math[2] ? $math[2] : '_vacancy';
			$math = '/' . preg_replace('/\//', '\/', $math[0]) . '/';
			$data[$as] = $this->content;
			$content = View::make(  $temp, $data );
			
			if ($content) {
				if (preg_match($this->_match_extends, $content, $math)) {
					$this->content = preg_replace($this->_match_extends, $this->content, $content);
				} else {
					$content = $content ."\r\n". $this->content ."\r\n";
					$this->content = $content;
				}
			} else {
				$this->content = preg_replace($math, '<!-- [extends failed] : '.$math.' -->', $this->content);
			}
		}
		
		return $this->content;
	}
	
	private function parse_include() {

		if (preg_match_all($this->_patten_include, $this->content, $maths, PREG_SET_ORDER)) {
			
			foreach ($maths as $math) {
				if ($this->engine != null) $data = & $this->engine->data();
				$temp = $math[1];
				$as = $math[2] ? $math[2] : '_spaceholder';
				$with = $math[3] ? json_decode($math[3], true) : [];
				$only = $math[4] ? true : false;
				$math = '/' . preg_replace('/\//', '\/', $math[0]) . '/';
				if ($only)
					$data = $with;
				else
					$data = array_merge($data, $with);
				$content = View::make( $temp , $data );
				//var_dump($data);
				if ($content) {
					$this->content = preg_replace($math, $content, $this->content);
					extract(array(
							$as => $content
					), EXTR_OVERWRITE);
					
				} else {
					$this->content = preg_replace($math, '<!-- [include error] : '.$math.' -->', $this->content);
				}
			}
		}
		
		return $this->content;
	}
	
	private function parse_section() {
		
		if (preg_match_all($this->_patten_section, $this->content, $maths, PREG_SET_ORDER)) {
			foreach ($maths as $math) {
				$name = $math[1];
				$content = $math[2] ? $math[2] : '';
		
				//var_dump($content);
		
				self::$sections[$name] = $content;
			}
		
			//extract($sections, EXTR_OVERWRITE);
		}
		
		$this->content = preg_replace($this->_patten_section, '', $this->content);
		return $this->content;
	}
	
	private function parse_yield() {
		
		if (preg_match_all($this->_patten_yield, $this->content, $matchs, PREG_SET_ORDER)) {
			$yields = array();
			foreach ($matchs as $match) {
				$name = $match[1];
				$match = '/' . $match[0] . '/';
				$this->content = preg_replace($match, self::$sections[$name], $this->content);
			}
			$this->content = preg_replace($this->_patten_yield, '', $this->content);
		}
		
		return $this->content;
	}
	
	private function parse_literal() {
	
		if (preg_match_all($this->_patten_literal, $this->content, $matchs, PREG_SET_ORDER)) {
			
			foreach ($matchs as $match) {
				if (! empty($match[1])) {
					$as = $match[1];
				} else {
					$as = '_literal'. rand();
				}
				$content = trim($match[2], "\r\n");
				if ($this->engine != null) {
					$this->engine->$as = $content;
					//$data = & $this->engine->data();
					//$data[$as] = $content;
				}
				//var_dump($this->engine->data());
				$match = $match[0];
				$to = '<?php echo $'. $as .' ;?>';
				$this->content = str_replace($match, $to, $this->content);
			}
			
			$this->content = preg_replace($this->_patten_literal, '', $this->content);
		}
		
		return $this->content;
	}
	
	private function parse_import() {
		if (preg_match_all($this->_patten_import, $this->content, $matchs, PREG_SET_ORDER)) {
			
			foreach ($matchs as $match) {
				$name = $match[1];
				$as = $match[2];
				$config = ($this->engine) ? $this->engine->config() : [];
				$engine = $this->engine;
				$ve = new ViewEngine($config);
				$body = $ve->fetch($name, false);
				$macros = ViewParser::import($body);
				//var_dump($macros);
				//json_decode(json_encode(self::$macros), true);
				$obj = new ViewMacro();
				$self = & $this;
				foreach ($macros as $key => $val){
					$obj->$key = function() use ($val) {
						$args = combine_arr(array_keys($val[0]), func_get_args()[0]);
						$args = array_merge($val[0], $args);
						$body = $this->parse($val[1]);
						
						ob_start() and ob_clean();
						extract($args, EXTR_OVERWRITE);
						
						try {
							eval('?>'.$body);
						} catch (\Exception $e) {
							ob_end_clean(); throw $e;
						}
						
						$body = ob_get_clean();
						//echo "\r\n|".$body."|\r\n";
						return $body;
					};
					
				}
				
				if ($engine) $engine->$as = $obj;
				
			}
			
			$this->content = preg_replace($this->_patten_import, '', $this->content);
		}
	
		return $this->content;
	}
	
	private function parse_macro() {
		if (preg_match_all($this->_patten_macro, $this->content, $matchs, PREG_SET_ORDER)) {
			
			foreach ($matchs as $match) {
				$name = $match[1];
				$params = $match[2];
				$body = $match[3];
				$params = explode(',', $params);
				$_params = [];
				foreach ($params as $param) {
					$param = trim($param);
					$param = explode('=', $param);
					$param[0] = ltrim(trim($param[0], " \0\"\'\r\n"), '$');
					if (count($param) == 2) $param[1] = trim($param[1], " \0\"\'\r\n");
					else $param[1] = null;
					$_params[$param[0]] = $param[1];
				}
				//$params = array_flip($params);
				$this->macros[$name] = [$_params, $body];
			}
				
			$this->content = preg_replace($this->_patten_macro, '', $this->content);
		}
	
		return $this->content;
	}
	
	private function parse_comment() {
		$this->content = preg_replace($this->_patten_comment, '', $this->content);
		
		return $this->content;
	}
	
	private function parse_content() {
		$this->content = preg_replace($this->_patten, $this->_match, $this->content);
		
		return $this->content;
	}
	
}


/**
 * 视图宏类
 * @author KYO
 * @since 2015-11-14
 *
 */
class ViewMacro extends stdClass {

	public function __call($method, $args) {
		if (isset($this->$method)) {
			$func = $this->$method;
			return $func($args);
		} else {
			return false;
		}
	}
}


/**
 * 视图渲染器
 */
class ViewRender {

	/**
	 * 
	 * @param string $content 字符串内容
	 * @param array $key_value 变量键值对
	 * @return string
	 */
	private static function render($content, $key_value) {
		
		$page_content = $content;
		$regex = '/\{\%\=([\w]+)\%\}/i';
		$matches = array();
		preg_match_all($regex, $page_content, $matches);
		$argList = array();
		if(!$content)
			return false;
		if($matches==0)
			return $content;
		foreach ($matches[1] as $var) {
			$argList["{%=$var%}"] = '';
			if (isset($key_value[$var])) {
				$argList["{%=$var%}"] = $key_value[$var];
			}
		}
		return str_replace(array_keys($argList), array_values($argList),
			$content);
	}

	/**
	 * 
	 * @param string $page html页面路径
	 * @param array $key_value html变量
	 * @return string 渲染后字符
	 */
	public static function render_file($page, $key_value) {
		return self::render(file_get_contents($page), $key_value);
	}

}


if (!function_exists('combine_arr')) {
	function combine_arr($a, $b) {
		$acount = count($a);
		$bcount = count($b);
		$size = ($acount > $bcount) ? $bcount : $acount;
		$a = array_slice($a, 0, $size);
		$b = array_slice($b, 0, $size);
		return array_combine($a, $b);
	}
	
}


/*----------------------------------------------------------------------*/
//  模版调用函数，使用 blade 模板引擎不可删除。
/*----------------------------------------------------------------------*/

if (!function_exists('view')) {
	function view($path, $data = array()) {
		return Laravel\View::make($path, $data);
	}
}

if (!function_exists('starts_with')) {
	function starts_with($haystack, $needles)
	{
		foreach ((array) $needles as $needle)
		{
			if (strpos($haystack, $needle) === 0) return true;
		}
	
		return false;
	}
}

if (!function_exists('str_contains')) {
	/**
	 * Determine if a given string contains a given sub-string.
	 *
	 * @param  string        $haystack
	 * @param  string|array  $needle
	 * @return bool
	 */
	function str_contains($haystack, $needle)
	{
		foreach ((array) $needle as $n)
		{
			if (strpos($haystack, $n) !== false) return true;
		}
	
		return false;
	}
}

if (!function_exists('set_storage_path')) {
	function set_storage_path($path) {
		$GLOBALS[ 'blade_storage_path' ] = $path;
	}
}

if (!function_exists('set_storage')) {
	function set_storage($storage) {
		$GLOBALS[ 'blade_storage' ] = $storage;
	}
}

/*if (!function_exists('get_template_directory')) {
	function get_template_directory() {
		return array (
			APP_PATH . '/',
			APP_PATH . '/',
		);
	}
}*/

?>