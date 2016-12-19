<?php

namespace Mage\Task\BuiltIn\Symfony2;

use Mage\Console;
use Mage\Task\BuiltIn\Symfony2\SymfonyAbstractTask;
use Mage\Task\SkipException;
use Mage\Yaml\Yaml;

/**
 * Task to execute SQL delta files on deploy.
 *
 * Once a delta file is executed successfully, it will not be executed again: the last successfully executed delta will be registered with its relative path on .mage/environment/LastDelta/{environment} (where {environment} is the name of the environment being executed. And in this file the path of the last delta must be a relative path from the root of the project.
 * example: src/sqldeltas/deploy/sprint2/001-1028-someDBchanges.sql
 * The next deploy will skip all deltas up to this one included, and execute the remaining.
 * If the file with the last executed delta is not found, task will be skipped.
 * If file is empty or no delta was matched to the one on file, all deltas will be executed assuming non was executed prior to this deploy.
 * Example of task symfony2/delta configuration in environment configuration file:
 * - symfony2/delta: {delta-root: src/sqldeltas/deploy, stop-on-error: true, placeholders: {schema_1: 'myschema', otherparam: 'parameterValue'} }
 *
 * Accepts the following params:
 * <ol>
 * <li><em>delta-root</em> (mandatory): <code>{path}</code>. Path to the root directory where all delt .sql files should be searched recursively.</li>
 * <li><em>stop-on-error</em> (optative): <code>true</code>/<code>false</code> (defaults to <code>true</code>). Whether the execution of deltas should stop on fail or continue. On fail, it aborts the task.</li>
 * <li><em>placeholders</em> (optative): <code>[key => value]</code> (defaults to <code>[]</code>). For all deltas to execute, before execution, replace all keys from array with their respective values. Defaults to empty array for no replacements.</li>
 * </ol>
 */
class DeltaTask extends SymfonyAbstractTask {
	/**
	 * (non-PHPdoc)
	 *
	 * @see \Mage\Task\AbstractTask::getName()
	 */
	public function getName() {
		return 'Delta Executions';
	}
	
	/**
	 * Executes all deltas not executed before
	 *
	 * @see \Mage\Task\AbstractTask::run()
	 */
	public function run() {
		
		// Get full path of last executed delta or null if none
		$deployOriginRoot = getcwd () . DIRECTORY_SEPARATOR . $this->config->deployment ( "from" );
		$lastDeltaFile = $deployOriginRoot . DIRECTORY_SEPARATOR . ".mage" . DIRECTORY_SEPARATOR . "config" . DIRECTORY_SEPARATOR . "environment" . DIRECTORY_SEPARATOR . "LastDelta" . DIRECTORY_SEPARATOR . $this->getConfig ()->getEnvironment ();
		
		if (! file_exists ( $lastDeltaFile ))
			throw new SkipException ( 'Error fetching path to last delta file. The file most exist. (at least empty). Path: ' . $lastDeltaFile );
			
			// MySQL with PDO_MYSQL
		$parametersYMLFile = $deployOriginRoot . DIRECTORY_SEPARATOR . "app" . DIRECTORY_SEPARATOR . "config" . DIRECTORY_SEPARATOR . "parameters.yml";
		$parametersYML = Yaml::parse ( $parametersYMLFile );
		$mysql_host = $parametersYML ['parameters'] ["database_host"];
		$mysql_user = $parametersYML ['parameters'] ["database_user"];
		$mysql_password = $parametersYML ['parameters'] ["database_password"];
		$db = new \PDO ( "mysql:host=$mysql_host;", $mysql_user, $mysql_password );
		
		// Get path to root folder with delta's
		$deltaRootPath = $this->getParameter ( 'delta-root' );
		if (! $deltaRootPath || ! is_dir ( $deltaRootPath ))
			throw new SkipException ( 'Param deltaRootPath is mandatory and most exist.' );
		
		$lastDelta = file_get_contents ( $lastDeltaFile );
		
		// Iterate recursively over all files on delta's folder
		$deltas = preg_grep ( '/^.*\.sql/', $this->getDirContents ( $deltaRootPath ) );
		
		$exec = false;
		$noErrors = true;
		$stopOnError = $this->getParameter ( 'stop-on-error', true );
		foreach ( $deltas as $delta ) {
			// Check for Errors
			if (! $noErrors && $stopOnError)
				break;
			
			$nameMatch = strcmp($delta, $lastDelta) == 0;
			$logDelta = 'Checking delta ' . $delta . ' against last delta (' . $lastDelta . '): ' . $nameMatch;
			Console::log($logDelta);
				
				// Execute or skip
			if ($exec) {
				if (! $this->executeSQL ( $delta, $db, $lastDeltaFile ))
					$noErrors = false;
			} else if (strcmp ( $delta, $lastDelta ) == 0) {
				$exec = true;
			}
		}
		if (! $exec)
			foreach ( $deltas as $delta ) {
				// Check for Errors
				if (! $noErrors && $stopOnError)
					break;
				
				if (! $this->executeSQL ( $delta, $db, $lastDeltaFile ))
					$noErrors = false;
			}
		
		return $noErrors;
	}
	
	/**
	 * Returns all files found recursively inside a given directory
	 *
	 * @param
	 *        	$dir
	 * @param array $results        	
	 * @return array
	 */
	protected function getDirContents($dir, &$results = array()) {
		$files = scandir ( $dir );
		
		foreach ( $files as $key => $value ) {
			$path = $dir . DIRECTORY_SEPARATOR . $value;
			if (! is_dir ( $path )) {
				$results [] = $path;
			} else if ($value != "." && $value != "..") {
				// TODO detect loops
				$this->getDirContents ( $path, $results );
				$results [] = $path;
			}
		}
		return $results;
	}
	
	/**
	 * Replaces all params indexes with their values inside a string(SQL).
	 *
	 * @param $sql the
	 *        	string containing the SQL code, with the placeholders to be replaced
	 * @param $params an
	 *        	array of $key => $value where $key is the placeholder string to be replaced within the provided SQL string, and $value, the value to replace it with
	 * @return mixed
	 */
	protected function parsePlaceHolders($sql, $params) {
		foreach ( $params as $placeholder => $value )
			$sql = str_replace ( $placeholder, $value, $sql );
		
		return $sql;
	}
	
	/**
	 * Execute delta.
	 * Ignores empty files.
	 *
	 * @param string $delta
	 *        	Path to SQL file to be executed
	 * @param $db The
	 *        	database object
	 * @param string $lastDeltaFile
	 *        	Path to file to save delta path on success
	 * @return bool True if SQL execution returned no errors.
	 */
	protected function executeSQL($delta, $db, $lastDeltaFile) {
		Console::log ( 'Executing delta: ' . $delta );
		
		// Get content
		$sql = file_get_contents ( $delta );
		
		// Parse any placeholders
		$sql = $this->parsePlaceHolders ( $sql, $this->getParameter ( 'placeholders', array () ) );
		
		// Checking that the sql file isn't empty
		$sql = trim ( $sql );
		if ($sql == "") {
			Console::log ( "---------------------------------" );
			Console::log ( 'Warning: Ignoring empty file: ' . $delta );
			Console::log ( "---------------------------------" );
		} else {
			// Execute Script From PHP
			$stmt = $db->prepare ( $sql );
			
			if ($stmt->execute ()) {
				// Save last delta path to file system
				file_put_contents ( $lastDeltaFile, $delta );
			} else {
				$errorInfo = $stmt->errorInfo ();
				Console::log ( "---------------------------------" );
				Console::log ( 'Error executing delta: ' . $delta );
				Console::log ( "---------------------------------" );
				Console::log ( "SQL error code " . $errorInfo[0] . " ::: Driver error code " . $errorInfo[1] . " ::: " . $errorInfo[2] );
				Console::log ( "---------------------------------" );
				return false;
			}
		}
		
		return true;
	}
}
