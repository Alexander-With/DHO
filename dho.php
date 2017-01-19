<?php
	class dho {
//==============================================================================
// DATABASE INFORMATION
		const sql_server = '#SERVER#';
		const sql_username = '#USERNAME#';
		const sql_password = '#PASSWORD#';
		const sql_database = '#DATABASE#';
//==============================================================================
// START CONSTRUCTOR
		public function __construct() {
			if(!isset($this -> conn_db)){
				$conn_db = new mysqli(self::sql_server, self::sql_username, self::sql_password, self::sql_database);
				if ($conn_db -> connect_error) {
					die("Connection failed: " . $conn_db -> connect_error);
				}
				$conn_db -> set_charset('utf8');
				$this -> conn_db = $conn_db;
				// Table Structres
				$this -> saved_tables = array();
				$this -> saved_primary = array();
			}
		}
// END CONSTRUCTOR
//==============================================================================
// START OF FUNCTION connect
		public function connect($table_name) {
			if(!isset($conn_db)){
				$conn_db = new mysqli(self::sql_server, self::sql_username, self::sql_password, self::sql_database);
				if ($conn_db -> connect_error) {
					die("Connection failed: " . $conn_db -> connect_error);
				}
				$conn_db -> set_charset('utf8');
				$this -> conn_db = $conn_db;
			}
			$this -> table_name = $table_name;
			$this -> col_names = $this -> get_col_names();
			$this -> primary = $this -> get_primary();
			return $conn_db;
		}
// END OF FUNCTION connect
//==============================================================================
// START OF FUNCTION select
		public function select($col_names = null, $search = null, $order = null, $group = null, $limit = null) {
		$conn_db = $this -> conn_db;
		$table_name = $this -> table_name;
		//Escape and implode $col_names
		if(!empty($col_names)){
			foreach ($col_names as $key => $value) {
				$safe_col_name[] = $conn_db -> real_escape_string($value);
			}
			$safe['col'] = implode(', ', $safe_col_name);
		} else{
			$safe['col'] = '*';
		}
		//Escape and implode $search
		if(!empty($search)){
			foreach ($search as $outer_key => $outer_value) {
				foreach ($search[$outer_key] as $inner_key => $inner_value) {
					$safe_search_array[$outer_key][] = $conn_db -> real_escape_string($inner_value);
				}
			}
			foreach ($search['search_key'] as $key => $value) {
				$safe_search_pairs[] = $safe_search_array['search_key'][$key].' '.$safe_search_array['search_operator'][$key].' ?';
			}
			$c = 1;
			foreach ($search['search_combinator'] as $key => $value) {
				array_splice($safe_search_pairs, $c, 0 ,$safe_search_array['search_combinator'][$key]);
				$c += 2;
			}
			$safe['search'] = ' WHERE '.implode(' ', $safe_search_pairs);
		} else{
			$safe['search'] = '';
		}
		//Escape $order
		if(!empty($order)){
			$safe['order'] = ' ORDER BY '.$conn_db -> real_escape_string($order);
		} else{
			$safe['order'] = '';
		}
		//Escape $group
		if(!empty($group)){
			$safe['group'] = ' GROUP BY '.$conn_db -> real_escape_string($group);
		} else{
			$safe['group'] = '';
		}
		//Escape $limit
		if(!empty($limit)){
			$safe['limit'] = ' LIMIT '.$conn_db -> real_escape_string($limit);
		} else{
			$safe['limit'] = '';
		}
		// Prepare the query.
		$query = 'SELECT '.$safe['col'].' FROM '.$table_name.$safe['search'].$safe['order'].$safe['group'].$safe['limit'];
		$this -> last_query = $query;
		$sql = $conn_db -> prepare($query);
		if($sql === false){
			$this -> error = 'SQL Request Failed';
			return false;
		}
		// Setting parameters if any is set.
		if(!empty($safe_search_array['search_value'])){
			$type = '';
			foreach ($safe_search_array['search_value'] as $key => $value) {
				$type .= 's';
			}
			$params[] = & $type;
			for ($i=0; $i < count($safe_search_array['search_value']); $i++) {
				if($safe_search_array['search_operator'][$i] == 'LIKE'){
					$tmp_param[$i] = '%'.$safe_search_array['search_value'][$i].'%';
					$params[] = & $tmp_param[$i];
				} else{
					$params[] = & $safe_search_array['search_value'][$i];
				}
			}
			call_user_func_array(array($sql, 'bind_param'), $params);
		}
		// Execute sql command.
		$sql -> execute();
		// Bind results;
		$sql_result = array();
		if(empty($col_names)){
			$meta = $sql -> result_metadata();
			$meta_col = $meta -> fetch_fields();
			for ($i=0; $i < $sql->field_count; $i++) {
				$safe_col_name[] = $meta_col[$i] -> name;
			}
		}
		for ($i=0; $i < count($safe_col_name); $i++) {
			$pre_result[] = 0;
		}
		for ($i=0; $i < count($safe_col_name); $i++) {
			$result[$safe_col_name[$i]] = & $pre_result[$i];
		}
		call_user_func_array(array($sql, 'bind_result'), $result);
		while($sql -> fetch()){
			foreach($result as $key=>$value){
				$row[$key] = $value;
			}
			array_push($sql_result, $row);
		}
		$sql -> close();
		// Return
		if(empty($sql_result)){
			$this -> error = 'Error: Empty';
			return false;
		}
		else{
			return $sql_result;
		}
		}
// END OF FUNCTION select
//==============================================================================
// START OF FUNCTION insert
		public function insert($values) {
			$conn_db = $this -> conn_db;
			$table_name = $this -> table_name;
			//Prepare insert data;
			$not_array = false;
			foreach ($values as $key => $value) {
				if(!is_array($value)){
					$not_array = true;
				}
			}
			if($not_array){
				foreach ($values as $key => $value) {
					$insert_data[0][$key] = $conn_db -> real_escape_string($value);
				}
			}
			else{
				foreach ($values as $outer_key => $outer_value) {
					foreach ($outer_value as $inner_key => $inner_value) {
						$insert_data[$outer_key][$inner_key] = $conn_db -> real_escape_string($inner_value);
					}
				}
			}
			// Check columns
			$db_col = $this -> col_names;
			$db_primary = $this -> primary;
			$columns = $db_col;
			if(count($db_col) != count($insert_data[0])){
				if(count($db_col)-1 == count($insert_data[0])){
					$primary = array_search($db_primary, $db_col);
					unset($columns[$primary]);
				}
				else{
					$this -> error = 'Error, Incorrect amount of data';
					return false;
				}
			}
			foreach ($insert_data[0] as $key => $value) {
				$placeholder_value[] = '?';
			}
			$query = 'INSERT INTO '.$table_name.' ('.implode(', ', $columns).') VALUES ('.implode(', ', $placeholder_value).')';
			$this -> last_query = $query;
			$sql = $conn_db -> prepare($query);
			if($sql === false){
				$this -> error = 'Error: SQL Request Failed';
				return false;
			}
			// Bind and execute SQL request
			$type = '';
			foreach ($placeholder_value as $key => $value) {
				$type .= 's';
			}
			$c = 0;
			foreach ($insert_data as $outer_key => $outer_value) {
				$params[$c][] = & $type;
				foreach ($outer_value as $inner_key => $inner_value) {
					$params[$c][] = & $insert_data[$outer_key][$inner_key];
				}
				call_user_func_array(array($sql, 'bind_param'), $params[$c]);
				$sql -> execute();
				$status[] = $sql -> affected_rows;
				$id[] = $sql -> insert_id;
				$c++;
			}
			// Close db;
			$sql -> close();
			//Status handling;
			foreach ($status as $key => $value) {
				if($value == 0){
					$this -> error = 'Error: Insert Error';
					return false;
				}
			}
			return $id;
		}
// END OF FUNCTION insert
//==============================================================================
// START OF FUNCTION delete
		public function delete($id) {
			$conn_db = $this -> conn_db;
			$table_name = $this -> table_name;
			// Build id data structure
			if(!is_array($id)){
				$id = array(intval($conn_db -> real_escape_string($id)));
			}
			foreach ($id as $key => $value) {
				$id[$key] = intval($conn_db -> real_escape_string($value));
			}
			// Check id's
			if(!$this -> check_primary($id)){
				$this -> error = 'Error: Incorrect Primary Key';
				return false;
			}
			// Prepare sql statment;
			$primary_key = $this -> primary;
			foreach ($id as $key => $value) {
				$primary[] = '?';
			}
			$deletion = '('.implode(',',$primary).')';
			$query = 'DELETE FROM '.$table_name.' WHERE '.$primary_key.' IN '.$deletion;
			$this -> last_query = $query;
			$sql = $conn_db -> prepare($query);
			// Execute sql statment
			foreach ($id as $key => $value) {
				$type_array[] = 'i';
			}
			$type = implode('', $type_array);
			$params[] = & $type;
			foreach ($id as $key => $value) {
				$params[] = & $id[$key];
			}
			call_user_func_array(array($sql, 'bind_param'), $params);
			$sql -> execute();
			// Close db;
			$status = $sql->affected_rows;
			$sql -> close();
			//Status handling;
			if($status == 0){
				return false;
			}
			else{
				return true;
			}
		}
// END OF FUNCTION delete
//==============================================================================
// START OF FUNCTION update
		public function update($values, $id) {
			$conn_db = $this -> conn_db;
			$table_name = $this -> table_name;
			$primary_key = $this -> primary;
			//Prepare update data;
			$not_array = false;
			foreach ($values as $key => $value) {
				if(!is_array($value)){
					$not_array = true;
				}
			}
			if($not_array){
				foreach ($values as $key => $value) {
					$update_data[0][$key] = $conn_db -> real_escape_string($value);
				}
			}
			else{
				foreach ($values as $outer_key => $outer_value) {
					foreach ($outer_value as $inner_key => $inner_value) {
						$update_data[$outer_key][$inner_key] = $conn_db -> real_escape_string($inner_value);
					}
				}
			}
			// Prepare Id
			if(!is_array($id)){
				$id = array(intval($conn_db -> real_escape_string($id)));
			}
			foreach ($id as $key => $value) {
				$id[$key] = intval($conn_db -> real_escape_string($value));
			}
			// Error Handeling
			if(count($update_data) != count($id)){
				if(count($update_data) < count($id)){
					$this -> error = 'Error: Missing "Update Data"';
					return false;
				}
				elseif(count($update_data) > count($id)){
					$this -> error = 'Error: Missing "Id Data"';
					return false;
				}
			}
			foreach ($update_data as $outer_key => $outer_value) {
				$query[$outer_key] = 'UPDATE '.$table_name.' SET ';
				foreach ($outer_value as $inner_key => $inner_value) {
					$imp[$outer_key][] = $inner_key.' = ?';
				}
				$query[$outer_key] .= implode(', ', $imp[$outer_key]);
				$query[$outer_key] .= ' WHERE '.$primary_key.' = ?';
			}
			foreach ($query as $key => $value) {
				$type[$key] = '';
				$sql[$key] = $conn_db -> prepare($query[$key]);
				$placeholder_value = count($update_data[$key]) + count($id[$key]);
				for ($i=0; $i < $placeholder_value; $i++) {
					$type[$key] .= 's';
				}
				$params[$key][] = & $type[$key];
				$c = 0;
				foreach ($update_data[$key] as $outer_key => $outer_value) {
					$ref[$c] = $outer_value;
					$params[$key][] = & $ref[$c];
					$c++;
				}
				$params[$key][] = & $id[$key];
				call_user_func_array(array($sql[$key], 'bind_param'), $params[$key]);
				$sql[$key] -> execute();
				$status[] = $sql[$key] -> affected_rows;
				$sql[$key] -> close();
			}
			//Status handling;
			$change = 0;
			foreach ($status as $key => $value) {
				if($value != 0){
					$change++;
				}
			}
			if($change == 0){
				$this -> error = 'Error: Nothing Updated';
				return false;
			}
			else{
				return true;
			}
		}
// END OF FUNCTION update
//==============================================================================
// START OF FUNCTION close
		public function close() {
			$conn_db = $this -> conn_db;
			if(!empty($conn_db)){
				$conn_db -> close();
			}
		}
// END OF FUNCTION close
//==============================================================================
// START OF FUNCTION print_query
		public function print_query($query) {
			if(!is_array($query)){
				echo 'No array is given.';
			}
			echo '<table style="border-collapse: collapse; border: 1px solid black;">';
			echo '<tr style="border: 1px solid black;">';
			foreach ($query[0] as $key => $value) {
				echo '<th style="border: 1px solid black;">'.$key.'</th>';
			}
			echo '</tr>';
			for ($i=0; $i < count($query); $i++) {
				echo '<tr style="border: 1px solid black;">';
				foreach ($query[$i] as $key => $value) {
					echo '<td style="border: 1px solid black;">';
						echo $query[$i][$key];
					echo '</td>';
				}
				echo '</tr>';
			}
			echo '</table>';
		}
// END OF FUNCTION print_query
//==============================================================================
// START OF FUNCTION get_col_names
		private function get_col_names() {
			$conn_db = $this -> conn_db;
			$table_name = $this -> table_name;
			if(isset($this -> saved_tables[$table_name])){
				return($this -> saved_tables[$table_name]);
			}
			$query = 'SELECT `COLUMN_NAME` FROM `INFORMATION_SCHEMA`.`COLUMNS` WHERE `TABLE_SCHEMA`="'.self::sql_database.'" AND `TABLE_NAME`="'.$table_name.'"';
			$sql = $conn_db -> prepare($query);
			$sql -> execute();

			$sql -> bind_result($col_names);
			while($sql -> fetch()){
				$sql_result[] = $col_names;
			}
			$this -> saved_tables[$table_name] = $sql_result;
			return $sql_result;
		}
// END OF FUNCTION get_col_names
//==============================================================================
// START OF FUNCTION get_primary
	private function get_primary() {
		$conn_db = $this -> conn_db;
		$table_name = $this -> table_name;
		if(isset($this -> saved_primary[$table_name]))
			return($this -> saved_primary[$table_name]);
		$query = 'SHOW KEYS FROM '.$table_name.' WHERE Key_name = "PRIMARY"';
		$sql = $conn_db -> prepare($query);
		$sql -> execute();
		$sql -> bind_result(
			$params['Table'],
			$params['Non_unique'],
			$params['Key_name'],
			$params['Seq_in_index'],
			$params['Column_name'],
			$params['Collation'],
			$params['Cardinality'],
			$params['Sub_part'],
			$params['Packed'],
			$params['Null'],
			$params['Index_type'],
			$params['Comment'],
			$params['Index_comment']
		);
		$sql -> fetch();
		$sql_result = $params['Column_name'];
		$this -> saved_primary[$table_name] = $sql_result;
		if(!isset($sql_result))
			return false;
		else
			return $sql_result;
	}
// END OF FUNCTION get_primary
//==============================================================================
// START OF FUNCTION check_primary
		private function check_primary($id) {
			$conn_db = $this -> conn_db;
			$table_name = $this -> table_name;
			$primary = $this -> get_primary();
			$search = array(
				'search_key'=>
					array(),
				'search_value'=>
					array(),
				'search_operator'=>
					array(),
				'search_combinator'=>
					array()
			);
			foreach ($id as $key => $value) {
				$search['search_key'][] = $primary;
				$search['search_value'][] = $value;
				$search['search_operator'][] = '=';
			}
			for ($i=1; $i < count($id); $i++) {
				$search['search_combinator'][] = 'OR';
			}
			$sql = $this -> select(null, $search);
			$error = false;
			if(count($id) != count($sql))
				return false;
			else
				return true;
		}
// END OF FUNCTION check_primary
//==============================================================================
// START OF Destructor
		public function __destruct() {
			/* Closes The database connection */
			$conn_db = $this -> conn_db;
			if(!empty($conn_db)){
				$conn_db -> close();
			}
		}
// END OF Destructor
//==============================================================================
}
// END OF CLASS
