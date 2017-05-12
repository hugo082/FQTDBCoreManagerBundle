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

namespace FQT\DBCoreManagerBundle\Core;

use FQT\DBCoreManagerBundle\Core\EntityInfo;
use FQT\DBCoreManagerBundle\Core\Action;


class Execution
{
    /**
     * @var EntityInfo
     */
    public $entityInfo;

    /**
     * @var Action
     */
    public $action;

    /**
     * @var Data
     */
    public $data;

    public function __construct(EntityInfo $entityInfo, Action $action, Data $data)
    {
        $this->entityInfo = $entityInfo;
        $this->action = $action;
        $this->data = $data;
    }
}