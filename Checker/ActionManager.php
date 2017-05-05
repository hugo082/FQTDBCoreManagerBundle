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

        $entityInfo = $this->checker->getEntity($name, $method);
        $action = $entityInfo["methods"][$method];

        $data = $this->defaultAction($request, $method, $entityInfo, $id);
        if ($data == null) {
            $entityObject = $this->checker->getEntityObject($entityInfo, $method, $id);

            $service = $this->container->get($action['service']);
            $methodName = $action['method'];
            $data = $service->$methodName($entityObject);
        }

        return array(
            "entityInfo" => $entityInfo,
            "data" => $data
        );
    }

    /**
     * @param $eInfo
     * @return array
     */
    public function listAction($eInfo)
    {
        $all = $this->checker->getEntityObject($eInfo);
        return array(
            "success" => true,
            "all" => $all
        );
    }

    /**
     * @param Request $request
     * @param $eInfo
     * @param $id
     * @return array|null
     */
    public function editAction(Request $request, $eInfo, $id)
    {
        $all = $this->checker->getEntityObject($eInfo);
        $entityObject = $this->checker->getEntityObject($eInfo, Conf::DEF_EDIT, $id);
        if (!$entityObject)
            return null;
        $process = $this->processForm($request, $eInfo, $entityObject);
        return array(
            "success" => $process["success"],
            "form" => $process["form"]->createView(),
            "all" => $all,
            "flash" => $process["flash"]
        );
    }

    /**
     * @param Request $request
     * @param $eInfo
     * @return array
     */
    public function addAction(Request $request, $eInfo)
    {
        $this->checker->checkObjectPermission($eInfo, null, Conf::DEF_ADD);
        $process = $this->processAddForm($request, $eInfo);
        return array(
            "success" => $process["success"],
            "form" => $process["form"]->createView(),
            "flash" => $process["flash"]
        );
    }

    public function removeAction($eInfo, $id)
    {
        $event = null;
        $entityObject = $this->checker->getEntityObject($eInfo, Conf::PERM_REMOVE, $id);
        if ($entityObject) {
            $event = $this->executeAction($eInfo, $entityObject, false);
            $flash = array(array("type" => 'success', "message" => 'Supprimé !'));
        } else
            $flash = array(array("type" => 'error', "message" => 'Not found'));
        return array(
            "success" => $entityObject != null,
            "event" => $event,
            "flash" => $flash
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
     * @param $entityInfo
     * @param $id
     * @return array|null
     */
    private function defaultAction(Request $request, $method, $entityInfo, $id) {
        if ($method == Conf::DEF_LIST)
            return $this->listAction($entityInfo);
        elseif ($method == Conf::DEF_ADD)
            return $this->addAction($request, $entityInfo);
        elseif ($method == Conf::PERM_EDIT)
            return $this->editAction($request, $entityInfo, $id);
        elseif ($method == Conf::DEF_REMOVE)
            return $this->removeAction($entityInfo, $id);
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
                "form" => $form,
                "flash" => array(
                    array("type" => 'success', "message" => 'Modification enregistré')
                )
            );
        }
        return array(
            "success" => false,
            "form" => $form,
            "flash" => array()
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
