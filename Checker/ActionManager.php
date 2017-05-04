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

use FQT\DBCoreManagerBundle\Checker\EntityManager as Checker;
use FQT\DBCoreManagerBundle\DependencyInjection\Configuration as Conf;
use FQT\DBCoreManagerBundle\Event\ActionEvent;
use FQT\DBCoreManagerBundle\FQTDBCoreManagerEvents as DBCMEvents;


class ActionManager
{
    private $em;
    private $checker;
    private $factory;
    private $dispatcher;

    public function __construct(ORMManager $em, Checker $checker, FormFactoryInterface $formFactory, Dispatcher $dispatcher)
    {
        $this->em = $em;
        $this->checker = $checker;
        $this->factory = $formFactory;
        $this->dispatcher = $dispatcher;
    }

    /**
     * @param Request $request
     * @param $name
     * @return array
     */
    public function listAction(Request $request, $name)
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
