<?PHP

function add_magic_quotes($array) {
	foreach ((array) $array as $k => $v) {
		if (is_array($v)) {
			$array[$k] = add_magic_quotes($v);
		} else {
			$array[$k] = addslashes($v);
		}
	}
	return $array;
}

function add_slashes($string) {
	if (!$GLOBALS['magic_quotes_gpc']) {
		if (is_array($string)) {
			foreach ($string as $key => $val) {
				$string[$key] = add_slashes($val);
			}
		} else {
			$string = addslashes($string);
		}
	}
	return $string;
}

/**
 * 获取指定长度的 utf8 字符串
 *
 * @param string $string
 * @param int $length
 * @param string $dot
 * @return string
 */
function get_utf8_str($string, $length, $dot = '...') {
	if (strlen($string) <= $length)
		return $string;
	
	$strcut = '';
	$n = $tn = $noc = 0;
	
	while ($n < strlen($string)) {
		$t = ord($string[$n]);
		if ($t == 9 || $t == 10 || (32 <= $t && $t <= 126)) {
			$tn = 1;
			$n++;
			$noc++;
		} elseif (194 <= $t && $t <= 223) {
			$tn = 2;
			$n += 2;
			$noc += 2;
		} elseif (224 <= $t && $t <= 239) {
			$tn = 3;
			$n += 3;
			$noc += 2;
		} elseif (240 <= $t && $t <= 247) {
			$tn = 4;
			$n += 4;
			$noc += 2;
		} elseif (248 <= $t && $t <= 251) {
			$tn = 5;
			$n += 5;
			$noc += 2;
		} elseif ($t == 252 || $t == 253) {
			$tn = 6;
			$n += 6;
			$noc += 2;
		} else {
			$n++;
		}
		if ($noc >= $length)
			break;
	}
	if ($noc > $length) {
		$n -= $tn;
	}
	if ($n < strlen($string)) {
		$strcut = substr($string, 0, $n);
		return $strcut . $dot;
	} else {
		return $string;
	}
}

/**
 * 字符串截取，支持中文和其他编码
 *
 * @param string $str 需要转换的字符串
 * @param string $start 开始位置
 * @param string $length 截取长度
 * @param string $charset 编码格式
 * @param string $suffix 截断显示字符
 * @return string
 */
function msubstr($str, $start = 0, $length, $charset = "utf-8", $suffix = true) {
	if (function_exists("mb_substr")) {
		$i_str_len = mb_strlen($str);
		$s_sub_str = mb_substr($str, $start, $length, $charset);
		if ($length >= $i_str_len) {
			return $s_sub_str;
		}
		return $s_sub_str . '...';
	} elseif (function_exists('iconv_substr')) {
		return iconv_substr($str, $start, $length, $charset);
	}
	$re['utf-8'] = "/[\x01-\x7f]|[\xc2-\xdf][\x80-\xbf]|[\xe0-\xef][\x80-\xbf]{2}|[\xf0-\xff][\x80-\xbf]{3}/";
	$re['gb2312'] = "/[\x01-\x7f]|[\xb0-\xf7][\xa0-\xfe]/";
	$re['gbk'] = "/[\x01-\x7f]|[\x81-\xfe][\x40-\xfe]/";
	$re['big5'] = "/[\x01-\x7f]|[\x81-\xfe]([\x40-\x7e]|\xa1-\xfe])/";
	preg_match_all($re[$charset], $str, $match);
	$slice = join("", array_slice($match[0], $start, $length));
	if ($suffix)
		return $slice . "…";
	return $slice;
}

/**
 * 取$from~$to范围内的随机数
 *
 * @param $from 下限
 * @param $to 上限
 * @return unknown_type
 */
function rand_from_to($from, $to) {
	$size = $from - $to; // 数值区间
	$max = 30000; // 最大
	if ($size < $max) {
		return $from + mt_rand(0, $size);
	} else {
		if ($size % $max) {
			return $from + random_from_to(0, $size / $max) * $max + mt_rand(0, $size % $max);
		} else {
			return $from + random_from_to(0, $size / $max) * $max + mt_rand(0, $max);
		}
	}
}

/**
 * 产生随机字串，可用来自动生成密码 默认长度6位 字母和数字混合
 *
 * @param string $len 长度
 * @param string $type 字串类型：0 字母 1 数字 2 大写字母 3 小写字母 4 中文
 *        其他为数字字母混合(去掉了 容易混淆的字符oOLl和数字01，)
 * @param string $addChars 额外字符
 * @return string
 */
function rand_string($len = 4, $type = 'check_code') {
	$str = '';
	switch ($type) {
		case 0 : // 大小写中英文
			$chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz';
		break;
		case 1 : // 数字
			$chars = str_repeat('0123456789', 3);
		break;
		case 2 : // 大写字母
			$chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
		break;
		case 3 : // 小写字母
			$chars = 'abcdefghijklmnopqrstuvwxyz';
		break;
		default :
			// 默认去掉了容易混淆的字符oOLl和数字01，要添加请使用addChars参数
			$chars = 'ABCDEFGHIJKMNPQRSTUVWXYZabcdefghijkmnpqrstuvwxyz23456789';
		break;
	}
	if ($len > 10) { // 位数过长重复字符串一定次数
		$chars = $type == 1 ? str_repeat($chars, $len) : str_repeat($chars, 5);
	}
	if ($type != 4) {
		$chars = str_shuffle($chars);
		$str = substr($chars, 0, $len);
	} else {
		// 中文随机字
		for ($i = 0; $i < $len; $i++) {
			$str .= msubstr($chars, floor(mt_rand(0, mb_strlen($chars, 'utf-8') - 1)), 1);
		}
	}
	return $str;
}

/**
 * 生成自动密码
 */
function make_password() {
	$temp = '0123456789abcdefghijklmnopqrstuvwxyz' . 'ABCDEFGHIJKMNPQRSTUVWXYZ~!@#$^*)_+}{}[]|":;,.' . time();
	for ($i = 0; $i < 10; $i++) {
		$temp = str_shuffle($temp . substr($temp, -5));
	}
	return md5($temp);
}

/**
 * 获取客户端IP地址
 *
 * @param boolean $s_type ip类型[ip|long]
 * @return string $ip
 */
function get_client_ip($b_ip = true) {
	$arr_ip_header = array(
			"HTTP_CLIENT_IP",
			"HTTP_X_FORWARDED_FOR",
			"REMOTE_ADDR",
			"HTTP_CDN_SRC_IP",
			"HTTP_PROXY_CLIENT_IP",
			"HTTP_WL_PROXY_CLIENT_IP" 
	);
	$client_ip = 'unknown';
	foreach ($arr_ip_header as $key) {
		if (!empty($_SERVER[$key]) && strtolower($_SERVER[$key]) != "unknown") {
			$client_ip = $_SERVER[$key];
			break;
		}
	}
	if ($pos = strpos($client_ip, ',')) {
		$client_ip = substr($client_ip, $pos + 1);
	}
	return $client_ip;
}

/**
 * 获取网络url文件内容，加入ua，以解决防采集的站
 */
function curl_get_contents($url) {
	$ch = curl_init();
	$timeout = 4;
	$user_agent = "Mozilla/4.0 (compatible; MSIE 8.0; Windows NT 6.0; WOW64; Trident/4.0; SLCC1)";
	curl_setopt($ch, CURLOPT_URL, $url);
	curl_setopt($ch, CURLOPT_HEADER, 0);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
	curl_setopt($ch, CURLOPT_USERAGENT, $user_agent);
	curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $timeout);
	$file_contents = curl_exec($ch);
	curl_close($ch);
	return $file_contents;
}

/**
 * 消息框。eg
 * msg("falied","/",10);
 * msg("ok");
 */
function show_msg($message, $url = '#', $time = 3, $isgo = 1) {
	$goto = "content='$time;url=$url'";
	if ($isgo != "1") {
		$goto = "";
	} // 是否自动跳转
	echo <<<END
<html>
	<meta http-equiv='refresh' $goto charset="utf-8">
	<style>
	#msgbox{width:400px;border: 1px solid #ddd;font-family:微软雅黑;color:888;font-size:13px;margin:0 auto;margin-top:150px;}
	#msgbox #title{background:#3F9AC6;color:#fff;line-height:30px;height:30px;padding-left:20px;font-weight:800;}
	#msgbox #message{text-align:center;padding:20px;}
	#msgbox #info{text-align:center;padding:5px;border-top:1px solid #ddd;background:#f2f2f2;color:#888;}
	</style>
	<body>
	<div id="msgbox">
	<div id="title">提示信息</div>
	<div id="message">$message</div>
	<div id="info">$time 秒后自动跳转，如不想等待可 <a href='$url'>点击这里</a></div></center>
	</body>
</html>
END;
	exit();
}

/**
 * 获取系统信息
 */
function get_sysinfo() {
	$sys_info['os'] = PHP_OS;
	$sys_info['zlib'] = function_exists('gzclose'); // zlib
	$sys_info['safe_mode'] = (boolean) ini_get('safe_mode'); // safe_mode = Off
	$sys_info['safe_mode_gid'] = (boolean) ini_get('safe_mode_gid'); // safe_mode_gid = Off
	$sys_info['timezone'] = function_exists("date_default_timezone_get") ? date_default_timezone_get() : L('no_setting');
	$sys_info['socket'] = function_exists('fsockopen');
	$sys_info['web_server'] = strpos($_SERVER['SERVER_SOFTWARE'], 'PHP') === false ? $_SERVER['SERVER_SOFTWARE'] . 'PHP/' . phpversion() : $_SERVER['SERVER_SOFTWARE'];
	$sys_info['phpversion'] = phpversion();
	$sys_info['fileupload'] = @ini_get('file_uploads') ? ini_get('upload_max_filesize') : 'unknown';
	return $sys_info;
}

/**
 * 文件扫描
 * @param $filepath 目录
 * @param $subdir 是否搜索子目录
 * @param $ex 搜索扩展
 * @param $isdir 是否只搜索目录
 * @param $enforcement 强制更新缓存
 */
function scan_file_lists($filepath, $subdir = 1, $ex = '', $isdir = 0, $enforcement = 0) {
	static $file_list = array();
	if ($enforcement)
		$file_list = array();
	$filepath = (is_dir($filepath)) ? $filepath : dirname($filepath);
	$flags = $isdir ? GLOB_ONLYDIR : 0;
	$list = glob($filepath . '*' . (!empty($ex) && empty($subdir) ? '.' . $ex : ''), $flags);
	if (!empty($ex))
		$ex_num = strlen($ex);
	foreach ($list as $k => $v) {
		if ($subdir && is_dir($v)) {
			scan_file_lists($v . DIRECTORY_SEPARATOR, $subdir, $ex, $isdir);
			continue;
		}
		if (!empty($ex) && strtolower(substr($v, -$ex_num, $ex_num)) != $ex) {
			unset($list[$k]);
			continue;
		} else {
			$file_list[dirname($v)][] = $v;
			continue;
		}
	}
	return $file_list;
}

/**
 * xss过滤函数
 *
 * @param $string
 * @return string
 */
function remove_xss($string) {
	$string = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]+/S', '', $string);
	
	return $string;
}

/**
 * 过滤ASCII码从0-28的控制字符
 * @return String
 */
function trim_unsafe_control_chars($str) {
	$rule = '/[' . chr(1) . '-' . chr(8) . chr(11) . '-' . chr(12) . chr(14) . '-' . chr(31) . ']*/';
	return str_replace(chr(0), '', preg_replace($rule, '', $str));
}

/**
 * 格式化文本域内容
 *
 * @param $string 文本域内容
 * @return string
 */
function trim_textarea($string) {
	$string = nl2br(str_replace(' ', '&nbsp;', $string));
	return $string;
}

/**
 * 字符截取 支持UTF8/GBK
 * @param $string
 * @param $length
 * @param $dot
 */
function str_cut($string, $length, $dot = '...') {
	$strlen = strlen($string);
	if ($strlen <= $length)
		return $string;
	$string = str_replace(array(
			' ',
			'&nbsp;',
			'&amp;',
			'&quot;',
			'&#039;',
			'&ldquo;',
			'&rdquo;',
			'&mdash;',
			'&lt;',
			'&gt;',
			'&middot;',
			'&hellip;' 
	), array(
			'∵',
			' ',
			'&',
			'"',
			"'",
			'“',
			'”',
			'—',
			'<',
			'>',
			'·',
			'…' 
	), $string);
	$strcut = '';
	if (strtolower(CHARSET) == 'utf-8') {
		$length = intval($length - strlen($dot) - $length / 3);
		$n = $tn = $noc = 0;
		while ($n < strlen($string)) {
			$t = ord($string[$n]);
			if ($t == 9 || $t == 10 || (32 <= $t && $t <= 126)) {
				$tn = 1;
				$n++;
				$noc++;
			} elseif (194 <= $t && $t <= 223) {
				$tn = 2;
				$n += 2;
				$noc += 2;
			} elseif (224 <= $t && $t <= 239) {
				$tn = 3;
				$n += 3;
				$noc += 2;
			} elseif (240 <= $t && $t <= 247) {
				$tn = 4;
				$n += 4;
				$noc += 2;
			} elseif (248 <= $t && $t <= 251) {
				$tn = 5;
				$n += 5;
				$noc += 2;
			} elseif ($t == 252 || $t == 253) {
				$tn = 6;
				$n += 6;
				$noc += 2;
			} else {
				$n++;
			}
			if ($noc >= $length) {
				break;
			}
		}
		if ($noc > $length) {
			$n -= $tn;
		}
		$strcut = substr($string, 0, $n);
		$strcut = str_replace(array(
				'∵',
				'&',
				'"',
				"'",
				'“',
				'”',
				'—',
				'<',
				'>',
				'·',
				'…' 
		), array(
				' ',
				'&amp;',
				'&quot;',
				'&#039;',
				'&ldquo;',
				'&rdquo;',
				'&mdash;',
				'&lt;',
				'&gt;',
				'&middot;',
				'&hellip;' 
		), $strcut);
	} else {
		$dotlen = strlen($dot);
		$maxi = $length - $dotlen - 1;
		$current_str = '';
		$search_arr = array(
				'&',
				' ',
				'"',
				"'",
				'“',
				'”',
				'—',
				'<',
				'>',
				'·',
				'…',
				'∵' 
		);
		$replace_arr = array(
				'&amp;',
				'&nbsp;',
				'&quot;',
				'&#039;',
				'&ldquo;',
				'&rdquo;',
				'&mdash;',
				'&lt;',
				'&gt;',
				'&middot;',
				'&hellip;',
				' ' 
		);
		$search_flip = array_flip($search_arr);
		for ($i = 0; $i < $maxi; $i++) {
			$current_str = ord($string[$i]) > 127 ? $string[$i] . $string[++$i] : $string[$i];
			if (in_array($current_str, $search_arr)) {
				$key = $search_flip[$current_str];
				$current_str = str_replace($search_arr[$key], $replace_arr[$key], $current_str);
			}
			$strcut .= $current_str;
		}
	}
	return $strcut . $dot;
}

/**
 * 获取请求ip
 *
 * @return ip地址
 */
function ip() {
	if (getenv('HTTP_CLIENT_IP') && strcasecmp(getenv('HTTP_CLIENT_IP'), 'unknown')) {
		$ip = getenv('HTTP_CLIENT_IP');
	} elseif (getenv('HTTP_X_FORWARDED_FOR') && strcasecmp(getenv('HTTP_X_FORWARDED_FOR'), 'unknown')) {
		$ip = getenv('HTTP_X_FORWARDED_FOR');
	} elseif (getenv('REMOTE_ADDR') && strcasecmp(getenv('REMOTE_ADDR'), 'unknown')) {
		$ip = getenv('REMOTE_ADDR');
	} elseif (isset($_SERVER['REMOTE_ADDR']) && $_SERVER['REMOTE_ADDR'] && strcasecmp($_SERVER['REMOTE_ADDR'], 'unknown')) {
		$ip = $_SERVER['REMOTE_ADDR'];
	}
	return preg_match('/[\d\.]{7,15}/', $ip, $matches) ? $matches[0] : '';
}

function get_cost_time() {
	$microtime = microtime(TRUE);
	return $microtime - SYS_START_TIME;
}

/**
 * 程序执行时间
 *
 * @return int
 */
function execute_time() {
	$stime = explode(' ', SYS_START_TIME);
	$etime = explode(' ', microtime());
	return number_format(($etime[1] + $etime[0] - $stime[1] - $stime[0]), 6);
}

/**
 * 产生随机字符串
 *
 * @param int $length 输出长度
 * @param string $chars 可选的 ，默认为 0123456789
 * @return string 字符串
 *        
 */
function random($length, $chars = '0123456789') {
	$hash = '';
	$max = strlen($chars) - 1;
	for ($i = 0; $i < $length; $i++) {
		$hash .= $chars[mt_rand(0, $max)];
	}
	return $hash;
}

/**
 * 将字符串转换为数组
 *
 * @param string $data
 * @return array
 *
 */
function string2array($data) {
	if ($data == '')
		return array();
	@eval("\$array = $data;");
	return $array;
}

/**
 * 将数组转换为字符串
 *
 * @param array $data
 * @return string
 *
 */
function array2string($data) {
	if ($data == '')
		return '';
	return addslashes(var_export($data, TRUE));
}

/**
 * 转换字节数为其他单位
 *
 * @param string $filesize
 * @return string
 *
 */
function sizecount($filesize) {
	if ($filesize >= 1073741824) {
		$filesize = round($filesize / 1073741824 * 100) / 100 . ' GB';
	} elseif ($filesize >= 1048576) {
		$filesize = round($filesize / 1048576 * 100) / 100 . ' MB';
	} elseif ($filesize >= 1024) {
		$filesize = round($filesize / 1024 * 100) / 100 . ' KB';
	} else {
		$filesize = $filesize . ' Bytes';
	}
	return $filesize;
}

/**
 * 字符串加密、解密函数
 *
 * @param string $txt
 * @param string $operation
 * @param string $key
 * @param string $expiry
 * @return string
 *
 */
function sys_auth($string, $operation = 'ENCODE', $key = '', $expiry = 0) {
	$key_length = 4;
	$key = md5($key != '' ? $key : pc_base::load_config('system', 'auth_key'));
	$fixedkey = md5($key);
	$egiskeys = md5(substr($fixedkey, 16, 16));
	$runtokey = $key_length ? ($operation == 'ENCODE' ? substr(md5(microtime(true)), -$key_length) : substr($string, 0, $key_length)) : '';
	$keys = md5(substr($runtokey, 0, 16) . substr($fixedkey, 0, 16) . substr($runtokey, 16) . substr($fixedkey, 16));
	$string = $operation == 'ENCODE' ? sprintf('%010d', $expiry ? $expiry + time() : 0) . substr(md5($string . $egiskeys), 0, 16) . $string : base64_decode(strtr(substr($string, $key_length), '-_', '+/'));
	
	if ($operation == 'ENCODE') {
		$string .= substr(md5(microtime(true)), -4);
	}
	if (function_exists('mcrypt_encrypt') == true) {
		$result = sys_auth_ex($string, $operation, $fixedkey);
	} else {
		$i = 0;
		$result = '';
		$string_length = strlen($string);
		for ($i = 0; $i < $string_length; $i++) {
			$result .= chr(ord($string{$i}) ^ ord($keys{$i % 32}));
		}
	}
	if ($operation == 'DECODE') {
		$result = substr($result, 0, -4);
	}
	
	if ($operation == 'ENCODE') {
		return $runtokey . rtrim(strtr(base64_encode($result), '+/', '-_'), '=');
	} else {
		if ((substr($result, 0, 10) == 0 || substr($result, 0, 10) - time() > 0) && substr($result, 10, 16) == substr(md5(substr($result, 26) . $egiskeys), 0, 16)) {
			return substr($result, 26);
		} else {
			return '';
		}
	}
}

/**
 * 字符串加密、解密扩展函数
 *
 * @param string $txt
 * @param string $operation
 * @param string $key
 * @return string
 *
 */
function sys_auth_ex($string, $operation = 'ENCODE', $key) {
	$encrypted_data = "";
	$td = mcrypt_module_open('rijndael-256', '', 'ecb', '');
	
	$iv = mcrypt_create_iv(mcrypt_enc_get_iv_size($td), MCRYPT_RAND);
	$key = substr($key, 0, mcrypt_enc_get_key_size($td));
	mcrypt_generic_init($td, $key, $iv);
	
	if ($operation == 'ENCODE') {
		$encrypted_data = mcrypt_generic($td, $string);
	} else {
		$encrypted_data = rtrim(mdecrypt_generic($td, $string));
	}
	mcrypt_generic_deinit($td);
	mcrypt_module_close($td);
	return $encrypted_data;
}

/**
 * 查询字符是否存在于某字符串
 *
 * @param $haystack 字符串
 * @param $needle 要查找的字符
 * @return bool
 */
function str_exists($haystack, $needle) {
	return !(strpos($haystack, $needle) === FALSE);
}

/**
 * 取得文件扩展
 *
 * @param $filename 文件名
 * @return 扩展名
 */
function fileext($filename) {
	return strtolower(trim(substr(strrchr($filename, '.'), 1, 10)));
}

/**
 * 生成sql语句，如果传入$in_cloumn 生成格式为 IN('a', 'b', 'c')
 * @param $data 条件数组或者字符串
 * @param $front 连接符
 * @param $in_column 字段名称
 * @return string
 */
function to_sqls($data, $front = ' AND ', $in_column = false) {
	if ($in_column && is_array($data)) {
		$ids = '\'' . implode('\',\'', $data) . '\'';
		$sql = "$in_column IN ($ids)";
		return $sql;
	} else {
		if ($front == '') {
			$front = ' AND ';
		}
		if (is_array($data) && count($data) > 0) {
			$sql = '';
			foreach ($data as $key => $val) {
				$sql .= $sql ? " $front `$key` = '$val' " : " `$key` = '$val' ";
			}
			return $sql;
		} else {
			return $data;
		}
	}
}

/**
 * 分页函数
 *
 * @param $total_num 信息总数
 * @param $cur_page 当前分页
 * @param $page_size 每页显示数
 * @param $page_set 显示页数
 * @return 分页
 */
function pages_list($cur_page, $total_num, $page_set = 10, $page_size = 20) {
	$multipage = array();
	
	$offset = ceil($page_set / 2 - 1);
	$pages = ceil($total_num / $page_size);
	
	$multipage['curpage'] = $cur_page;
	$multipage['pagesize'] = $page_size;
	$multipage['totalpage'] = $pages;
	$multipage['totalsize'] = $total_num;
	$multipage['offset'] = $offset;
	$multipage['from'] = 1;
	$multipage['to'] = 1;
	$multipage['more'] = 0;
	$multipage['previous'] = 0;
	$multipage['next'] = 0;
	$multipage['frontmore'] = 0;
	$multipage['endmore'] = 0;
	
	if ($total_num <= $page_size)
		return $multipage;
	
	$from = $cur_page - $offset;
	$to = $cur_page + $page_set - $offset - 1;
	
	$more = 0;
	if ($page_set > $pages) {
		$from = 1;
		$to = $pages;
	} else {
		if ($from < 1) {
			$to = $cur_page + 1 - $from;
			$from = 1;
			if (($to - $from) < $page_set && ($to - $from) < $pages) {
				$to = $page_set;
			}
		} elseif ($to > $pages) {
			$from = $cur_page - $pages + $to;
			$to = $pages;
			if (($to - $from) < $page_set && ($to - $from) < $pages) {
				$from = $pages - $page_set + 1;
			}
		}
		$more = 1;
	}
	
	$multipage['from'] = $from;
	$multipage['to'] = $to;
	$multipage['more'] = $more;
	
	if ($cur_page > 1) {
		$multipage['previous'] = 1;
	}
	
	if ($from > 1 && $more) {
		$multipage['frontmore'] = 1;
	}
	
	for ($i = $from; $i <= $to; $i++) {
		if ($i != $cur_page) {
			$multipage['pages'][$i] = $i;
		} else {
			$multipage['pages'][$i] = '';
		}
	}
	
	if ($cur_page < $pages) {
		if ($to < $pages && $more) {
			$multipage['endmore'] = 1;
		}
		$multipage['next'] = 1;
	}
	
	return $multipage;
}

/**
 * 判断email格式是否正确
 * @param $email
 */
function is_email($email) {
	return strlen($email) > 6 && preg_match("/^[\w\-\.]+@[\w\-\.]+(\.\w+)+$/", $email);
}

/**
 * IE浏览器判断
 */
function is_ie() {
	$useragent = strtolower($_SERVER['HTTP_USER_AGENT']);
	if ((strpos($useragent, 'opera') !== false) || (strpos($useragent, 'konqueror') !== false))
		return false;
	if (strpos($useragent, 'msie ') !== false)
		return true;
	return false;
}

/**
 * 文件下载
 * @param $filepath 文件路径
 * @param $filename 文件名称
 */
function file_down($filepath, $filename = '') {
	if (!$filename)
		$filename = basename($filepath);
	if (is_ie())
		$filename = rawurlencode($filename);
	$filetype = fileext($filename);
	$filesize = sprintf("%u", filesize($filepath));
	if (ob_get_length() !== false)
		@ob_end_clean();
	header('Pragma: public');
	header('Last-Modified: ' . gmdate('D, d M Y H:i:s') . ' GMT');
	header('Cache-Control: no-store, no-cache, must-revalidate');
	header('Cache-Control: pre-check=0, post-check=0, max-age=0');
	header('Content-Transfer-Encoding: binary');
	header('Content-Encoding: none');
	header('Content-type: ' . $filetype);
	header('Content-Disposition: attachment; filename="' . $filename . '"');
	header('Content-length: ' . $filesize);
	readfile($filepath);
	exit();
}

/**
 * 判断字符串是否为utf8编码，英文和半角字符返回ture
 * @param $string
 * @return bool
 */
function is_utf8($string) {
	return preg_match('%^(?:
					[\x09\x0A\x0D\x20-\x7E] # ASCII
					| [\xC2-\xDF][\x80-\xBF] # non-overlong 2-byte
					| \xE0[\xA0-\xBF][\x80-\xBF] # excluding overlongs
					| [\xE1-\xEC\xEE\xEF][\x80-\xBF]{2} # straight 3-byte
					| \xED[\x80-\x9F][\x80-\xBF] # excluding surrogates
					| \xF0[\x90-\xBF][\x80-\xBF]{2} # planes 1-3
					| [\xF1-\xF3][\x80-\xBF]{3} # planes 4-15
					| \xF4[\x80-\x8F][\x80-\xBF]{2} # plane 16
					)*$%xs', $string);
}

/**
 * Function dataformat
 * 时间转换
 * @param $n INT时间
 */
function dataformat($n) {
	$hours = floor($n / 3600);
	$minite = floor($n % 3600 / 60);
	$secend = floor($n % 3600 % 60);
	$minite = $minite < 10 ? "0" . $minite : $minite;
	$secend = $secend < 10 ? "0" . $secend : $secend;
	if ($n >= 3600) {
		return $hours . ":" . $minite . ":" . $secend;
	} else {
		return $minite . ":" . $secend;
	}
}

/**
 * 下载本地文件
 * @param array $file 文件信息数组
 * @param callable $callback 下载回调函数，一般用于增加下载次数
 * @param string $args 回调函数参数
 * @return boolean 下载失败返回false
 */
function download_file($file, $callback = null, $args = null) {
	if (is_file($file['path'])) {
		/* 调用回调函数新增下载数 */
		is_callable($callback) && call_user_func($callback, $args);
		
		/* 执行下载 */
		// TODO: 大文件断点续传
		header("Content-Description: File Transfer");
		header('Content-type: ' . $file['type']);
		header('Content-Length:' . $file['size']);
		if (preg_match('/MSIE/', $_SERVER['HTTP_USER_AGENT'])) { // for IE
			header('Content-Disposition: attachment; filename="' . rawurlencode($file['name']) . '"');
		} else {
			header('Content-Disposition: attachment; filename="' . $file['name'] . '"');
		}
		readfile($file['path']);
		exit();
	} else {
		$this->error = '文件已被删除！';
		return false;
	}
}

/**
 * Force Download
 *
 * Generates headers that force a download to happen
 *
 * @access public
 * @param string filename
 * @param mixed the data to be downloaded
 * @return void
 */
if (!function_exists('force_download')) {

	function force_download($filename = '', $data = '') {
		if ($filename == '' or $data == '') {
			return FALSE;
		}
		
		// Try to determine if the filename includes a file extension.
		// We need it in order to set the MIME type
		if (FALSE === strpos($filename, '.')) {
			return FALSE;
		}
		
		// Grab the file extension
		$x = explode('.', $filename);
		$extension = end($x);
		
		// Load the mime types
		if (defined('ENVIRONMENT') and is_file(APPPATH . 'config/' . ENVIRONMENT . '/mimes.php')) {
			include (APPPATH . 'config/' . ENVIRONMENT . '/mimes.php');
		} elseif (is_file(APPPATH . 'config/mimes.php')) {
			include (APPPATH . 'config/mimes.php');
		}
		
		// Set a default mime if we can't find it
		if (!isset($mimes[$extension])) {
			$mime = 'application/octet-stream';
		} else {
			$mime = (is_array($mimes[$extension])) ? $mimes[$extension][0] : $mimes[$extension];
		}
		
		// Generate the server headers
		if (strpos($_SERVER['HTTP_USER_AGENT'], "MSIE") !== FALSE) {
			header('Content-Type: "' . $mime . '"');
			header('Content-Disposition: attachment; filename="' . $filename . '"');
			header('Expires: 0');
			header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
			header("Content-Transfer-Encoding: binary");
			header('Pragma: public');
			header("Content-Length: " . strlen($data));
		} else {
			header('Content-Type: "' . $mime . '"');
			header('Content-Disposition: attachment; filename="' . $filename . '"');
			header("Content-Transfer-Encoding: binary");
			header('Expires: 0');
			header('Pragma: no-cache');
			header("Content-Length: " . strlen($data));
		}
		
		exit($data);
	}
}

/**
 * Create a Random String
 *
 * Useful for generating passwords or hashes.
 *
 * @access public
 * @param string type of random string. basic, alpha, alunum, numeric, nozero, unique, md5, encrypt and sha1
 * @param integer number of characters
 * @return string
 */
if (!function_exists('random_string')) {

	function random_string($type = 'alnum', $len = 8) {
		switch ($type) {
			case 'basic' :
				return mt_rand();
			break;
			case 'alnum' :
			case 'numeric' :
			case 'nozero' :
			case 'alpha' :
				
				switch ($type) {
					case 'alpha' :
						$pool = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
					break;
					case 'alnum' :
						$pool = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
					break;
					case 'numeric' :
						$pool = '0123456789';
					break;
					case 'nozero' :
						$pool = '123456789';
					break;
				}
				
				$str = '';
				for ($i = 0; $i < $len; $i++) {
					$str .= substr($pool, mt_rand(0, strlen($pool) - 1), 1);
				}
				return $str;
			break;
			case 'unique' :
			case 'md5' :
				
				return md5(uniqid(mt_rand()));
			break;
			case 'encrypt' :
			case 'sha1' :
				
				$CI = & get_instance();
				$CI->load->helper('security');
				
				return do_hash(uniqid(mt_rand(), TRUE), 'sha1');
			break;
		}
	}
}

