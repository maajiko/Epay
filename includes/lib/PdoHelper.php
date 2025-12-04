<?php
namespace lib;

class PdoHelper
{
	private $sqlPrefix = "pre_";//SQL数据表前缀识别字符
	private $db;
	private $fetchStyle = \PDO::FETCH_ASSOC;
	private $prefix;
	private $errorInfo;

	/**
	 * PdoHelper constructor.
	 *
	 * @param array $dbconfig 数据库信息
	 */
	function __construct($dbconfig)
	{
		$this->prefix = $dbconfig['dbqz'].'_';
		try {
			$this->db = new \PDO("mysql:host={$dbconfig['host']};dbname={$dbconfig['dbname']};port={$dbconfig['port']}",$dbconfig['user'],$dbconfig['pwd']);
		} catch (\Exception $e) {
			exit('链接数据库失败:' . $e->getMessage());
		}
		$this->db->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_SILENT);
		$this->db->exec("set sql_mode = ''");
		$this->db->exec("set names utf8mb4");
		$this->db->exec("set time_zone='+8:00'");
	}

	/**
	 * 设置结果集方式
	 *
	 * @param string $_style
	 */
	public function setFetchStyle($_style)
	{
		$this->fetchStyle = $_style;
	}

	/**
	 * 替换数据表前缀
	 * @param $_sql
	 *
	 * @return mixed
	 */
	private function dealPrefix($_sql){
		return str_replace($this->sqlPrefix,$this->prefix,$_sql);
	}

	private function _where($conditions){
		$result = array( "_where" => " ","_bindParams" => array());
		if(is_array($conditions) && !empty($conditions)){
			$fieldss = array(); $sql = null; $join = array();
			if(isset($conditions[0]) && $sql = $conditions[0]) unset($conditions[0]);
			foreach( $conditions as $key => $condition ){
				if(substr($key, 0, 1) != ":"){
					unset($conditions[$key]);
					$conditions[":".$key] = $condition;
				}
				$join[] = "`{$key}` = :{$key}";
			}
			if(!$sql) $sql = join(" AND ",$join);

			$result["_where"] = " WHERE ". $sql;
			$result["_bindParams"] = $conditions;
		}elseif(!empty($conditions)){
			$result["_where"] = " WHERE ". $conditions;
		}
		return $result;
	}

	private function _select($table, $fields = '*', $where = array(), $sort = null, $limit = null){
		$sort = !empty($sort) ? ' ORDER BY '.$sort : '';
		$fields = !empty($fields) ? $fields : '*';
		if(is_array($fields)){
			$fields = implode(',',$fields);
		}
		$conditions = $this->_where($where);

		$sql = ' FROM pre_'.$table.$conditions["_where"];
		if(is_array($limit)){
			$limit = ' LIMIT '.$limit[0].','.$limit[1];			
		}elseif(!empty($limit)){
			$limit = ' LIMIT '.$limit;
		}else{
			$limit = '';
		}
		return array('sql'=>'SELECT '. $fields . $sql . $sort . $limit, 'bind'=>$conditions["_bindParams"]);
	}

	/**
	 * 查询一条数据
	 * @param string $table
	 * @param string $fields
	 * @param array $where
	 * @param string $sort
	 * @param int $limit
	 *
	 * @return array
	 */
	public function find($table, $fields = '*', $where = array(), $sort = null, $limit = null){
		$sql_arr = $this->_select($table, $fields, $where, $sort, $limit);
		return $this->getRow($sql_arr['sql'], $sql_arr['bind']);
	}

	/**
	 * 查询全部数据
	 * @param string $table
	 * @param string $fields
	 * @param array $where
	 * @param string $sort
	 * @param int $limit
	 *
	 * @return array
	 */
	public function findAll($table, $fields = '*', $where = array(), $sort = null, $limit = null){
		$sql_arr = $this->_select($table, $fields, $where, $sort, $limit);
		return $this->getAll($sql_arr['sql'], $sql_arr['bind']);
	}

	/**
	 * 查询字段数据
	 * @param string $table
	 * @param string $fields
	 * @param array $where
	 * @param string $sort
	 *
	 * @return mixed
	 */
	public function findColumn($table, $fields, $where = array(), $sort = null){
		$sql_arr = $this->_select($table, $fields, $where, $sort, 1);
		return $this->getColumn($sql_arr['sql'], $sql_arr['bind']);
	}

	/**
	 * 插入数据
	 * @param string $table
	 * @param array $data
	 * @param bool $replace
	 *
	 * @return int
	 */
	public function insert($table, $data, $replace = false){
		$values = array();
		foreach ($data as $k=>$v){
			$keys[] = "`{$k}`";
			if ($v == 'NOW()' || $v == 'CURDATE()' || $v == 'CURTIME()') {
				$marks[] = $v;
			}elseif ($v === null || $v === false) {
				$marks[] = 'NULL';
			}else{
				$values[":".$k] = $v;
				$marks[] = ":".$k;
			}
		}
		$rowCount = $this->exec(($replace?"REPLACE":"INSERT")." INTO pre_".$table." (".implode(', ', $keys).") VALUES (".implode(', ', $marks).")", $values);
		if($rowCount){
			return $this->lastInsertId();
		}else{
			return false;
		}
	}

	/**
	 * 更新数据
	 * @param string $table
	 * @param array $data
	 * @param array $where
	 *
	 * @return int
	 */
	public function update($table, $data, $where){
		if(is_array($data) && !empty($data)){
			$values = array();
			foreach ($data as $k=>$v){
				if($v == 'NOW()' || $v == 'CURDATE()' || $v == 'CURTIME()'){
					$setstr[] = "`{$k}` = ".$v;
				}elseif($v === null || $v === false){
					$setstr[] = "`{$k}` = NULL";
				}else{
					$values[":M_UPDATE_".$k] = $v;
					$setstr[] = "`{$k}` = :M_UPDATE_".$k;
				}
			}
			$update = implode(', ', $setstr);
		}elseif(!empty($data)){
			$update = $data;
		}else{
			return false;
		}
		$conditions = $this->_where($where);
		$rowCount = $this->exec("UPDATE pre_".$table." SET ".$update.$conditions["_where"], $conditions["_bindParams"] + $values);
		return $rowCount;
	}

	/**
	 * 删除数据
	 * @param string $table
	 * @param array $where
	 *
	 * @return int
	 */
	public function delete($table, $where){
		$conditions = $this->_where($where);
		$rowCount = $this->exec("DELETE FROM pre_".$table.$conditions["_where"], $conditions["_bindParams"]);
		return $rowCount;
	}

	/**
	 * 统计行数
	 * @param string $table
	 * @param array $where
	 *
	 * @return int
	 */
	public function count($table, $where){
		$conditions = $this->_where($where);
		$count = $this->getColumn("SELECT COUNT(*) FROM pre_".$table.$conditions["_where"], $conditions["_bindParams"]);
		return $count;
	}


	/**
	 * 执行语句
	 * @param string $_sql
	 * @param array $_array
	 *
	 * @return int|bool
	 */
	public function exec($_sql, $_array = null)
	{
		$_sql = $this->dealPrefix($_sql);
		if (is_array($_array)) {
			$stmt = $this->db->prepare($_sql);
			if($stmt) {
				$result = $stmt->execute($_array);
				if($result!==false){
					return $stmt->rowCount();
				}else{
					$this->errorInfo = $stmt->errorInfo();
					return false;
				}
			}else{
				$this->errorInfo = $this->db->errorInfo();
				return false;
			}
		} else {
			$result = $this->db->exec($_sql);
			if($result!==false){
				return $result;
			}else{
				$this->errorInfo = $this->db->errorInfo();
				return false;
			}
		}
	}

	/**
	 * 获取PDOStatement
	 * @param string $_sql
	 * @param array $_array
	 *
	 * @return \PDOStatement
	 */
	public function query($_sql, $_array = null)
	{
		$_sql = $this->dealPrefix($_sql);
		if (is_array($_array)) {
			$stmt = $this->db->prepare($_sql);
			if($stmt) {
				if($stmt->execute($_array)){
					return $stmt;
				}else{
					$this->errorInfo = $stmt->errorInfo();
					return false;
				}
			}else{
				$this->errorInfo = $this->db->errorInfo();
				return false;
			}
		} else {
			if($stmt = $this->db->query($_sql)){
				return $stmt;
			}else{
				$this->errorInfo = $this->db->errorInfo();
				return false;
			}
		}
	}

	/**
	 * 查询一条结果
	 *
	 * @param string $_sql string
	 * @param array $_array array
	 *
	 * @return mixed
	 */
	public function getRow($_sql, $_array = null)
	{
		$stmt = $this->query($_sql, $_array);
		if($stmt) {
			return $stmt->fetch($this->fetchStyle);
		}else{
			return false;
		}
	}

	/**
	 * 获取所有结果
	 *
	 * @param string $_sql
	 * @param array $_array
	 *
	 * @return array
	 */
	public function getAll($_sql, $_array = null)
	{
		$stmt = $this->query($_sql, $_array);
		if($stmt) {
			return $stmt->fetchAll($this->fetchStyle);
		}else{
			return false;
		}
	}

	/**
	 * 获取结果数
	 * @param string $_sql
	 * @param array $_array
	 *
	 * @return int
	 */
	public function getCount($_sql, $_array = null)
	{
		$stmt = $this->query($_sql, $_array);
		if($stmt) {
			return $stmt->rowCount();
		}else{
			return false;
		}
	}

	/**
	 * 获取一个字段值
	 * @param string $_sql
	 * @param array $_array
	 *
	 * @return int
	 */
	public function getColumn($_sql, $_array = null)
	{
		$stmt = $this->query($_sql, $_array);
		if($stmt) {
			return $stmt->fetchColumn();
		}else{
			return false;
		}
	}

	/**
	 * 返回最后插入行的ID
	 *
	 * @return int|\PDOStatement
	 */
	public function lastInsertId()
	{
		return $this->db->lastInsertId();
	}

	/**
	 * 返回错误信息
	 *
	 * @return string|\PDOStatement
	 */
	public function error()
	{
		$error = $this->errorInfo;
		if($error){
			return '['.$error[1].']'.$error[2];
		}else{
			return null;
		}
	}

	//开启事务
	public function beginTransaction()
	{
		return $this->db->beginTransaction();
	}

	//提交事务
	public function commit()
	{
		return $this->db->commit();
	}

	//回滚事务
	public function rollBack()
	{
		return $this->db->rollBack();
	}

	//事务
	public function transaction($action){
		if (is_callable($action))
		{
			$this->db->beginTransaction();

			try {
				$result = $action($this);

				if ($result === false)
				{
					$this->db->rollBack();
				}
				else
				{
					$this->db->commit();
				}
			}
			catch (\Exception $e) {
				$this->db->rollBack();
				throw $e;
			}

			return $result;
		}

		return false;
	}

	function __get($name)
	{
		return $this->$name;
	}

	function __destruct()
	{
		$this->db = null;
	}


}