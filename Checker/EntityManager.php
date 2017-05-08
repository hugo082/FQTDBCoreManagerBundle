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

namespace FQT\DBCoreManagerBundle\Checker;

use Doctrine\ORM\EntityManager as ORMManager;
use Symfony\Component\Config\Definition\Exception\Exception;
use Symfony\Component\DependencyInjection\ContainerInterface as Container;
use Symfony\Component\Security\Core\Authorization\AuthorizationChecker;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage;
use FQT\DBCoreManagerBundle\DependencyInjection\Configuration as Conf;

use FQT\DBCoreManagerBundle\Exception\NotFoundException;
use FQT\DBCoreManagerBundle\Exception\NotAllowedException;

class EntityManager
{
    /**
     * @var AuthorizationChecker
     */
    private $context;
    /**
     * @var TokenStorage
     */
    private $token;
    /**
     * @var ORMManager
     */
    private $em;
    /**
     * @var Container
     */
    private $container;
    /**
     * @var array
     */
    private $settings;
    /**
     * @var array
     */
    private $entities;

    public function __construct(Container $container, array $settings, array $entities)
    {
        $this->em = $container->get('doctrine.orm.entity_manager');
        $this->context = $container->get('security.authorization_checker');
        $this->token = $container->get('security.token_storage');
        $this->container = $container;

        $this->settings = $settings;
        $this->entities = $entities;
    }

    public function getEntity(string $name, string $action) {
        if (!isset($this->entities[$name]))
            throw new NotFoundException($name);

        $eInfo = $this->entities[$name];
        if (!(key_exists($action, $eInfo['permissions']) and $eInfo['permissions'][$action]))
            throw new NotAllowedException($eInfo);
        if (!$this->entityAccess($eInfo, $action))
            throw new NotAllowedException($eInfo);

        return $eInfo;
    }

    /**
     * Return all entities where current user can execute action or access information
     * @return array
     */
    public function getEntities() {
        $tmp = $this->entities;
        foreach ($tmp as $key => $e) {
            if (!$this->entityAccess($e))
                unset($tmp[$key]);
        }
        return $tmp;
    }

    /**
     * Return entity or entities and check his permissions with action
     * @param array $eInfo
     * @param string|NULL $action
     * @param int|NULL $id
     * @return array|null|object
     * @throws NotAllowedException
     */
    public function getEntityObject(array $eInfo, string $action = NULL, int $id = NULL) {
        $repo = $this->em->getRepository($eInfo['bundle'].':'.$eInfo['name']);
        if ($id) {
            $obj = $repo->find($id);
            $this->checkObjectPermission($eInfo, $obj, $action);
            return $obj;
        }
        elseif ($eInfo['listingMethod'] != NULL) {
            $name = $eInfo['listingMethod'];
            $all = $repo->$name($this->getUser());
        } else
            $all = $repo->findAll();
        $res = array();
        foreach ($all as $e) {
            if (($a = $this->setObjectPermissions($eInfo, $e))['permissions'][Conf::PERM_LIST])
                $res[] = $a;
        }
        return $res;
    }


    /**
     * Check if current user can execute action on object
     * @param array $eInfo
     * @param $obj
     * @param string $action
     * @return bool
     * @throws NotAllowedException
     */
    public function checkObjectPermission(array $eInfo, $obj, string $action) {
        if ($action == NULL || !$this->getObjectPermission($eInfo, $obj, $action))
            throw new NotAllowedException($eInfo);
        return true;
    }

    /**
     * Configure object custom methods permissions
     * @param array $eInfo
     * @param object $obj
     * @return array
     */
    private function setObjectPermissions(array $eInfo, $obj) {
        $permissions = array();
        foreach (Conf::PERMISSIONS as $p)
            $permissions[$p] = $this->getObjectPermission($eInfo,$obj, $p);
        return array(
            'permissions' => $permissions,
            'obj' => $obj
        );
    }

    /**
     * @param array $eInfo
     * @param $obj
     * @param string $actionId
     * @return bool
     */
    private function getObjectPermission(array $eInfo, $obj, string $actionId) {
        var_dump($eInfo);
        if ($eInfo["access_details"] == null)
            return true;
        $accessDetails = $this->getAccessDetailsOfMethod($eInfo["access_details"], $actionId);
        $action = $eInfo['methods'][$actionId];
        $check = $this->getObjectCheckPermission($accessDetails, $action, $obj);
        $roles = $action["roles"] == null || $this->grantedRoles($action["roles"]);
        return $check && $roles;
    }

    /**
     * Check result of custom method permission if it's defined.
     * @param array $accessDetails
     * @param array $action
     * @param $obj
     * @return bool
     */
    private function getObjectCheckPermission(array $accessDetails, array $action, $obj){
        if (($methodName = $accessDetails["check"]) == NULL)
            return true;
        if ($action["isDefault"])
            throw new Exception('Impossible to define a custom check for default method.');
        $service = $this->container->get($action['service']);
        $methodName = $action['method'];
        $isAuthorise = $service->$methodName($obj);
        if (is_bool($isAuthorise) === false)
            throw new Exception('check method of access details must return a boolean.');
        return $isAuthorise;
    }

    /**
     * Check if current user have access to $entity with entityInfo
     * @param $entity
     * @param $action
     * @return bool
     */
    private function entityAccess($entity, $action = NULL) {
        if ($entity['access_details'] != NULL)
            return $action != NULL && $this->grantedAccessDetails($entity['access_details'], $action);
        elseif ($entity['access'] != NULL)
            return $this->grantedRoles($entity['access']);
        foreach ($entity['permissions'] as $method => $perm) {
            if ($perm)
                return true;
        }
        return false;
    }

    /**
     * Check if current user is granted at least one of roles for $method
     * @param array $accessDetails
     * @param $method
     * @return bool
     */
    private function grantedAccessDetails(array $accessDetails, $method) {
        if (($action = $this->getAccessDetailsOfMethod($accessDetails, $method)) != null) {
            if ($action["roles"] != null && $this->grantedRoles($action["roles"]))
                return true;
        }
        return false;
    }

    /**
     * Check if current user is granted at least one of $roles
     * @param array $roles
     * @return bool
     */
    private function grantedRoles(array $roles) {
        foreach ($roles as $role) {
            if ($this->context->isGranted($role))
                return true;
        }
        return false;
    }

    /**
     * Get access details of method
     * @param array $accessDetails
     * @param $method
     * @return mixed|null
     */
    private function getAccessDetailsOfMethod(array $accessDetails, $method) {
        foreach ($accessDetails as $action) {
            if ($method == $action["method"])
                return $action;
        }
        return null;
    }

    /**
     * Get settings
     * @return array
     */
    public function getSettings(){
        return $this->settings;
    }

    /**
     * Get current user
     * @return mixed
     */
    private function getUser()
    {
        return $this->token->getToken()->getUser();
    }
}
