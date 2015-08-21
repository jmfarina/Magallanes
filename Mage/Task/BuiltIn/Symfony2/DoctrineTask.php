<?php

namespace Mage\Task\BuiltIn\Symfony2;

use Mage\Task\BuiltIn\Symfony2\SymfonyAbstractTask;

/**
 * Task to execute Doctrine commands
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