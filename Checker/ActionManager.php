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
use Doctrine\ORM\Mapping\Entity;
use FQT\DBCoreManagerBundle\Core\Action;
use FQT\DBCoreManagerBundle\Exception\NotAllowedException;
use FQT\DBCoreManagerBundle\Exception\NotFoundException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface as Dispatcher;
use Symfony\Component\DependencyInjection\ContainerInterface as Container;

use FQT\DBCoreManagerBundle\Core\EntityInfo;
use FQT\DBCoreManagerBundle\Core\Execution;
use FQT\DBCoreManagerBundle\Core\Data;
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

    /**
     * @var null|EntityInfo
     */
    private $entityInfo = null;

    public function __construct(Container $container)
    {
        $this->em = $container->get('doctrine.orm.entity_manager');
        $this->checker = $container->get('fqt.dbcm.checker');
        $this->factory = $container->get('form.factory');
        $this->dispatcher = $container->get('event_dispatcher');
        $this->container = $container;
    }

    public function indexAction() {
        return $this->checker->getEntities();
    }

    /**
     * @param Request $request
     * @param $actionID
     * @param $name
     * @param $id
     * @return Execution
     * @throws NotAllowedException
     */
    public function customAction(Request $request, $actionID, $name, $id) {
        $this->entityInfo = $this->checker->getEntity($name, $actionID);
        $action = $this->entityInfo->getActionWithID($actionID, true);
        if (!$action->isFullAuthorize())
            throw new NotAllowedException($this->entityInfo);
        $data = $this->processAction($request, $action, $id);
        return new Execution($this->entityInfo, $action, $data);
    }

    /**
     * @param Request $request
     * @param Action $action
     * @param null|int $id
     * @return Data
     * @throws \Exception
     */
    public function processAction(Request $request, Action $action, int $id = null) {
        $data = $this->defaultAction($request, $action, $id);
        if ($data == null) {
            $this->entityInfo->getObjectAnnotations($action, $id);

            $service = $this->container->get($action->serviceName);
            $methodName = $action->method;
            $data = $service->$methodName($action->object, $request);
        }
        if (!$data instanceof Data)
            throw new \Exception("Data must be an instance of " . Data::class . ", " . gettype($data) . " given.");
        return $data;
    }

    /**
     * @param Action $action
     * @param int|null $id
     * @return Data
     */
    public function listAction(Action $action, int $id = null)
    {
        $id = ($id != null && $id >= 0) ? $id : null;
        $all = $this->entityInfo->getObjectAnnotations($action, $id);
        if (!key_exists(0, $all))
            $all = array($all);
        return new Data(array(
            "success" => true,
            "all" => $all)
        );
    }

    /**
     * @param Request $request
     * @param Action $action
     * @param int $id
     * @return Data
     * @throws NotFoundException
     */
    public function editAction(Request $request, Action $action, int $id)
    {
        $this->entityInfo->getObjectAnnotations($action, $id);
        if (!$action->object)
            throw new NotFoundException($this); // TODO : Not found Object not Entity
        $process = $this->processForm($request, $action->object);
        return new Data(array(
            "success" => $process["success"],
            "redirect" => $process["success"],
            "form" => $process["form"]->createView(),
            "flash" => $process["flash"])
        );
    }

    /**
     * @param Request $request
     * @return Data
     */
    public function addAction(Request $request)
    {
        $process = $this->processAddForm($request);
        return new Data(array(
            "success" => $process["success"],
            "redirect" => $process["success"],
            "form" => $process["form"]->createView(),
            "flash" => $process["flash"])
        );
    }

    /**
     * @param Action $action
     * @param $id
     * @return Data
     */
    public function removeAction(Action $action, $id)
    {
        $this->entityInfo->getObjectAnnotations($action, $id);
        if ($action->object) {
            $this->executeAction($action->object, false);
            $flash = array(array("type" => 'success', "message" => 'Supprimé !'));
        } else
            $flash = array(array("type" => 'error', "message" => 'Not found'));
        return new Data(array(
            "success" => $action->object != null,
            "redirect" => true,
            "flash" => $flash)
        );
    }


    /**
     * Process form for add action.
     * Can be call in listController.
     *
     * @param Request $request
     * @return array
     */
    public function processAddForm(Request $request) {
        $entityObject = new $this->entityInfo->fullPath();
        return $this->processForm($request, $entityObject);
    }

    /**
     * Execute action if it's a default
     * @param Request $request
     * @param Action $action
     * @param null|int $id
     * @return Data
     */
    private function defaultAction(Request $request, Action $action, int $id = null) {
        if ($action->id == Conf::DEF_LIST)
            return $this->listAction($action, $id);
        elseif ($action->id == Conf::DEF_ADD)
            return $this->addAction($request);
        elseif ($action->id == Conf::PERM_EDIT)
            return $this->editAction($request, $action, $id);
        elseif ($action->id == Conf::DEF_REMOVE)
            return $this->removeAction($action, $id);
        return null;
    }

    /**
     * @param Request $request
     * @param $entityObject
     * @return array
     */
    private function processForm(Request $request, $entityObject){
        $form = $this->factory->create($this->entityInfo->fullFormType, $entityObject);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $this->executeAction($entityObject);
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
     * @param $entityObject
     * @param bool $isPersist
     * @return ActionEvent
     */
    private function executeAction($entityObject, $isPersist = true) {
        $event = new ActionEvent($this->entityInfo, $entityObject, array('success', 'Flash description of action')); // TODO: Dynamic flash msg
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
