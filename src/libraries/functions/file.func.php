<?php

/**
 * 读取并返回指定文件的文本内容
 *
 * @access	public
 * @param	string	path to file
 * @return	string
 */
if (!function_exists('read_file')) {

	function read_file($file) {
		if (!file_exists($file)) {
			return FALSE;
		}
		
		if (function_exists('file_get_contents')) {
			return file_get_contents($file);
		}
		
		if (!$fp = @fopen($file, FOPEN_READ)) {
			return FALSE;
		}
		
		flock($fp, LOCK_SH);
		
		$data = '';
		if (filesize($file) > 0) {
			$data = & fread($fp, filesize($file));
		}
		
		flock($fp, LOCK_UN);
		fclose($fp);
		
		return $data;
	}
}

/**
 * 将文本写入指定文件
 *
 * @access public
 * @param string path to file
 * @param string file data
 * @return bool
 */
if (!function_exists('write_file')) {

	function write_file($path, $data, $mode = FOPEN_WRITE_CREATE_DESTRUCTIVE) {
		if ( ! $fp = @fopen($path, $mode)) {
			return FALSE;
		}

		flock($fp, LOCK_EX);
		fwrite($fp, $data);
		flock($fp, LOCK_UN);
		fclose($fp);

		return TRUE;
	}
}

/**
 * Delete Files
 *
 * Deletes all files contained in the supplied directory path.
 * Files must be writable or owned by the system in order to be deleted.
 * If the second parameter is set to TRUE, any directories contained
 * within the supplied base directory will be nuked as well.
 *
 * @access public
 * @param string path to file
 * @param bool whether to delete any directories found in the path
 * @return bool
 */
if (!function_exists('delete_files')) {

	function delete_files($path, $del_dir = FALSE, $level = 0) {
		// Trim the trailing slash
		$path = rtrim($path, DIRECTORY_SEPARATOR);

		if ( ! $current_dir = @opendir($path))
		{
			return FALSE;
		}

		while (FALSE !== ($filename = @readdir($current_dir)))
		{
			if ($filename != "." and $filename != "..")
			{
				if (is_dir($path.DIRECTORY_SEPARATOR.$filename))
				{
					// Ignore empty folders
					if (substr($filename, 0, 1) != '.')
					{
						delete_files($path.DIRECTORY_SEPARATOR.$filename, $del_dir, $level + 1);
					}
				} else {
					unlink($path.DIRECTORY_SEPARATOR.$filename);
				}
			}
		}
		@closedir($current_dir);

		if ($del_dir == TRUE AND $level > 0) {
			return @rmdir($path);
		}

		return TRUE;
	}
}

/**
 * Get Filenames
 *
 * Reads the specified directory and builds an array containing the filenames.
 * Any sub-folders contained within the specified path are read as well.
 *
 * @access public
 * @param string path to source
 * @param bool whether to include the path as part of the filename
 * @param bool internal variable to determine recursion status - do not use in calls
 * @return array
 */
if (!function_exists('get_filenames')) {

	function get_filenames($source_dir, $include_path = FALSE, $_recursion = FALSE) {
		static $_filedata = array();
		
		if ($fp = @opendir($source_dir)) {
			// reset the array and make sure $source_dir has a trailing slash on the initial call
			if ($_recursion === FALSE) {
				$_filedata = array();
				$source_dir = rtrim(realpath($source_dir), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
			}
			
			while (FALSE !== ($file = readdir($fp))) {
				if (@is_dir($source_dir . $file) && strncmp($file, '.', 1) !== 0) {
					get_filenames($source_dir . $file . DIRECTORY_SEPARATOR, $include_path, TRUE);
				} elseif (strncmp($file, '.', 1) !== 0) {
					$_filedata[] = ($include_path == TRUE) ? $source_dir . $file : $file;
				}
			}
			return $_filedata;
		} else {
			return FALSE;
		}
	}
}

/**
 * Get Directory File Information
 *
 * Reads the specified directory and builds an array containing the filenames,
 * filesize, dates, and permissions
 *
 * Any sub-folders contained within the specified path are read as well.
 *
 * @access public
 * @param string path to source
 * @param bool Look only at the top level directory specified?
 * @param bool internal variable to determine recursion status - do not use in calls
 * @return array
 */
if (!function_exists('get_dir_file_info')) {

	function get_dir_file_info($source_dir, $top_level_only = TRUE, $_recursion = FALSE) {
		static $_filedata = array();
		$relative_path = $source_dir;
		
		if ($fp = @opendir($source_dir)) {
			// reset the array and make sure $source_dir has a trailing slash on the initial call
			if ($_recursion === FALSE) {
				$_filedata = array();
				$source_dir = rtrim(realpath($source_dir), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
			}
			
			// foreach (scandir($source_dir, 1) as $file) // In addition to being PHP5+, scandir() is simply not as fast
			while (FALSE !== ($file = readdir($fp))) {
				if (@is_dir($source_dir . $file) and strncmp($file, '.', 1) !== 0 and $top_level_only === FALSE) {
					get_dir_file_info($source_dir . $file . DIRECTORY_SEPARATOR, $top_level_only, TRUE);
				} elseif (strncmp($file, '.', 1) !== 0) {
					$_filedata[$file] = get_file_info($source_dir . $file);
					$_filedata[$file]['relative_path'] = $relative_path;
				}
			}
			
			return $_filedata;
		} else {
			return FALSE;
		}
	}
}

/**
 * Get File Info
 *
 * Given a file and path, returns the name, path, size, date modified
 * Second parameter allows you to explicitly declare what information you want returned
 * Options are: name, server_path, size, date, readable, writable, executable, fileperms
 * Returns FALSE if the file cannot be found.
 *
 * @access public
 * @param string path to file
 * @param mixed array or comma separated string of information returned
 * @return array
 *
 */
if (!function_exists('get_file_info')) {

	function get_file_info($file, $returned_values = array('name', 'server_path', 'size', 'date')) {
		if (!file_exists($file)) {
			return FALSE;
		}
		
		if (is_string($returned_values)) {
			$returned_values = explode(',', $returned_values);
		}
		
		foreach ($returned_values as $key) {
			switch ($key) {
				case 'name' :
					$fileinfo['name'] = substr(strrchr($file, DIRECTORY_SEPARATOR), 1);
				break;
				case 'server_path' :
					$fileinfo['server_path'] = $file;
				break;
				case 'size' :
					$fileinfo['size'] = filesize($file);
				break;
				case 'date' :
					$fileinfo['date'] = filemtime($file);
				break;
				case 'readable' :
					$fileinfo['readable'] = is_readable($file);
				break;
				case 'writable' :
					// There are known problems using is_weritable on IIS. It may not be reliable - consider fileperms()
					$fileinfo['writable'] = is_writable($file);
				break;
				case 'executable' :
					$fileinfo['executable'] = is_executable($file);
				break;
				case 'fileperms' :
					$fileinfo['fileperms'] = fileperms($file);
				break;
			}
		}
		
		return $fileinfo;
	}
}

/**
 * Get Mime by Extension
 *
 * Translates a file extension into a mime type based on config/mimes.php.
 * Returns FALSE if it can't determine the type, or open the mime config file
 *
 * Note: this is NOT an accurate way of determining file mime types, and is here strictly as a convenience
 * It should NOT be trusted, and should certainly NOT be used for security
 *
 * @access public
 * @param string path to file
 * @return mixed
 */
if (!function_exists('get_mime_by_extension')) {

	function get_mime_by_extension($file) {
		$extension = strtolower(substr(strrchr($file, '.'), 1));
		
		global $mimes;
		
		if (!is_array($mimes)) {
			if (defined('ENVIRONMENT') and is_file(APPPATH . 'config/' . ENVIRONMENT . '/mimes.php')) {
				include (APPPATH . 'config/' . ENVIRONMENT . '/mimes.php');
			} elseif (is_file(APPPATH . 'config/mimes.php')) {
				include (APPPATH . 'config/mimes.php');
			}
			
			if (!is_array($mimes)) {
				return FALSE;
			}
		}
		
		if (array_key_exists($extension, $mimes)) {
			if (is_array($mimes[$extension])) {
				// Multiple mime types, just give the first one
				return current($mimes[$extension]);
			} else {
				return $mimes[$extension];
			}
		} else {
			return FALSE;
		}
	}
}

/**
 * Create a Directory Map
 *
 * Reads the specified directory and builds an array
 * representation of it.  Sub-folders contained with the
 * directory will be mapped as well.
 *
 * @access	public
 * @param	string	path to source
 * @param	int		depth of directories to traverse (0 = fully recursive, 1 = current dir, etc)
 * @return	array
 */
if ( ! function_exists('directory_map'))
{
	function directory_map($source_dir, $directory_depth = 0, $hidden = FALSE)
	{
		if ($fp = @opendir($source_dir))
		{
			$filedata	= array();
			$new_depth	= $directory_depth - 1;
			$source_dir	= rtrim($source_dir, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR;

			while (FALSE !== ($file = readdir($fp)))
			{
				// Remove '.', '..', and hidden files [optional]
				if ( ! trim($file, '.') OR ($hidden == FALSE && $file[0] == '.'))
				{
					continue;
				}

				if (($directory_depth < 1 OR $new_depth > 0) && @is_dir($source_dir.$file))
				{
					$filedata[$file] = directory_map($source_dir.$file.DIRECTORY_SEPARATOR, $new_depth, $hidden);
				}
				else
				{
					$filedata[] = $file;
				}
			}

			closedir($fp);
			return $filedata;
		}

		return FALSE;
	}
}
