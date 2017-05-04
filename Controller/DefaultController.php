<?php

namespace FQT\DBCoreManagerBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;

class DefaultController extends Controller
{
    /**
     * @Route("/", name="fqtdbcm")
     */
    public function indexAction()
    {
        return $this->render('FQTDBCoreManagerBundle:Default:index.html.twig');
    }
}
