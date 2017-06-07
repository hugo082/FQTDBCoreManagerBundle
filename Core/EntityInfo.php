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

use Doctrine\ORM\EntityManager as ORMManager;
use FQT\DBCoreManagerBundle\Annotations\AnnotationsContainer;
use FQT\DBCoreManagerBundle\Annotations\Viewable;
use FQT\DBCoreManagerBundle\Core\Model\iEncodable;
use FQT\DBRestManagerBundle\Manager\RestManager;
use Symfony\Component\DependencyInjection\ContainerInterface as Container;

use FQT\DBCoreManagerBundle\Core\Action;
use FQT\DBCoreManagerBundle\DependencyInjection\Configuration as Conf;

use MongoDB\Driver\Exception\ExecutionTimeoutException;
use Symfony\Component\Config\Definition\Exception\Exception;
use FQT\DBCoreManagerBundle\Exception\NotFoundException;
use FQT\DBCoreManagerBundle\Exception\NotAllowedException;

use Doctrine\Common\Annotations\AnnotationReader;

class EntityInfo implements iEncodable
{
    /**
     * @var ORMManager
     */
    private $em;

    /**
     * @var array
     */
    private $_annotations;


    /**
     * @var string
     */
    public $fullName;
    /**
     * @var string
     */
    public $bundle;
    /**
     * @var string
     */
    public $name;
    /**
     * @var string
     */
    public $fullPath;

    /**
     * @var string
     */
    public $formType;
    /**
     * @var string
     */
    public $fullFormType;
    /**
     * @var string
     */
    public $listingMethod;

    /**
     * @var array
     */
    public $actions;
    /**
     * @var Access
     */
    public $access;

    /**
     * @var array
     */
    public $views;

    /**
     * @var array
     */
    public $permissions;

    public function __construct(array $data, $container)
    {
        $this->em = $container->get('doctrine.orm.entity_manager');

        $this->name = $data['name'];
        $this->bundle = $data['bundle'];
        $this->fullName = $data['fullName'];
        $this->fullPath = $data['fullPath'];

        $this->formType = $data['formType'];
        $this->fullFormType = $data['fullFormType'];
        $this->listingMethod = $data['listingMethod'];

        $this->actions = array();
        foreach ($data['methods'] as $actionID => $action)
            $this->actions[$actionID] = new Action($action, $container);

    }

    public function encode(): array {
        return array(
            "name" => $this->name,
            "fullName" => $this->fullName,
            "actions" => RestManager::Encode($this->actions)
        );
    }

    public function computePermissions(){
        foreach ($this->actions as $action)
            $action->computePermissions();
    }

    /**
     * Check if current user have at least one permission.
     * @return bool
     */
    public function havePermission(){
        foreach ($this->actions as $action) {
            if ($action->computePermissions())
                return true;
        }
    }

    /**
     * @param string $actionID
     * @throws NotAllowedException
     */
    public function checkPermissionForActionWithID(string $actionID) {
        $action = $this->getActionWithID($actionID);
        $this->checkPermissionForAction($action);
    }

    /**
     * @param Action $action
     * @param bool $throw
     * @return bool
     * @throws NotAllowedException
     */
    public function checkPermissionForAction(Action $action, bool $throw = false, bool $force = false) {
        if ($action == null || !$action->isFullAuthorize($force)) {
            if ($throw)
                throw new NotAllowedException($this);
            return false;
        }
        return true;
    }

    /**
     * Return entity or entities and check his permissions with action id $actionID
     * @param string $actionID
     * @param int|null $id
     * @return array|object
     * @throws \Exception|NotAllowedException
     */
    public function getObjectWithActionID(string $actionID, int $id = null) {
        $action = $this->getActionWithID($actionID);
        if ($action == null)
            throw new \Exception("Impossible to check permission for action '" . $actionID . "'. Action not found.");
        return $this->getObject($action, $id);
    }

    /**
     * Return entity or entities and check his permissions with action $action
     * @param Action $action
     * @param int|null $id
     * @return array|object
     * @throws NotAllowedException
     */
    public function getObject(Action $action, int $id = null) {
        $bundle = str_replace(array("\\", "/"), "", $this->bundle);
        $repo = $this->em->getRepository($bundle.':'.$this->name);
        if ($id) {
            $action->object = $repo->find($id);
            $this->checkPermissionForAction($action, true, true);
            return $action->object;
        } elseif ($this->listingMethod != null) {
            $name = $this->listingMethod;
            $all = $repo->$name();
        } else
            $all = $repo->findAll();
        $res = array();
        foreach ($all as $object) {
            $perms = $this->computePermissionsForObject($object, $action);
            if ($perms['current'])
                $res[] = $perms;
        }
        return $res;
    }

    /**
     * Configure object custom methods permissions
     * @param $obj
     * @param Action|null $currentAction
     * @return array
     */
    private function computePermissionsForObject($obj, Action $currentAction = null) {
        $permissions = array();
        $currentPerm = false;
        /** @var Action $action */
        foreach ($this->actions as $action) {
            $action->object = $obj;
            if ($action == $currentAction)
                $currentPerm = $action->isFullAuthorize(true);
            $permissions[$action->id] = $action->environment == Conf::ENV_OBJECT && $action->isFullAuthorize(true);
        }
        return array(
            'current' => $currentPerm,
            'permissions' => $permissions,
            'annotation_container' => $this->loadAnnotationsContainer($obj)
        );
    }

    private function loadAnnotationsContainer($object) {
        if ($this->_annotations == null)
            $this->_annotations = $this->loadAnnotations($object);
        $container = new AnnotationsContainer($object->getId());
        /**
         * @var string $key
         * @var Viewable $annotation
         */
        foreach ($this->_annotations as $key => $annotation) {
            $a = Viewable::fromViewable($annotation);
            $mName = $annotation->getMethodName();
            $a->setValue($object->$mName());
            $container->pushAnnotation($a);
        }
        $container->sort();
        return $container;
    }

    /**
     * @param object $object
     * @return array
     */
    private function loadAnnotations($object) {
        $annotationReader = new AnnotationReader();
        $reflect = new \ReflectionClass($object);
        $methods = $reflect->getMethods(\ReflectionProperty::IS_PUBLIC);

        $tmp = array();
        foreach ($methods as $method) {
            $reflectionMethod = new \ReflectionMethod(get_class($object), $method->getName());
            $viewable = $this->getViewable($annotationReader->getMethodAnnotations($reflectionMethod));
            if ($viewable) {
                $viewable->setMethodName($method->getName());
                $tmp[] = $viewable;
            }
        }
        return $tmp;
    }

    /**
     * @param array $annotations
     * @return Viewable|null
     */
    private function getViewable(array $annotations) {
        foreach ($annotations as $annotation) {
            if ($annotation instanceof Viewable)
                return $annotation;
        }
        return null;
    }

    /**
     * @param string $actionID
     * @param bool $throw
     * @return null|Action
     * @throws \Exception
     */
    public function getActionWithID(string $actionID, bool $throw = false) {
        if (($action = self::getKeySecure($actionID, $this->actions)) == null and $throw)
            throw new \Exception("Impossible to find action with ID " . $actionID);
        return $action;
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