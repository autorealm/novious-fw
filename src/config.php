<?php
//@header("content-type:text/html;charset=utf-8");

/*------------------------------------------------------- */
//--- 全局配置文件
/*------------------------------------------------------- */

//文档根目录
define( 'ROOT_DIR', str_replace("\\","/",dirname(dirname(realpath(__FILE__)))) );
//管理员目录
define( 'ADMIN_DIR', ROOT_DIR."/admin" );
//开发环境路径
define( 'DEV_ENV_PATH', ROOT_DIR."/dev" );
define( 'DEV_LIB_PATH', DEV_ENV_PATH.'/libs' );
define( 'DEV_CORE_PATH', DEV_ENV_PATH.'/core' );

define( 'RETURN_FORMAT', 'json' );
define( 'DATETIME_FORMAT', 'Y-m-d H:i:s' );
define( 'API_HOST', 'http://novious.saeapps.com/api/' );

define( 'DOC_PATH', $_SERVER['DOCUMENT_ROOT'] );
define( 'APP_PATH', str_replace("\\","/",dirname(realpath(__FILE__))) );
define( 'CONTROLLER_PATH', APP_PATH.'/controllers' );
define( 'MIDDLEWARE_PATH', APP_PATH.'/middlewares' );
define( 'MODEL_PATH', APP_PATH.'/models' );
define( 'LIBRARY_PATH', APP_PATH.'/libraries' );
define( 'HELPER_PATH', APP_PATH.'/helpers' );

define('SELF', pathinfo(__FILE__, PATHINFO_BASENAME));
define('EXT', '.php');
define('CRLF', "\r\n");
if ( ! defined('DS')) {
	define('DS', DIRECTORY_SEPARATOR);
}

//配置数组
$config = array();

//调试开关
$config['debug'] = TRUE;

//系统日志路径
$config["log_path"] = str_replace('/', DIRECTORY_SEPARATOR, ROOT_DIR."/../logs");


// --------------------------------------------------------------------------
// Configuration
// --------------------------------------------------------------------------
$memcache_config = array(
	'engine'				=> 'Memcache',
	'prefix' 				=> '',
	'compression'			=> FALSE,
	'auto_compress_tresh'	=> FALSE,
	'auto_compress_savings'	=> 0.2,
	'expiration'			=> 3600,
	'delete_expiration'		=> 0

);

$memcache_config['_servers'] = array(
		'default' => array(
				'host'			=> 'localhost',
				'port'			=> '11211',
				'weight'		=> '1',
				'persistent'	=> FALSE
		)

);

$config['memcache'] = $memcache_config;

$template_config = [];
$template_config['blade'] = array(
		'suffix' => '.blade.php',
		'engine' => 'blade',
		'template_path' => ROOT_DIR.'/views',
		'storage_path' => 'cache',
		'bucket' => 'templates',
		'cache' => true,
		'expire' => 1000
	);

$template_config['text'] = array(
		'suffix'=>'.html',
		'engine'=> 'text',
		'template_path'=>ROOT_DIR.'/views',
		'storage_path'=>'/storage/cache',
		'bucket' => 'templates',
		'cache'=>false,
		'expire'=>1000
	);

$template_config['tonic'] = array(
		'suffix'=>'.tonic.php',
		'engine'=> 'tonic',
		'template_path'=>ROOT_DIR.'/views',
		'cache_path'=>'/storage/cache',
		'cache'=>false,
		'expire'=>1000
	);

$config['template'] = $template_config;

$sae_config = array(
		'DB_TYPE'           =>  'mysql', // 数据库类型
		'DB_DEPLOY_TYPE'    =>  1,
		'DB_RW_SEPARATE'    =>  true,
		'DB_HOST'           =>  SAE_MYSQL_HOST_M.','.SAE_MYSQL_HOST_S, // 服务器地址
		'DB_NAME'           =>  SAE_MYSQL_DB, // 数据库名
		'DB_USER'           =>  SAE_MYSQL_USER, // 用户名
		'DB_PWD'            =>  SAE_MYSQL_PASS, // 密码
		'DB_PORT'           =>  SAE_MYSQL_PORT, // 端口
		//更改模板替换变量，让普通能在所有平台下显示
		'TMPL_PARSE_STRING' =>  array(
			// __PUBLIC__/upload  -->  /Public/upload -->http://appname-public.stor.sinaapp.com/upload
			//'/Public/upload'    =>  $st->getUrl('public','upload')
		),
		'LOG_TYPE'          =>  'Sae',
		'DATA_CACHE_TYPE'   =>  'Memcachesae',
		'CHECK_APP_DIR'     =>  false,
	);


if (! $config['debug']) {
	error_reporting(0);//禁用错误报告
} else {
	
}

date_default_timezone_set("PRC");

