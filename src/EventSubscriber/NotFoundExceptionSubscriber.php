<?php

namespace App\EventSubscriber;

use Psr\Log\LoggerInterface;
use Symfony\Component\ErrorHandler\Exception\FlattenException;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\KernelEvents;
use Twig\Environment;

/**
 * Affiche la page 404 custom pour toute URL introuvable,
 * quel que soit l'utilisateur et même en APP_DEBUG=1.
 */
final class NotFoundExceptionSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private Environment $twig,
        private ?LoggerInterface $logger = null,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        // Avant ErrorListener (-128) pour intercepter les 404 nous-mêmes
        return [KernelEvents::EXCEPTION => ['onKernelException', -100]];
    }

    public function onKernelException(ExceptionEvent $event): void
    {
        $throwable = $event->getThrowable();
        $statusCode = $throwable instanceof HttpExceptionInterface
            ? $throwable->getStatusCode()
            : 500;

        if (404 !== $statusCode) {
            return;
        }

        if (!$this->wantsHtml($event->getRequest())) {
            return;
        }

        try {
            $flattenException = FlattenException::createFromThrowable($throwable);

            $content = $this->twig->render('@Twig/Exception/error404.html.twig', [
                'exception' => $flattenException,
                'status_code' => 404,
                'status_text' => $flattenException->getStatusText(),
            ]);

            $event->setResponse(new Response($content, 404));
            $event->stopPropagation();
        } catch (\Throwable $e) {
            $this->logger?->error('Impossible de rendre la page 404 custom : {message}', [
                'message' => $e->getMessage(),
                'exception' => $e,
            ]);
            // Laisser ErrorListener gérer le fallback
        }
    }

    private function wantsHtml(Request $request): bool
    {
        $format = $request->getRequestFormat();
        if ($format && 'html' !== $format) {
            return false;
        }

        $acceptable = $request->getAcceptableContentTypes();
        if ([] === $acceptable) {
            return true;
        }

        foreach ($acceptable as $type) {
            if (str_contains($type, 'html') || '*/*' === $type || 'application/xhtml+xml' === $type) {
                return true;
            }
        }

        // Navigateurs / clients sans Accept explicite JSON/XML → HTML
        return !str_contains(implode(',', $acceptable), 'json')
            && !str_contains(implode(',', $acceptable), 'xml');
    }
}
