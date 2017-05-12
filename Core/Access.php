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
use Symfony\Component\Security\Core\Authorization\AuthorizationChecker;

use FQT\DBCoreManagerBundle\Core\Action;

use Symfony\Component\Config\Definition\Exception\Exception;
use FQT\DBCoreManagerBundle\Exception\NotFoundException;
use FQT\DBCoreManagerBundle\Exception\NotAllowedException;

class Access
{
    /**
     * @var Container
     */
    private $container;
    /**
     * @var AuthorizationChecker
     */
    private $context;


    /**
     * @var Action
     */
    public $action;
    /**
     * @var null|string
     */
    public $check = null;
    /**
     * @var null|array
     */
    public $roles = null;


    /**
     * @var null|bool
     */
    private $rolesAccess = null;
    /**
     * @var null|bool
     */
    private $checkAccess = null;

    public function __construct(Action $action, Container $container, array $data = null)
    {
        $this->container = $container;
        $this->context = $container->get('security.authorization_checker');
        $this->action = $action;
        if ($data != null) {
            $this->check = $data['check'];
            $this->roles = $data['roles'];
        }
    }

    public function compute() {
        return $this->isRolesAuthorize() && $this->computeWithCheck();
    }

    private function computeWithRoles() {
        if ($this->roles == null)
            return $this->rolesAccess = true;
        return $this->rolesAccess = $this->isGranted($this->roles);
    }

    public function computeWithCheck() {
        if ($this->check == null)
            return true;
        if ($this->action->isDefault)
            throw new Exception('Impossible to define a custom check for default method.');
        $mName = $this->check;
        $isAuthorize = $this->container->get($this->action->serviceName)->$mName($this->action->object);
        if (is_bool($isAuthorize) === false)
            throw new Exception('Check method of access details must return a boolean, ' . gettype($isAuthorize) . ' given.');
        $this->checkAccess = $isAuthorize;
        return $isAuthorize;
    }

    public function injectContainer(Container $container) {
        $this->container = $container;
        $this->context = $container->get('security.authorization_checker');
    }

    private function isGranted(array $roles) {
        foreach ($roles as $role) {
            if ($this->context->isGranted($role))
                return true;
        }
        return false;
    }

    public function isRolesAuthorize(bool $force = false) {
        if ($this->rolesAccess == null || $force)
            return $this->computeWithRoles();
        return $this->rolesAccess;
    }

    public function isCheckAuthorize(bool $force = false) {
        if ($this->checkAccess == null || $force)
            return $this->computeWithCheck();
        return $this->checkAccess;
    }

    public function isAuthorize(bool $force = false) {
        return $this->isCheckAuthorize($force) && $this->isRolesAuthorize($force);
    }
}