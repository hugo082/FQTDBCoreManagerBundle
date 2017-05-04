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

namespace FQT\DBCoreManagerBundle\Event\Listener;

use FQT\DBCoreManagerBundle\Exception\ExceptionInterface;
use Symfony\Component\HttpFoundation\Response;

use Symfony\Component\HttpKernel\Event\GetResponseForExceptionEvent;

class ExceptionListener
{
    protected $twig;
    protected $environment;

    public function __construct(\Twig_Environment $twig, string $environment)
    {
        $this->twig = $twig;
        $this->environment = $environment;
    }

    public function onKernelException(GetResponseForExceptionEvent $event)
    {
        $exception = $event->getException();

        if (!$exception instanceof ExceptionInterface) {
            return;
        }

        $responseData = [
            'exception' => [
                'title' => $exception->getTitle($this->environment),
                'message' => $exception->getMessage(),
                'statusCode' => $exception->getStatusCode($this->environment),
                'headers' => $exception->getHeaders($this->environment)
            ]
        ];

        if ($this->environment != 'prod')
            $responseData['exception']['message'] = $exception->getDevMessage();

        $content = $this->twig->render('DBManagerBundle:Exception:error.html.twig', $responseData);

        $event->setResponse(new Response($content));
    }
}
