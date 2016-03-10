<?PHP
$GLOBALS['_begin_time'] = microtime(true);
$GLOBALS['_start_memory_usage'] = function_exists('memory_get_usage') ? memory_get_usage() : false;

include_once 'config.php';
define("IN_PRODUCTION", true);

define("CONFORM_VER", version_compare(PHP_VERSION, "5.4.0", "<"));

if(CONFORM_VER) {
	ini_set('magic_quotes_runtime', False);
	define('MAGIC_QUOTES_GPC', get_magic_quotes_gpc() ? True : False);
}else{
	define('MAGIC_QUOTES_GPC', False);
}

if (!ini_get("date.timezone")) {
	ini_set("date.timezone", "UTC");
}

ini_set('display_errors', false);
ini_set('session.use_cookies', true);
ini_set('session.use_trans_sid', false);
ini_set('session.cache_expire',  30);
ini_set('session.use_cookies', true);
ini_set('session.auto_start', false);
ini_set('allow_url_include', true);
//ini_set('memory_limit', '16M');
ini_set('output_buffering', true);

@set_time_limit(600);
@ini_set('session.cache_expire', 1000);

//检测PHP系统是否自动添加反斜杠
define('SYS_MAGICGPC', get_magic_quotes_gpc());
define('CWD', getcwd() . '/');

is_link(basename(__FILE__)) and chdir(dirname(realpath(__FILE__)));

define("SERVICE_PATH", realpath("service"));
define("PUBLIC_PATH", realpath("public"));
define("ASSETS_PATH", realpath("assets"));
define("INCLUDE_PATH", realpath("includes"));
define("VIEWS_PATH", realpath("views"));

set_include_path(get_include_path() . PATH_SEPARATOR . INCLUDE_PATH);
set_include_path(get_include_path() . PATH_SEPARATOR . SERVICE_PATH);

if (defined('ENVIRONMENT')) {
	switch (ENVIRONMENT) {
		case 'development':
			error_reporting(E_ALL);
			break;
		case 'testing':
			error_reporting(E_ALL ^ E_NOTICE);
			break;
		case 'production':
			error_reporting(0);
			break;
		default:
			exit('The application environment is not set correctly.');
	}
} else {
	//error_reporting(E_ALL ^ E_NOTICE ^ E_USER_WARNING);
}

if (file_exists(APP_PATH . '/_web.php')) {
	include(APP_PATH . '/_web.php');
}

// Initialize.
require APP_PATH . '/loader.php';

//require APP_PATH . '/view.php';

function appinfo($name='') {
	
	return $name;
}

//spl_autoload_register('autoload');
//spl_autoload_register(include 'app/loader.php');

function autoload($class) {
	require APP_PATH.'/'.str_replace('\\', '/', strtolower($class)).'.php'; 
	//require __DIR__.'/'.$class.'.php';
}

//function __autoload($object) {
//require_once("{$object}.php");
//}


?>