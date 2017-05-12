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

namespace FQT\DBCoreManagerBundle\Exception;

use FQT\DBCoreManagerBundle\Core\EntityInfo;

class NotAllowedException extends \Exception implements ExceptionInterface
{
    private $statusCode = 323;
    private $headers;
    private $title;

    private $devTitle = NULL;
    private $devMessage = NULL;

    /**
     * @var EntityInfo
     */
    private $eInfo;

    public function __construct(EntityInfo $eInfo)
    {
        $this->eInfo = $eInfo;
        $this->headers = array();
        $this->title = "Action not allowed";
        $message = "You can't execute this action on " . $eInfo->name;

        $this->devTitle = "Impossible to execute this action on " . $eInfo->fullName;
        $this->devMessage = "The roles of the current user may not be 
        sufficient or that the action is not allowed on this entity. <br>
        For more information, look the dumps above.<br>
        If all boolean on dumps are incoherent with this exception, check the result of actionMethod.";

        parent::__construct($message, 0, null);
    }

    public function getDevMessage()
    {
        if ($this->devMessage != NULL) {
            //var_dump($this->eInfo->access);
            //var_dump($this->eInfo->actions);
            return $this->devMessage;
        }
        return $this->message;
    }

    public function getStatusCode(string $env)
    {
        return $this->statusCode;
    }

    public function getHeaders(string $env)
    {
        return $this->headers;
    }

    public function getTitle(string $env)
    {
        if ($env != 'prod' and $this->devTitle != NULL)
            return $this->devTitle;
        return $this->title;
    }
}
