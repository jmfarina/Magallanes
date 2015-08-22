<?php

namespace Mage\Task\BuiltIn\Symfony2;

use Mage\Task\BuiltIn\Symfony2\SymfonyAbstractTask;

/**
 * Task to execute Doctrine commands.
 * 
 * Accepts the following params, each corresponding to a command to be executed, and they are executed respecting this order:
 * <ol>
 * <li><em>drop</em> (optative): <code>true</code>/<code>false</code> (defaults to <code>false</code>). Whether the database should be dropped. If it fails, it will continue with the next command (if any).</li>
 * <li><em>create</em> (optative): <code>true</code>/<code>false</code> (defaults to <code>false</code>). Whether the database should be created. On fail, it aborts the task.</li>
 * <li><em>update</em> (optative): <code>true</code>/<code>false</code> (defaults to <code>false</code>). Whether the schema should be updated. On fail, it aborts the task.</li>
 * <li><em>loadFixtures</em> (optative): <code>true</code>/<code>false</code> (defaults to <code>false</code>). Whether the fixtures should be loaded <em><strong>WARNING: PURGES EXISTING DATA</strong></em>. On fail, it aborts the task.</li>
 * </ol>
 */
class DoctrineTask extends SymfonyAbstractTask
{
	/**
	* (non-PHPdoc)
	* @see \Mage\Task\AbstractTask::getName()
	*/
	public function getName()
	{
		return 'Symfony v2 - Doctrine Tasks';
	}

	/**
	* Migrates Doctrine entities
	*
	* @see \Mage\Task\AbstractTask::run()
	*/
	public function run()
	{
		$env = $this->getParameter('env', 'dev');
		
		
		//commands['paramKey' => ['paramDefaultValue', 'command', 'continueOnFail']
		$commands = array(
			'drop' => array(false, $this->getAppPath() . ' doctrine:database:drop --force', true),
			'create' => array(false, $this->getAppPath() . ' doctrine:database:create', false),
			'update' => array(false, $this->getAppPath() . ' doctrine:schema:update --force', false),
			'loadFixtures' => array(false, $this->getAppPath() . ' doctrine:fixture:load -n', false)//purges database, no confirmation required
		);
		
		$goOn = true;
		foreach ($commands as $key => $commandInfo) {
			$execute = $this->getParameter($key, $commandInfo[0]);
			if(!$goOn) {
				break;
			} else if($execute) {
				$command = $commandInfo[1];
				$goOn = $this->runCommand($command);
			}
		}
		
		return $goOn;
	}
}