<?php

/*
 * This file is part of the FQTDBCoreManagerBundle package.
 *
 * (c) FOUQUET <https://github.com/hugo082/DBManagerBundle>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * @author Hugo Fouquet <hugo.fouquet@epita.fr>
 */

namespace FQT\DBCoreManagerBundle\Event;

use Symfony\Component\EventDispatcher\Event;

class ActionEvent extends Event
{
    /**
     * @var array
     */
    private $eInfo = NULL;

    /**
     * @var mixed
     */
    private $obj = NULL;

    /**
     * @var bool
     */
    private $executed = false;

    /**
     * @var array
     */
    private $flash = array('title', 'message');

    public function __construct(array $eInfo, $obj, array $flash = NULL)
    {
        $this->eInfo = $eInfo;
        $this->obj = $obj;
        if ($flash != NULL)
            $this->flash = $flash;
    }

    public function setEntityInfo(array $eInfo)
    {
        $this->eInfo = $eInfo;
    }

    public function getEntityInfo()
    {
        return $this->eInfo;
    }

    public function setEntityObject($obj)
    {
        $this->obj = $obj;
    }

    public function getEntityObject()
    {
        return $this->obj;
    }

    /**
     * Set the process execution bool
     * @param $bool
     */
    public function setExecuted($bool) {
        $this->executed = $bool;
    }

    /**
     * Indicate if process have been executed.
     * @return bool
     */
    public function isExecuted() {
        return $this->executed;
    }

    /**
     * Set the flash title and message
     * @param string $title
     * @param string $message
     */
    public function setFlash(string $title, string $message) {
        $this->flash = array($title, $message);
    }

    /**
     * Get the flash title
     * @return string
     */
    public function getFlashTitle() {
        return $this->flash[0];
    }

    /**
     * Get the flash message
     * @return string
     */
    public function getFlashMessage() {
        return $this->flash[1];
    }
}