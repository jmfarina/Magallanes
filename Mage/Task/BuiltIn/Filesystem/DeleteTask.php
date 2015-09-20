<?php
namespace Mage\Task\BuiltIn\Filesystem;

use Mage\Task\AbstractTask;
use Mage\Task\SkipException;

/**
 * Task for deleting specified paths. Change will be done on local or
 * remote host depending on the stage of the deployment.
 *
 * Usage :
 *   pre-deploy:
 *     - filesystem/delete: {paths: /var/www/myapp/app/cache:/var/www/myapp/app/cache, recursive: false, checkPathsExist: true, owner: www-data, group: www-data, rights: 775}
 *     - filesystem/delete:
 *         paths:
 *             - /var/www/myapp/app/cache
 *             - /var/www/myapp/app/logs
 *         recursive: false
 *         force: false
 *         checkPathsExist: true
 *   on-deploy:
 *     - filesystem/delete: {paths: app/cache:app/logs, recursive: false, force: false, checkPathsExist: true}
 */
//based on Huet's PermissionsTask
class DeleteTask extends AbstractTask
{
    /**
     * Paths to change of permissions in an array or a string separated by
     * PATH_SEPARATOR.
     *
     * If the stage is on local host you should give full paths. If on remote
     * you may give full or relative to the current release directory paths.
     *
     * @var string
     */
    private $paths;

    /**
     * If set to true, will check existance of given paths on the host and
     * throw SkipException if at least one does not exist.
     *
     * @var boolean
     */
    private $checkPathsExist = true;

    /**
     * If set to true, will recursively change permissions on given paths. Default is false.
     *
     * @var string
     */
    private $recursive = false;

    /**
     * If set to true, will enable the force parameter for the delete command. Default is false.
     *
     * @var string
     */
    private $force = false;

    /**
     * Initialize parameters.
     *
     * @throws SkipException
     */
    public function init()
    {
        parent::init();

        if (! is_null($this->getParameter('checkPathsExist'))) {
            $this->setCheckPathsExist($this->getParameter('checkPathsExist'));
        }

        if (! $this->getParameter('paths')) {
            throw new SkipException('Param paths is mandatory');
        }
        $this->setPaths(is_array($this->getParameter('paths')) ? $this->getParameter('paths') : explode(PATH_SEPARATOR, $this->getParameter('paths', '')));

        if (! is_null($recursive = $this->getParameter('recursive'))) {
            $this->setRecursive($recursive);
        }
        
        if (! is_null($force = $this->getParameter('force'))) {
            $this->setForce($force);
        }
    }

    /**
     * @return string
     */
    public function getName()
    {
        return "Delete given paths";
    }

    /**
     * @return boolean
     */
    public function run()
    {
    	$command = 'rm '. $this->getOptionsForCmd() . ' ' . $this->getPathsForCmd();
        $result = $this->runCommand($command);

        return $result;
    }

    /**
     * Returns the options for the commands to run. Only supports -R for now.
     *
     * @return string
     */
    protected function getOptionsForCmd()
    {
        $optionsForCmd = '';
        $options = array(
            'R' => $this->recursive,
        	'f' => $this->force
        );

        foreach ($options as $option => $apply) {
            if ($apply === true) {
                $optionsForCmd .= $option;
            }
        }

        return $optionsForCmd ? '-' . $optionsForCmd : '';
    }

    /**
     * Transforms paths array to a string separated by 1 space in order to use
     * it in a command line.
     *
     * @return string
     */
    protected function getPathsForCmd($paths = null)
    {
        if (is_null($paths)) {
            $paths = $this->paths;
        }

        return implode(' ', $paths);
    }

    /**
     * Set paths. Will check if they exist on the host depending on
     * checkPathsExist flag.
     *
     * @param array $paths
     * @return PermissionsTask
     * @throws SkipException
     */
    protected function setPaths(array $paths)
    {
        if ($this->checkPathsExist === true) {
            $commands = array();
            foreach ($paths as $path) {
                $commands[] = '(([ -f ' . $path . ' ]) || ([ -d ' . $path . ' ]))';
            }

            $command = implode(' && ', $commands);
            if (! $this->runCommand($command)) {
                throw new SkipException('Make sure all paths given exist on the host : ' . $this->getPathsForCmd($paths));
            }
        }

        $this->paths = $paths;

        return $this;
    }

    /**
     * @return string
     */
    protected function getPaths()
    {
        return $this->paths;
    }

    /**
     * Set checkPathsExist.
     *
     * @param boolean $checkPathsExist
     * @return PermissionsTask
     */
    protected function setCheckPathsExist($checkPathsExist)
    {
        $this->checkPathsExist = (bool) $checkPathsExist;

        return $this;
    }

    /**
     * @return boolean
     */
    protected function getCheckPathsExist()
    {
        return $this->checkPathsExist;
    }

    /**
     * Set recursive.
     *
     * @param boolean $recursive
     * @return PermissionsTask
     */
    protected function setRecursive($recursive)
    {
        $this->recursive = (bool) $recursive;

        return $this;
    }

    /**
     * @return boolean
     */
    protected function getRecursive()
    {
        return $this->recursive;
    }
    
    /**
     * Set force.
     *
     * @param boolean $force
     * @return PermissionsTask
     */
    protected function setForce($force)
    {
        $this->force = (bool) $force;

        return $this;
    }

    /**
     * @return boolean
     */
    protected function getForce()
    {
        return $this->force;
    }
}