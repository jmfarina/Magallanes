<?php

/*
 * This file is part of the Magallanes package.
 *
 * (c) Andrés Montañez <andres@andresmontanez.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace Mage\Task\BuiltIn\NodeJs;

use Mage\Task\AbstractTask;

/**
 * Task to execute Bower commands.
 * Accepts the following params:
 * <ol>
 * <li><em>bower_cmd</em> (optative): the command used to execute bower. Defined either in general config or as a task param. Defaults to 'bower'.</li>
 * <li><em>from</em> (optative): the path to use as working directory to execute bower. Defined as a task param. Defaults to project's root.</li>
 * </ol>
 */
class BowerTask extends AbstractTask {
	protected function getBowerCommand() {
		// reads bower command from config: if the param is not specified as a param to the task, it defaults to
		// general config's, and if it's not defined there either, it defaults to 'bower'
		$bowerCmd = $this->getParameter ( 'bower_cmd', $this->getConfig ()->general ( 'bower_cmd', 'bower' ) );
		return $bowerCmd;
	}
	
	protected function getWorkingDirectory() {
		// base path from where to execute bower's command
		$from = $this->getParameter ( 'from', $this->getConfig ()->deployment ( 'from', './' ) );
		return $from;
	}
	
	/*
	 * (non-PHPdoc)
	 * @see \Mage\Task\AbstractTask::run()
	 */
	public function run() {
		$command = 'cd ' . $this->getWorkingDirectory() . '; ' . $this->getBowerCommand () . ' install';
		return $this->runCommand($command);
	}
	
	
	/* (non-PHPdoc)
	 * @see \Mage\Task\AbstractTask::getName()
	 */
	public function getName() {
		return 'Bower';
	}
}

