<?php

namespace Mage\Task\BuiltIn\Symfony2;

use Mage\Task\BuiltIn\Symfony2\SymfonyAbstractTask;
use Mage\Task\SkipException;

/**
 * Task to execute arbitrary Symfony commands. Commands can be executed the following way:
 * on-deploy:
 * - symfony2/symfony-generic: {command: 'execute:My:Task'}
 * 
 */
class SymfonyGenericTask extends SymfonyAbstractTask
{
	/**
	* (non-PHPdoc)
	* @see \Mage\Task\AbstractTask::getName()
	*/
	public function getName()
	{
		return 'Symfony v2 - Generic Task';
	}


	/**
	 * Initialize parameters.
	 *
	 * @throws SkipException
	 */
	public function init()
	{
		parent::init();

		if (! $this->getParameter('command')) {
			throw new SkipException('Param command is mandatory');
		}
	}
	
	/**
	*
	* @see \Mage\Task\AbstractTask::run()
	*/
	public function run()
	{
		$command = $this->getParameter('command');
		return $this->runCommand($this->getAppPath() . ' ' . $command);
	}
}