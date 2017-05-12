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

use Symfony\Component\DependencyInjection\ContainerInterface as Container;

use FQT\DBCoreManagerBundle\Core\Access;

class Action
{

    /**
     * @var null|string
     */
    public $method = null;
    /**
     * @var string
     */
    public $environment;
    /**
     * @var bool
     */
    public $isDefault;

    /**
     * @var string
     */
    public $id;
    /**
     * @var null|string
     */
    public $serviceName = null;
    /**
     * @var string
     */
    public $fullName;

    /**
     * @var object
     */
    public $object;

    /**
     * @var Access
     */
    public $access;

    public function __construct(array $data, Container $container)
    {
        $this->id = $data['id'];
        $this->isDefault = $data['isDefault'];
        $this->environment = $data['environment'];
        $this->fullName = $data['fullName'];

        if (key_exists("method", $data))
            $this->method = $data['method'];

        if (key_exists("service", $data))
            $this->serviceName = $data['service'];

        $this->access = new Access($this, $container, $data['access']);
    }

    public function computePermissions() {
        return $this->access->compute();
    }

    public function isAuthorize(bool $force = false) {
        return $this->access->isRolesAuthorize($force);
    }

    public function isCheckAuthorize(bool $force = false) {
        return $this->access->isCheckAuthorize($force);
    }

    public function isFullAuthorize(bool $force = false) {
        return $this->access->isAuthorize($force);
    }
}
