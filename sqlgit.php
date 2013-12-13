<?php

/**
 * SQLGit
 *
 * SQL Schema version control with Git
 *
 * @author James Pegg <jamescpegg@gmail.com>
 */

if (!class_exists('PDO')) {
	trigger_error('SQLGit requires PDO.', E_USER_ERROR);
}

if (php_sapi_name() !== 'cli') {
	trigger_error('SQLGit should only be run from the terminal.', E_USER_ERROR);
}

$opts  = 'u:';	// User
$opts .= 'p:'; 	// Password
$opts .= 'd:';  // Database
$opts .= 's::';	// Save
$opts .= 'm::';	// Merge

$options = getopt($opts);


/**
 * Argument Checks
 */

if (!isset($options['u'])) {
	trigger_error('No user defined.', E_USER_ERROR);
} else {
	$user = $options['u'];
	unset($options['u']);
}

if (!isset($options['p'])) {
	trigger_error('No password defined.', E_USER_ERROR);
} else {
	$password = $options['p'];
	unset($options['p']);
}

if (!isset($options['d'])) {
	trigger_error('No database defined.', E_USER_ERROR);
} else {
	$database = $options['d'];
	unset($options['d']);
}

if (!(isset($options['s']) OR isset($options['m']))) {
	trigger_error('No command given.', E_USER_ERROR);
} else {
	$command = end(array_keys($options));
}

/**
 * Database Connection
 */

try {
	$connection = new PDO('mysql:host=localhost;dbname=' . $database, $user, $password);
} catch (\PDOException $e) {
	trigger_error('Could not connect to database : ' . $e->getMessage(), E_USER_ERROR);
}

/**
 * Current Schema
 */

$current = Array();

$tables = $connection->prepare('SHOW TABLES');
$tables->execute();
$tables = $tables->fetchAll(PDO::FETCH_NUM);

foreach ($tables as $table) {
	$current[$table[0]] = [];
}

foreach ($current as $table => $data) {
	$columns = $connection->prepare("DESCRIBE `{$table}`");
	$columns->execute();
	$columns = $columns->fetchAll(PDO::FETCH_ASSOC);

	foreach ($columns as $column) {
		$current[$table][$column['Field']] = $column;
	}
}

switch ($command) {

	// Save the current schema
	case 's':
		$file = fopen('schema', 'w+');
		fwrite($file, serialize($current));
		fclose($file);
		break;

	case 'm':
		if (is_readable('schema')) {
			$saved = unserialize(file_get_contents('schema'));

			$connection->beginTransaction();

			foreach ($saved as $table => $columns) {
				if (!isset($current[$table])) {

					// Create Table
					$connection->exec(Schema::createTable($table, $columns));

				} else {

					foreach ($columns as $column => $data) {

						if (!isset($current[$table][$column])) {

							// Create Column
							$connection->exec(Schema::createColumn($table, $data));

						} else {
							$diff = array_diff_assoc($data, $current[$table][$column]);

							if (!empty($diff)) {

								// Alter current schema
								$connection->exec(Schema::alterColumn($table, $data));

								// Alter current indexes
								if ($index = Schema::createIndex($table, $data)) {
									$connection->exec($index);
								}
							}
						}
					}
				}
			}

			foreach ($current as $table => $columns) {
				if (!isset($saved[$table])) {

					// Drop Table
					$connection->exec(Schema::dropTable($table));

				} else {
					foreach ($columns as $column => $data) {

						if (!isset($saved[$table][$column])) {

							// Drop Column
							$connection->exec(Schema::dropColumn($table, $data));

						} else {

							if (!empty($data['Key']) && empty($saved[$table][$column]['Key'])) {

								// Drop Index
								$connection->exec(Schema::dropIndex($table, $data));
								
							}

						}
					}					
				}
			}

			// Make all changes
			$connection->commit();
		}
		break;
}



echo "\n";

class Schema
{

	/* Tables */

	public static function createTable($table, array $columns)
	{
		$string = "CREATE TABLE {$table} (";

		$stack = [];

		foreach ($columns as $column) {
			$stack[] = self::column($column);
			if ($column['Key'] == 'PRI') {
				$stack[] = " PRIMARY KEY ({$column['Field']})";
			}			
		}

		$string .= implode(',', $stack);

		$string .= ")";

		return $string;
	}

	public static function dropTable($table)
	{
		return "DROP TABLE `{$table}`";
	}

	/* Columns */

	public static function createColumn($table, array $data)
	{
		return "ALTER TABLE `{$table}` ADD " . self::column($data);
	}

	public static function dropColumn($table, array $data)
	{
		return "ALTER TABLE `{$table}` DROP `{$data['Field']}`";
	}

	public static function alterColumn($table, array $data)
	{
		return "ALTER TABLE `{$table}` CHANGE `{$data['Field']}` " . self::column($data);
	}

	private static function column(array $data)
	{
		$string = " `{$data['Field']}` {$data['Type']}";

		if ($data['Null'] == 'NO') {
			$string .= ' NOT NULL';
		}

		if (!empty($data['Default'])) {
			$string .= " DEFAULT '{$data['Default']}'";
		}

		if (!empty($data['Extra'])) {
			$string .= " {$data['Extra']}";
		}

		return $string;		
	}	

	/* Indexes */

	public static function createIndex($table, array $data)
	{
		$string = '';

		if (!empty($data['Key'])) {
			$string .= " ALTER TABLE `{$table}` ADD ";

			switch ($data['Key']) {
				case 'UNI':
					$string .= 'UNIQUE';
					break;
				
				case 'PRI':
					$string .= 'PRIMARY';
					break;

				case 'MUL':
					$string .= 'INDEX';
					break;

				default:
					return '';
					break;
			}

			$string .= "(`{$data['Key']}`)";
		}

		return (!empty($string) ? $string : false);
	}

	public static function dropIndex($table, array $data)
	{
		return "ALTER TABLE `{$table}` DROP INDEX {$data['Field']};";
	}
}