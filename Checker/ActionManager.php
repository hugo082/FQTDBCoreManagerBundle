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
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface as Dispatcher;

use Symfony\Component\DependencyInjection\ContainerInterface as Container;

use FQT\DBCoreManagerBundle\Checker\EntityManager as Checker;
use FQT\DBCoreManagerBundle\DependencyInjection\Configuration as Conf;
use FQT\DBCoreManagerBundle\Event\ActionEvent;
use FQT\DBCoreManagerBundle\FQTDBCoreManagerEvents as DBCMEvents;


class ActionManager
{
    /**
     * @var ORMManager
     */
    private $em;
    /**
     * @var Checker
     */
    private $checker;
    /**
     * @var FormFactoryInterface
     */
    private $factory;
    /**
     * @var Dispatcher
     */
    private $dispatcher;
    /**
     * @var Container
     */
    private $container;

    public function __construct(Container $container)
    {
        $this->em = $container->get('doctrine.orm.entity_manager');
        $this->checker = $container->get('fqt.dbcm.checker');
        $this->factory = $container->get('form.factory');
        $this->dispatcher = $container->get('event_dispatcher');
        $this->container = $container;
    }

    public function customAction(Request $request, $method, $name, $id) {
        $execution = $this->defaultAction($request, $method, $name, $id);
        if ($execution != null)
            return $execution;

        $eInfo = $this->checker->getEntity($name, $method);
        $entityObject = $this->checker->getEntityObject($eInfo, $method, $id);

        $methodInfo = $eInfo['methods'][$method];
        $service = $this->container->get($methodInfo['service']);
        $methodName = $methodInfo['method'];
        $data = $service->$methodName($entityObject);
        return array(
            "success" => null,
            "entityInfo" => $eInfo,
            "form" => null,
            "data" => $data
        );
    }

    /**
     * @param $name
     * @return array
     */
    public function listAction($name)
    {
        $eInfo = $this->checker->getEntity($name, Conf::PERM_LIST);
        $all = $this->checker->getEntityObject($eInfo);
        return array(
            "success" => null,
            "entityInfo" => $eInfo,
            "form" => null,
            "data" => $all
        );
    }

    /**
     * @param Request $request
     * @param $name
     * @param $id
     * @return array|null
     */
    public function editAction(Request $request, $name, $id)
    {
        $eInfo = $this->checker->getEntity($name, Conf::PERM_EDIT);

        $all = $this->checker->getEntityObject($eInfo);
        $entityObject = $this->checker->getEntityObject($eInfo, Conf::PERM_EDIT, $id);
        if (!$entityObject)
            return null;
        $process = $this->processForm($request, $eInfo, $entityObject);
        return array(
            "success" => $process["success"],
            "entityInfo" => $eInfo,
            "form" => $process["form"],
            "data" => $all
        );
    }

    /**
     * @param Request $request
     * @param $name
     * @return array
     */
    public function addAction(Request $request, $name)
    {
        $eInfo = $this->checker->getEntity($name, Conf::PERM_ADD);
        $this->checker->checkObjectPermission($eInfo, null, Conf::PERM_ADD);

        $process = $this->processAddForm($request, $eInfo);
        return array(
            "success" => $process["success"],
            "entityInfo" => $eInfo,
            "form" => $process["form"],
            "data" => null
        );
    }

    public function removeAction($name, $id)
    {
        $event = null;
        $eInfo = $this->checker->getEntity($name, Conf::PERM_REMOVE);
        $entityObject = $this->checker->getEntityObject($eInfo, Conf::PERM_REMOVE, $id);
        if ($entityObject)
            $event = $this->executeAction($eInfo, $entityObject, false);
        return array(
            "success" => $entityObject != null,
            "entityInfo" => $eInfo,
            "form" => null,
            "data" => $event
        );
    }


    /**
     * Process form for add action.
     * Can be call in listController.
     *
     * @param Request $request
     * @param array $eInfo
     * @return array
     */
    public function processAddForm(Request $request, array $eInfo) {
        $entityObject = new $eInfo['fullPath']();
        return $this->processForm($request, $eInfo, $entityObject);
    }

    /**
     * Execute action if it's a default
     * @param Request $request
     * @param $method
     * @param $name
     * @param $id
     * @return array|null
     */
    private function defaultAction(Request $request, $method, $name, $id) {
        if ($method == Conf::DEF_LIST)
            return $this->listAction($name);
        elseif ($method == Conf::DEF_ADD)
            return $this->addAction($request, $name);
        elseif ($method == Conf::PERM_EDIT)
            return $this->editAction($request, $name, $id);
        elseif ($method == Conf::DEF_REMOVE)
            return $this->removeAction($name, $id);
        return null;
    }

    /**
     * @param Request $request
     * @param array $eInfo
     * @param $entityObject
     * @return array
     */
    private function processForm(Request $request, array $eInfo, $entityObject){
        $form = $this->factory->create($eInfo['fullFormType'], $entityObject);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $this->executeAction($eInfo, $entityObject);
            return array(
                "success" => true,
                "form" => $form
            );
        }
        return array(
            "success" => false,
            "form" => $form
        );
    }

    /**
     * Execution action with form information
     *
     * @param array $eInfo
     * @param $entityObject
     * @param bool $isPersist
     * @return ActionEvent
     */
    private function executeAction(array $eInfo, $entityObject, $isPersist = true) {
        $event = new ActionEvent($eInfo, $entityObject, array('success', 'Flash description of action')); // TODO: Dynamic flash msg
        $this->dispatcher->dispatch(DBCMEvents::ACTION_ADD_BEFORE, $event); // TODO: Dynamic Event Action

        if (!$event->isExecuted()) {
            if ($isPersist)
                $this->em->persist($entityObject);
            else
                $this->em->remove($entityObject);
            $this->em->flush();
        }
        return $event;
    }
}
