<?php

namespace Mage\Task\BuiltIn\Symfony2;

use Mage\Config\ConfigNotFoundException;
use Mage\Console;
use Mage\Task\BuiltIn\Symfony2\SymfonyAbstractTask;
use Mage\Task\SkipException;
use Mage\Yaml\Yaml;

/**
 * Task to execute Delta files on deploy.
 *
 * Once a delta is executed, it will not be executed again. Last delta executed will
 * be registered with its relative path on .mage/environmentes/LastDelta/{environment}.
 * The next deploy will skip all deltas up to this one included, and execute the remaining.
 * If the file with the last executed delta is not found, task will be skipped.
 * If file is empty or no delta
 * was matched to the one on file, all deltas will be executed assuming non was prior to this deploy.
 *
 * Accepts the following params:
 * <ol>
 * <li><em>delta-root</em> (mandatory): <code>{path}</code>. Path to the root directory where all delt .sql files should be searched recursively.</li>
 * <li><em>stop-on-error</em> (optative): <code>true</code>/<code>false</code> (defaults to <code>true</code>). Whether the execution of deltas should stop on fail or continue. On fail, it aborts the task.</li>
 * <li><em>placeholders</em> (optative): <code>[key => value]</code> (defaults to <code>[]</code>). For all deltas to execute, before execution, replace all key's from array with their respective value's. Defaults to empty array for no replacements.</li>
 * </ol>
 */
class DeltaTask extends SymfonyAbstractTask
{
	/**
	* (non-PHPdoc)
	* @see \Mage\Task\AbstractTask::getName()
	*/
	public function getName()
	{
		return 'Delta Executions';
	}

	/**
	* Executes all deltas not executed.
	*
	* @see \Mage\Task\AbstractTask::run()
	*/
	public function run()
	{

        //Get full path of last executed delta or null if none
        $deployOriginRoot = getcwd() . DIRECTORY_SEPARATOR . $this->config->deployment("from");
        $lastDeltaFile =  $deployOriginRoot . DIRECTORY_SEPARATOR . ".mage" . DIRECTORY_SEPARATOR . "config" .
            DIRECTORY_SEPARATOR . "environment" . DIRECTORY_SEPARATOR . "LastDelta" . DIRECTORY_SEPARATOR .
            $this->getConfig()->getEnvironment();

        if ( ! file_exists($lastDeltaFile) )
            throw new SkipException('Error fetching path to last delta file. The file most exist. (at least empty). Path: ' . $lastDeltaFile);


        //MySQL with PDO_MYSQL
        $parametersYMLFile = $deployOriginRoot . DIRECTORY_SEPARATOR . "app" . DIRECTORY_SEPARATOR . "config" . DIRECTORY_SEPARATOR . "parameters.yml";
        $parametersYML = Yaml::parse($parametersYMLFile);
        $mysql_host = $parametersYML['parameters']["database_host"];
        $mysql_user = $parametersYML['parameters']["database_user"];
        $mysql_password = $parametersYML['parameters']["database_password"];
        $db = new \PDO("mysql:host=$mysql_host;", $mysql_user, $mysql_password);

	    //Get path to root folder with delta's
		$deltaRootPath = $this->getParameter('delta-root');
        if (!$deltaRootPath || !is_dir($deltaRootPath))
            throw new SkipException('Param deltaRootPath is mandatory and most exist.');

        $lastDelta = file_get_contents($lastDeltaFile);

        //Iterate recursivly over all files on delta's folder
        $deltas = preg_grep( '/^.*\.sql/', $this->getDirContents($deltaRootPath) );

		$exec = false;
        $noErrors = true;
        $stopOnError = $this->getParameter('stop-on-error', true);
		foreach ($deltas as $delta) {
		    //Check for Errors
		    if (!$noErrors && $stopOnError ) break;

            //Execute or skip
            if ($exec)
            {
                if( ! $this->executeSQL($delta, $db, $lastDeltaFile) )
                    $noErrors = false;
            } else if ( strcmp($delta, $lastDelta) == 0) {
                $exec = true;
            }


        }
        if (!$exec)
            foreach ($deltas as $delta) {
                //Check for Errors
                if (!$noErrors && $stopOnError ) break;

                if (!$this->executeSQL($delta, $db, $lastDeltaFile))
                    $noErrors = false;
            }

		return $noErrors;
	}

    /**
     * Returns all files found recursivly inside a given directory
     *
     * @param $dir
     * @param array $results
     * @return array
     */
    protected function getDirContents($dir, &$results = array()){

        $files = scandir($dir);

        foreach($files as $key => $value){
            $path = $dir . DIRECTORY_SEPARATOR . $value;
            if(!is_dir($path)) {
                $results[] = $path;
            } else if($value != "." && $value != "..") {
                $this->getDirContents($path, $results);
                $results[] = $path;
            }
        }
        return $results;
    }

    /**
     * Replaces all params indexes with ther values inside a string(sql).
     *
     * @param $sql
     * @param $params
     * @return mixed
     */
	protected function parsePlaceHolders($sql, $params) {
        foreach ($params as $placeholder => $value)
            $sql = str_replace($placeholder, $value, $sql);

        return $sql;
    }

    /**
     * Execute delta
     *
     * @param string $delta Sql to execute
     * @param $db
     * @param string $lastDeltaFile Path to file to save delta path on success
     * @return bool True if sql returned no errors.
     */
    protected function executeSQL($delta, $db, $lastDeltaFile) {

        Console::log('Executing delta: ' . $delta);

        //Get content
        $sql = file_get_contents($delta);

        //Parse any placeholders
        $sql = $this->parsePlaceHolders($sql, $this->getParameter('placeholders', array()));

        //Execute Script From PHP
        $stmt = $db->prepare($sql);

        if ($stmt->execute()) {
            //Save last delta path to file system
            file_put_contents($lastDeltaFile,$delta);
        } else {
            Console::log("---------------------------------");
            Console::log('Error executing delta: ' . $delta);
            Console::log("---------------------------------");
            Console::log("SQL error code " . $stmt->errorInfo()[0] . " ::: Driver error code " . $stmt->errorInfo()[1] . " ::: " . $stmt->errorInfo()[2]);
            Console::log("---------------------------------");
            return false;
        }

        return true;
    }

}
