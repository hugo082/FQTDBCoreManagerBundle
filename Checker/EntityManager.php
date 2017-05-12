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
use FQT\DBCoreManagerBundle\Core\EntityInfo;

use FQT\DBCoreManagerBundle\Exception\NotFoundException;
use FQT\DBCoreManagerBundle\Exception\NotAllowedException;

class EntityManager
{
    /**
     * @var array
     */
    private $entities;

    public function __construct(Container $container, array $entities)
    {
        $this->entities = array();
        foreach ($entities as $entityID => $entity)
            $this->entities[$entityID] = new EntityInfo($entity, $container);
    }

    /**
     * Get entity information of $name and check permission for action with ID $actionID
     * @param string $name
     * @param string $actionID
     * @return EntityInfo|null
     * @throws NotFoundException|NotAllowedException
     */
    public function getEntity(string $name, string $actionID) {
        $entityInfo = $this->getEntityWithID($name);
        if ($entityInfo == null)
            throw new NotFoundException($name);
        $entityInfo->checkPermissionForActionWithID($actionID);
        return $entityInfo;
    }

    /**
     * Return all entities where current user can execute action or access information
     * @return array
     */
    public function getEntities() {
        $tmp = $this->entities;
        /** @var EntityInfo $e */
        foreach ($tmp as $key => $e) {
            if (!$e->havePermission())
                unset($tmp[$key]);
        }
        return $tmp;
    }

    /**
     * @param string $eID
     * @return EntityInfo | null
     */
    private function getEntityWithID(string $eID) {
        return self::getKeySecure($eID, $this->entities);
    }

    /**
     * Return value of key or null if doesn't exist
     * @param $key
     * @param $array
     * @return null
     */
    private static function getKeySecure($key, $array) {
        if (key_exists($key, $array))
            return $array[$key];
        return null;
    }
}
