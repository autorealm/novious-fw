<?PHP

class ExceptionRedirectHandler {
	public $redirect = 'error404.html';

	protected $_exception;
	protected $_logFile= '/error.log';
	
	public function __construct(Exception $e) {
		$this->_exception = $e;
	}
	
	public static function handle(Exception $e) {
		$self = new self($e);
		$self->log();
		while(@ob_end_clean());//清空所有的输出
		header('HTTP/1.1 307 Temporary Redirect');
		header('Location:' . $_SERVER["HTTP_REFERER"]. $self->redirect);
		exit(1);
	}
	
	public function log() {
		error_log($this->_exception->getMessage().PHP_EOL, 3, BASEPATH . $this->_logFile);
	}
}

//set_exception_handler(array('ExceptionRedirectHandler', 'handle'));
//$link = mysql_connect('localhost', 'roo23t', 'root');
//if(!$link){
//throw new Exception("数据库被攻击，请抓紧查看!");
//}

/**
 * 错误处理器
 */
class ErrorHandler {
	public $message;
	public $filename;
	public $line;
	public $vars;
	protected $_noticeLog = '/php/patterns/error/noticeLog.log';
	
	public function __construct($message, $filename, $line, $vars) {
		$this->message = $message;
		$this->filename = $filename;
		$this->line = $line;
		$this->vars = $vars;
	}
	
	public static function deal($errno, $errmsg, $filename, $line, $vars) {
		$self = new self($errmsg, $filename, $line, $vars);
		switch($errno){
			case E_USER_ERROR :
				return $self->dealError();
				break;
			case E_USER_WARNING:
			case E_WARNING:
				return $self->dealWaring();
				break;
			case E_NOTICE:
			case E_USER_NOTICE:
				return $self->dealNotice();
				break;
			default:
				return false;
		}
	}
	
	/**
	 * 致命错误发送邮件
	 */
	public function dealError() {
		ob_start();
		debug_print_backtrace();
		$backtrace = ob_get_flush();
		$errorMsg = <<<EOF
[致命错误]
产生错误的文件：{$this->filename}
产生错误的信息：{$this->message}
产生错误的行号：{$this->line}
追踪信息：{$backtrace}
EOF;
		error_log($errorMsg, 1, 'autorealm@163.com');
		exit(1);
	}
	
	/**
	 * 警告信息发送邮件
	 * @return bool
	 */
	public function dealWaring() {
		$errorMsg = <<<EOF
[警告错误]
产生警告的文件：{$this->filename}
产生警告的信息：{$this->message}
产生警告的行号：{$this->line}
EOF;
		return error_log($errorMsg, 1, 'autorealm@163.com');
	}
	
	/**
	 * 通知信息存放文件
	 * @return bool
	 */
	public function dealNotice() {
		$datetime = date('Y-m-d H:i:s', time());
		$errorMsg = <<<EOF
[通知错误]
产生通知的文件：{$this->filename}
产生通知的信息：{$this->message}
产生通知的行号：{$this->line}
产生通知的时间：{$datetime}
EOF;
		return error_log($errorMsg, 3, $this->_noticeLog);
	}

}

class Handler {
	public static $errors = array();
	
	public static $errortype = array (
			E_ERROR              => 'Error',
			E_WARNING            => 'Warning',
			E_PARSE              => 'Parsing Error',
			E_NOTICE             => 'Notice',
			E_CORE_ERROR         => 'Core Error',
			E_CORE_WARNING       => 'Core Warning',
			E_COMPILE_ERROR      => 'Compile Error',
			E_COMPILE_WARNING    => 'Compile Warning',
			E_USER_ERROR         => 'User Error',
			E_USER_WARNING       => 'User Warning',
			E_USER_NOTICE        => 'User Notice',
			E_STRICT             => 'Runtime Notice',
			E_RECOVERABLE_ERROR  => 'Catchable Fatal Error'
	);
	
	public static $user_errors = array(E_USER_ERROR, E_USER_WARNING, E_USER_NOTICE);
	
	public function __construct() {
		
	}
	
	public static function errors() {
		return self::$errors;
	}
	
	public static function get_last_error() {
		$last = count(self::$errors) - 1;
		if ($last >= 0)
			return self::$errors[$last];
		else
			return null;
	}

	public static function get_millisecond($float=false) {
		list($usec, $sec) = explode(" ", microtime());
		if ($float) {
			return ((float)$usec + (float)$sec);
		} else {
			$msec = round($usec*1000);
			return $msec;
		}
	}
	
	public static function handle($errno, $errmsg, $filename, $line, & $vars) {
		$time = date("Y-m-d H:i:s." . self::get_millisecond() . " (T)");
		$error = array(
				'time' => $time,
				'error' => self::$errortype[$errno],
				'type' => $errno,
				'message' => $errmsg,
				'file' => $filename,
				'line' => $line,
				//'vars' => $vars
		);
		if (! is_null($vars)) unset($vars);
		self::$errors[] = $error;
	}
	
	public static function json_handle_error($errno, $errmsg, $filename, $linenum, $vars) {
		$dt = date("Y-m-d H:i:s." . self::get_millisecond() . " (T)");
		$err = array();
		$err['time'] = $dt;
		$err['type'] = $errno;
		$err['error'] = self::$errortype[$errno];
		$err['message'] = $errmsg;
		$err['file'] = $filename;
		$err['line'] = $linenum;
		if (in_array($errno, self::$user_errors)) {
			$err['vars'] = serialize($vars, true);
		}
		echo $err;
	}
	
	public static function html_handle_error($errno, $errmsg, $filename, $linenum, $vars) {
		$dt = date("Y-m-d H:i:s." . self::get_millisecond() . " (T)");
		$err = "<ErrorEntry style='display:none;'>\n";
		$err .= "<DateTime>$dt</DateTime>\n";
		$err .= "<ErrorNum>$errno</ErrorNum>\n";
		$err .= "<ErrorType>". self::$errortype[$errno] ."</ErrorType>\n";
		$err .= "<ErrorMsg>$errmsg</ErrorMsg>\n";
		$err .= "<ScriptName>$filename</ScriptName>\n";
		$err .= "<ScriptLineNum>$linenum</ScriptLineNum>\n";
		// set of errors for which a var trace will be saved
		if (in_array($errno, self::$user_errors)) {
			if (function_exists('wddx_serialize_value'))
				$err .= "<VarTrace>" . wddx_serialize_value($vars, "Variables") . "</VarTrace>\n";
			else
				$err .= "<VarTrace>\n" .var_export($vars, true) . "\n</VarTrace>\n";
		}
		$err .= "</ErrorEntry>";
		$err = "\r\n" . $err . "\r\n";
		echo $err;
	}
	
}


//set_error_handler(array('MyErrorHandler', 'deal'));

