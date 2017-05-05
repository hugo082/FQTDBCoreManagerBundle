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
use Symfony\Component\Security\Core\Authorization\AuthorizationChecker;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage;
use FQT\DBCoreManagerBundle\DependencyInjection\Configuration as Conf;

use FQT\DBCoreManagerBundle\Exception\NotFoundException;
use FQT\DBCoreManagerBundle\Exception\NotAllowedException;

class EntityManager
{
    private $context;
    private $token;
    private $settings;
    private $entities;
    private $em;

    public function __construct(ORMManager $em, AuthorizationChecker $context, TokenStorage $token, array $settings, array $entities)
    {
        $this->em = $em;
        $this->context = $context;
        $this->token = $token;
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
     * @param string $action
     * @return bool
     */
    private function getObjectPermission(array $eInfo, $obj, string $action) {
        if (($m = $eInfo['access_details'][$action.'Method']) == NULL)
            return true;
        return $m($this->getUser(), $obj);
    }

    /**
     * Check if current user have access to $entity with entityInfo
     * @param $entity
     * @param $action
     * @return bool
     */
    private function entityAccess($entity, $action = NULL) {
        if ($entity['access_details'] != NULL) {
            if ($action != NULL)
                return $this->grantedRoles($entity['access_details'][$action]);
            foreach (Conf::PERMISSIONS as $perm) {
                if ($this->grantedRoles($entity['access_details'][$perm]))
                    return true;
            }
            return false;
        } elseif ($entity['access'] != NULL)
            return $this->grantedRoles($entity['access']);
        foreach ($entity['permissions'] as $method => $perm) {
            if ($perm)
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
