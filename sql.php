<?php

class sql {

	public static $sqls = array();
	public static $configs = array();
	public static $dbhs = array();
	public static $db_config = array();
    private static $sql_error = FALSE;
    private static $is_trans = FALSE;

    public function __construct() {
        require_once 'config.php';
		self::$db_config = $db_config;
	}

	/**
	 * 数据库配置
	 * @param string $type
	 */
	public static function setconfig($type = 'master') {
		$db_config = self::$db_config;
		$configs[$type]['dsn'] = "mysql:host={$db_config[$type]['host']}; port={$db_config[$type]['port']}; dbname={$db_config[$type]['dbname']}";
		$configs[$type]['username'] = $db_config[$type]['username'];
		$configs[$type]['password'] = $db_config[$type]['password'];
		self::$configs = $configs;
	}

	/**
	 * 获取一个PDO对象
	 * @param string $type   类型 [master|slave] 主从
	 * @return PDO
	 */
	public static function getPdo($type = 'master') {
		self::setconfig($type);
		$key = $type;
		if (!isset(self::$dbhs[$key])) {
			$dbh = new PDO(self::$configs[$type]['dsn'], self::$configs[$type]['username'], self::$configs[$type]['password'], array());
			$dbh->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
			$dbh->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
			$dbh->exec('SET CHARACTER SET utf8');
			self::$dbhs[$key] = $dbh;
		}
		return self::$dbhs[$key];
	}

	public function close() {
		if (!self::$dbhs) {
			return false;
		}
		foreach (self::$dbhs as $key => $dbh) {
			unset(self::$dbhs[$key]);
		}
		return true;
	}

	/**
	 * 查询出所有的记录
	 * @param string $sql
	 * @param array $params
	 * @return PDOStatement
	 */
	public static function execQuery($sql, $params = null) {
		$pdo = self::$is_trans ? self::getPdo('master') : self::getPdo('slave');
		self::_paramParse($sql, $params);
		$sth = self::_preExec($pdo, $sql, $params);
		return $sth;
	}

    public function build_key($data, $fields) {
        $fields = explode(',', $fields);
        $temp = array();
        foreach ($data as $key => $value) {
            if (in_array($key, $fields)) {
                $temp[$key] = $value;
            }
        }
        return $temp;
    }

	
	/**
	 * 将数据插入到指定表中
	 * @param string $tableName
	 * @param string $data  要insert到表中的数据
	 * @param string $get_last_insert_id 是否获取最后插入ID
	 */
	public static function insert($tableName, $data, $get_last_insert_id = false) {
        $data_keys = array_keys($data);
        $keys = array();
        foreach($data_keys as $row){
            $keys[] = '`'.trim($row,'`').'`';
        }
		$sql = "insert into `{$tableName}`(" . join(",",$keys ) . ") values(" . rtrim(str_repeat("?,", count($data)), ",") . ")";
        $params = array_values($data);
        $pdo = self::getPdo('master');
		$sth = self::_preExec($pdo, $sql, $params);
		if (is_array($sth) && $sth['error_code']) {
			return false;
		}
		if ($get_last_insert_id) {
			$pdo = self::getPdo('master');
			return $pdo->lastInsertId();
		} else {
			return true;
		}
	}

	/**
	 * 将数据批量插入到指定表中
	 * @param string $tableName
	 * @param string $data_array  要insert到表中的数据
	 * @param string $get_last_insert_id 是否获取最后插入ID
	 */
	public static function insertMulti($tableName, $data_array) {
		$data = current($data_array);
        $data_keys = array_keys($data);
        $keys = array();
        foreach($data_keys as $row){
            $keys[] = '`'.trim($row,'`').'`';
        }
		$current_parameters = null;
		$sql = "insert into `{$tableName}`(" . join(",", $keys) . ") values(" . rtrim(str_repeat("?,", count($data)), ",") . ")";
		$pdo = self::getPdo('master');
		try {
			$sth = $pdo->prepare($sql);
			reset($data_array);
			foreach ($data_array as $params) {
				$current_parameters = array_values($params);
				$sth->execute($current_parameters);
			}
		} catch (Exception $e) {
            self::$sql_error = TRUE;
			$error_log = $e->getMessage() . '||sql:' . $sql . '||data:' . json_encode($current_parameters);
			//writeLog($error_log, 'sql_error');
			return false;
		}
		return true;
	}

	/**
	 * 对指定表进行更新操作
	 * rareDb::update('tableName',array('title'=>'this is title','content'=>'this is content'),'id=?',array(12));
	 * @param string $tableName
	 * @param array $data   要进行更新的数据  array('title'=>'this is title','hitNum=hitNum+1')
	 * @param string $where
	 * @param string $whereParam
	 * @param string $dbName
     * @param bool $return_row 返回真实的影响行数
	 */
	public static function update($tableName, $data, $where, $whereParam = null, $dbName = null,$return_row = FALSE) {
		if (is_string($data))
			$data = array($data);
		$sql = "UPDATE `{$tableName}` SET ";
		$tmp = array();
		$param = array();
		foreach ($data as $k => $v) {
			if (is_int($k)) {   //如  hitNum=hitNum+1，可以是直接的函数
				$tmp[] = $v;
			} else {		   //其他情况全部使用占位符 'title'=>'this is title'
				$tmp[] = "`{$k}`=:k_{$k}";
				$param[":k_" . $k] = $v;
			}
		}
		$where = self::filters($where);
		self::_paramParse($where, $whereParam);
		$param = array_merge($param, $whereParam);
		$sql.=join(",", $tmp) . " {$where}";
		$result = self::exec($sql, $param);
		if (is_array($result) && $result['error_code']) {
			return false;
		}
		if (!$return_row && !$result) {
			$result = true;
		}
		return $result;
	}
	/**
	 * 对指定表进行删除操作
	 * 如Db::delete('tableName',"id=?",array(1));
	 * @param string $tableName
	 * @param string $where
	 * @param array $whereParam
	 * @param string $dbName
	 */
	public static function delete($tableName, $where, $whereParam = null, $dbName = null) {
		self::_paramParse($where, $whereParam);
		$param = $whereParam;
		$sql = "delete from `{$tableName}` where {$where}";
		return self::exec($sql, $param, $dbName);
	}
	/**
	 * @param string $sql
	 * @param array $params 当参数只有一个时也可以直接写参数而不需要写成数组
	 */
	public static function query($sql, $params = null) {
		$fetch_all = false;
		return self::select($sql, $params, $fetch_all);
	}
	/**
	 * 查询出所有的记录
	 * @param string $sql
	 * @param array $params
	 */
	public static function queryAll($sql, $params = null) {
		return self::select($sql, $params);
	}

	/*
	 * @param string $sql
	 * @param string|array $params
	 * @param string $dbName
	 * @param bllean $fetchAll 是否获取全部结果集
	 */

	protected static function select($sql, $params = null, $fetchAll = true) {
		$sth = self::execQuery($sql, $params);
		if (is_array($sth) && isset($sth['error_code'])) {
			return null;
		}
		return $fetchAll ? $sth->fetchAll() : $sth->fetch();
	}

	/**
	 * 
	 * @param string $sql
	 * @param array $params
	 * @param string $dbName
	 * @return int
	 */
	public static function exec($sql, $params = null) {
		$pdo = self::getPdo('master');
		self::_paramParse($sql, $params);
		$sth = self::_preExec($pdo, $sql, $params);
		if (is_array($sth) && $sth['error_code']) {
			return $sth;
		}
		return $sth->rowCount();
	}

	/**
	 * @param PDO $pdo
	 * @param string $sql
	 * @param array $params
	 * @throws Exception
	 * @return PDOStatement
	 */
	private static function _preExec($pdo, $sql, $params) {
		//writeLog('sql:'.$sql.'||params:'.$params,'sql_log');
		try {
			$sth = $pdo->prepare($sql);
			$sth->execute($params);
		} catch (Exception $e) {
			$error_info = $e->errorInfo;
			$data['error_code'] = $error_info[1];
			$data['error'] = $error_info[2];
			if ($data['error_code'] == 2006) {
				self::close();
				$pdo = self::getPdo('master');
				return self::_preExec($pdo, $sql, $params);
			}
            self::$sql_error = TRUE;
			$error_log = $e->__toString() . '||sql:' . $sql . '||data:' . json_encode($params);
			//writeLog($error_log, 'sql_error');
			return $data;
		}
		return $sth;
	}
	/**
	 * 自动生成条件语句
	 *
	 * @param array $filters
	 * @return string
	 */
	public function filters($filters) {
		$sql_where = '';
		if (is_array($filters)) {
			foreach ($filters as $f => $v) {
				$f_type = gettype($v);
				if ($f_type == 'array') {
					$sql_where .= ($sql_where ? " AND " : "") . "(`{$f}` " . $v ['operator'] . " '" . $v ['value'] . "')";
				} elseif ($f_type == 'string')
					$sql_where .= ($sql_where ? " OR " : "") . "(`{$f}` LIKE '%{$v}%')";
				else {
					$sql_where .= ($sql_where ? " AND " : "") . "(`{$f}` = '{$v}')";
				}
			}
		} elseif (strlen($filters)) {
			$sql_where = $filters;
		} else
			return '';
		$sql_where = $sql_where ? " WHERE " . $sql_where : '';
		return $sql_where;
	}

	/**
	 * 对sql语句进行预处理，同时对参数进行同步处理 ,以实现在调用时sql和参数多种占位符格式支持
	 * 如 $where="id=1" , $params=1 处理成$where="id=:id",$params['id']=1
	 * @param string $where
	 * @param array $params
	 */
	public static function _paramParse(&$where, &$params) {
		if (is_null($params)) {
			$params = array();
			return;
		};

		if (!is_array($params))
			$params = array($params);
		$_first = each($params);
		$tmp = array();
		if (!is_int($_first['key'])) {
			foreach ($params as $_k => $_v) {
				$tmp[":" . ltrim($_k, ":")] = $_v;
			}
		} else {
			preg_match_all("/`?([\w_]+)`?\s*[\=<>!]+\s*\?\s+/i", $where . " ", $matches, PREG_SET_ORDER);
			if ($matches) {
				foreach ($matches as $_k => $matche) {
					$fieldName = ":" . $matche[1]; //字段名称
					$i = 0;
					while (array_key_exists($fieldName, $params)) {
						$fieldName = ":" . $matche[1] . "_" . ($i++);
					}
					$where = str_replace(trim($matche[0]), str_replace("?", $fieldName, $matche[0]), $where);
					if (array_key_exists($_k, $params)) {
						$tmp[$fieldName] = $params[$_k];
					}
				}
			}
		}
		$params = $tmp;

		//------------------------------------------
		//fix sql like: select * from table_name where id in(:ids)
		preg_match_all("/\s+in\s*\(\s*(\:\w+)\s*\)/i", $where . " ", $matches, PREG_SET_ORDER);

		if ($matches) {
			foreach ($matches as $_k => $matche) {

				$fieldName = trim($matche[1], ":");

				$_val = $params[$matche[1]];

				if (!is_array($_val)) {
					$_val = explode(",", addslashes($_val));
				}

				$_tmpStrArray = array();
				foreach ($_val as $_item) {
					$_tmpStrArray[] = is_numeric($_item) ? $_item : "'" . $_item . "'";
				}
				$_val = implode(",", $_tmpStrArray);
				$where = str_replace($matche[0], " In (" . $_val . ") ", $where);

				unset($params[$matche[1]]);
			}
		}
		//==========================================
	}
    
    public static function trans_begin(){
        $pdo = self::getPdo('master');
        return $pdo->beginTransaction();
    }
    
    public static function trans_end(){
        $pdo = self::getPdo('master');
        if(self::$sql_error){
            if(!$pdo->rollBack()){
                $error_log = 'transaction rollback fail.[time:'.time().']';
                //writeLog($error_log,'sql_error');
            }
            self::$sql_error = FALSE;
            self::$is_trans = FALSE;
            return FALSE;
        }
        self::$sql_error = FALSE;
        self::$is_trans = FALSE;
        if(!$pdo->commit()){
            $error_log = 'transaction commit fail.[time:'.time().']';
            //writeLog($error_log,'sql_error');
            return FALSE;
        }
        return TRUE;
    }
}
