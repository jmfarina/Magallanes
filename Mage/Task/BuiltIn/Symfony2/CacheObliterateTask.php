<?php

/*
 * This file is part of the Magallanes package.
 *
 * (c) Andrés Montañez <andres@andresmontanez.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace Mage\Task\BuiltIn\Symfony2;

use Mage\Task\BuiltIn\Symfony2\SymfonyAbstractTask;
use Mage\Task\BuiltIn\Filesystem\DeleteTask;

/**
 * Task for Clearing the Cache
 *
 * Example of usage:
 * symfony2/cache-clear: { env: dev }
 * symfony2/cache-clear: { env: dev, optional: --no-warmup }
 *
 * @author Andrés Montañez <andres@andresmontanez.com>
 * @author Samuel Chiriluta <samuel4x4@gmail.com>
 */
class CacheObliterateTask extends SymfonyAbstractTask {
	private $deleteTask;
	
	/**
	 * (non-PHPdoc)
	 *
	 * @see \Mage\Task\AbstractTask::getName()
	 */
	public function getName() {
		return 'Symfony v2 - Cache Obliterate [built-in]';
	}
	public function init() {
		// avoiding deleting directories not strictly within app/cache by specifying an env parameter with a path
		$env = basename ( $this->getParameter ( 'env', 'dev' ) );
		
		$deleteConfig = clone $this->config;
		$deleteConfig->addParameter ( "recursive", true );
		$deleteConfig->addParameter ( "force", true );
		$deleteConfig->addParameter ( "checkPathsExist", true );
		
		$deleteConfig->addParameter ( "paths", array (
				"app/cache/" . $env
		) );
		
		$this->deleteTask = new DeleteTask ( $deleteConfig, $this->inRollback, $this->stage, $this->parameters );
		$this->deleteTask->init ();
	}
	
	/**
	 * Clears the Cache
	 *
	 * @see \Mage\Task\AbstractTask::run()
	 */
	public function run() {
		$result = $this->deleteTask->run ();
		
		return $result;
	}
}
