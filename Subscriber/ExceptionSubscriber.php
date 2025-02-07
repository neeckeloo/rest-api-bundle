<?php

namespace Wizards\RestBundle\Subscriber;

use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\GetResponseForExceptionEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Wizards\RestBundle\Exception\MultiPartHttpException;
use WizardsRest\Exception\HttpException;

/**
 * Serializes a controller output to a configured format response.
 */
class ExceptionSubscriber implements EventSubscriberInterface
{
    /**
     * @var LoggerInterface
     */
    private $logger;

    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    /**
     * @return array
     */
    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::EXCEPTION => 'onKernelException'
        ];
    }

    /**
     * @param GetResponseForExceptionEvent $event
     */
    public function onKernelException(GetResponseForExceptionEvent $event)
    {
        $exception = $event->getException();

        $this->logger->log('error', $exception->getMessage());

        $response = new Response();
        $response->setContent(json_encode(['errors' => $this->getErrorBody($exception)]));
        $response->setStatusCode(Response::HTTP_INTERNAL_SERVER_ERROR);

        if ($exception instanceof HttpExceptionInterface || $exception instanceof HttpException) {
            $response->setStatusCode($exception->getStatusCode());
            $response->headers->replace(['content-type' => 'application/vnd.api+json']);
        }

        $event->setResponse($response);
    }

    /**
     * Formats the error body.
     *
     * @param \Exception $exception
     *
     * @return array
     */
    private function getErrorBody($exception)
    {
        if ($exception instanceof MultiPartHttpException) {
            return array_map(
                function ($error) {
                    return ['detail' => $error];
                },
                $exception->getMessageList()
            );
        }

        return [['detail' => $exception->getMessage()]];
    }
}
