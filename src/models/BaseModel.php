<?php
// namespace APP\Model;

/**
 * 数据模型基类
 */
class Model {
	
	// 数据库连接
	protected $_db = null;
	// 数据表名
	protected $_table = '';
	// 表主键
	public $_pkey = '';
	// 模型数据
	public $_data = array();
	// 跟踪数据
	protected $_trace = array();
	
	/**
	 * Model 构造函数
	 * @param string $table
	 * @param string $pkey
	 */
	public function __construct($table, $pkey = 'id') {
		if ($this->_db == null or !($this->_db instanceof DBFactory)) {
			require_once (dirname(__FILE__) . '/init.php');
			$this->_db = DBFactory::get_instance();
		}
		if (!isset($table))
			$this->_table = get_class($this);
		else
			$this->_table = $table;
		
		$this->_pkey = $pkey;
	}
	
	/**
	 *
	 * @param array $data
	 */
	public function set_data($data) {
		if (is_array($data))
			$this->_data = $data;
	}
	
	/**
	 *
	 * @return Ambigous <multitype:, array, unknown>
	 */
	public function get_data() {
		return $this->_data;
	}
	
	/**
	 * 设置模型数据项（字段与值）
	 */
	public function __set($name, $value) {
		$this->_data[$name] = $value;
	}
	
	/**
	 * 获取模型数据值
	 */
	public function __get($name) {
		if (isset($this->_data[$name]) && $this->_data[$name] instanceof Model)
			return $this->_data[$name];
		
		if (property_exists($this->_db, $name))
			return $this->_db->$name;
	}
	public function __isset($name) {
		if (isset($this->_data[$name]))
			return true;
		
		if (property_exists($this->_db, $name))
			return isset($this->_db->$name);
	}
	public function __unset($name) {
		unset($this->_data[$name]);
	}
	
	/**
	 * 生成新的模型类
	 */
	public static function make($table_name) {
		$table_name = preg_replace("/[^-a-z0-9_]+/i", '', $table_name);
		if (!class_exists($table_name))
			eval("class $table_name extends Model{}");
		return new $table_name();
	}
	
	/**
	 *
	 * @return mixed insert id or false in case of failure
	 */
	public function insert() {
		$this->_trace['created_at'] = date("Y-m-d H:i:s");
		$sql_data = $this->prepare();
		if (!$this->validate($sql_data))
			return false;
		
		$id = $this->db->insert($this->_table, $sql_data);
		if (!empty($this->_pkey) && !isset($this->_data[$this->_pkey]))
			$this->_data[$this->_pkey] = $id;
		$this->_trace['is_new'] = false;
		
		return $id;
	}
	
	/**
	 *
	 * @param array $data Optional update data to apply to the object
	 */
	public function update($data = null) {
		if (empty($this->_fields))
			return false;
		
		if (empty($this->data[$this->_pkey]))
			return false;
		
		if ($data) {
			foreach ( $data as $k => $v )
				$this->$k = $v;
		}
		
		$this->_trace['updated_at'] = date("Y-m-d H:i:s");
		
		$sql_data = $this->prepare();
		if (!$this->validate($sql_data))
			return false;
		
		$this->_db->where($this->_pkey, $this->_data[$this->_pkey]);
		return $this->_db->update($this->_table, $sql_data);
	}
	public function update($data, $id, $key) {
		// if (!is_numeric($id)) return -1;
		if (！isset($key) || empty($key))
			$key = $this->_pkey;
		
		$sql = "UPDATE {$this->_table} SET ";
		
		$updates = array();
		if (is_array($data)) {
			foreach ( $data as $column => $value ) {
				if (is_string($value))
					$val = '"' . $val . '"';
				$updates[] = '`' . $column . '`' . '=' . $value;
			}
		} else
			return false;
		
		$sql .= implode(', ', $updates);
		$sql .= " WHERE `$key`='$id'";
		
		$this->_db->query($sql);
		return $this->_db->affected_rows();
	}
	
	/**
	 * 保存或者更新一条数据
	 * @return mixed insert id or false in case of failure
	 */
	public function save($data = null) {
		if ($this->_trace['is_new'])
			return $this->insert();
		return $this->update($data);
	}
	
	/**
	 * 删除当前数据
	 *
	 * @return boolean Indicates success. 0 or 1.
	 */
	public function delete() {
		if (empty($this->data[$this->_pkey]))
			return false;
		
		$this->db->where($this->_pkey, $this->data[$this->_pkey]);
		return $this->db->delete($this->_table);
	}
	public function fetch($limit = null, $fields = null) {
		if (is_null($key)) {
			$key = $this->_primaryKey;
		}
		$sql = "SELECT * FROM {$this->_table} WHERE `{$key}`='{$value}'";
		$rows = $this->_db->fetch($sql);
		
		foreach ( $rows as $key => $val ) {
		}
		
		return $rows;
	}
	
	/**
	 * Function to join object with another object.
	 *
	 * @access public
	 * @param string $objectName Object Name
	 * @param string $key Key for a join from primary object
	 * @param string $joinType SQL join type: LEFT, RIGHT, INNER, OUTER
	 *       
	 * @return Model
	 */
	private function join($object_name, $key = null, $joinType = 'LEFT') {
		$obj = new $object_name();
		if (!$key)
			$key = $object_name . "id";
		$joinStr = MysqliDb::$prefix . $this->_table . ".{$key} = " . MysqliDb::$prefix . "{$obj->_table}.{$obj->_pkey}";
		$this->db->join($obj->_table, $joinStr, $joinType);
		return $this;
	}
	
	/**
	 *
	 * @param array $data
	 */
	private function process(&$data) {
		if (isset($this->_json_fields) && is_array($this->_json_fields)) {
			foreach ( $this->_json_fields as $key )
				$data[$key] = json_decode($data[$key]);
		}
		
		if (isset($this->_array_fields) && is_array($this->_array_fields)) {
			foreach ( $this->_array_fields as $key )
				$data[$key] = explode("|", $data[$key]);
		}
	}
	
	/**
	 *
	 * @param array $data
	 */
	private function validate($data) {
		if (!$this->_fields)
			return true;
		
		foreach ( $this->_fields as $key => $desc ) {
			$type = null;
			$required = false;
			if (isset($data[$key]))
				$value = $data[$key];
			else
				$value = null;
			
			if (is_array($value))
				continue;
			
			if (isset($desc[0]))
				$type = $desc[0];
			if (isset($desc[1]) && ($desc[1] == 'required'))
				$required = true;
			
			if ($required && strlen($value) == 0) {
				$this->errors[] = Array(
						$this->_table . "." . $key => "is required" 
				);
				continue;
			}
			if ($value == null)
				continue;
			
			switch ($type) {
				case "text" :
					$regexp = null;
				break;
				case "int" :
					$regexp = "/^[0-9]*$/";
				break;
				case "bool" :
					$regexp = '/^[yes|no|0|1|true|false]$/i';
				break;
				case "datetime" :
					$regexp = "/^[0-9a-zA-Z -:]*$/";
				break;
				default :
					$regexp = $type;
				break;
			}
			if (!$regexp)
				continue;
			
			if (!preg_match($regexp, $value)) {
				$this->_trace['errors'][] = Array(
						$this->_table . "." . $key => "$type validation failed" 
				);
				continue;
			}
		}
		return !count($this->_trace['errors']) > 0;
	}
	private function prepare() {
		$this->_trace['errors'] = Array();
		$sql_data = Array();
		if (count($this->_data) == 0)
			return Array();
		
		if (!$this->_fields)
			return $this->_data;
		
		foreach ( $this->_data as $key => &$value ) {
			if ($value instanceof Model && $value->_trace['is_new']) {
				$id = $value->save();
				if ($id)
					$value = $id;
				else
					$this->_trace['errors'] = array_merge($this->_trace['errors'], $value->errors);
			}
			
			if (!in_array($key, array_keys($this->_fields)))
				continue;
			
			if (!is_array($value)) {
				$sql_data[$key] = $value;
				continue;
			}
			
			if (isset($this->_json_fields) && in_array($key, $this->_json_fields))
				$sql_data[$key] = json_encode($value);
			else if (isset($this->_array_fields) && in_array($key, $this->_array_fields))
				$sql_data[$key] = implode("|", $value);
			else
				$sql_data[$key] = $value;
		}
		return $sql_data;
	}
	
	/**
	 * Converts object data to an associative array.
	 *
	 * @return array Converted data
	 */
	public function toArray() {
		$data = $this->_data;
		foreach ( $data as &$d ) {
			if ($d instanceof Model)
				$d = $d->_data;
		}
		return $data;
	}
	
	/**
	 * Converts object data to a JSON string.
	 *
	 * @return string Converted data
	 */
	public function toJson() {
		return json_encode($this->toArray());
	}
	
	/**
	 * Converts object data to a JSON string.
	 *
	 * @return string Converted data
	 */
	public function __toString() {
		return $this->toJson();
	}
}