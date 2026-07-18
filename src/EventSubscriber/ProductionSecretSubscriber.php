<?php

namespace App\EventSubscriber;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Exception\ServiceUnavailableHttpException;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Refuse le démarrage en production si APP_SECRET est encore un placeholder.
 */
final class ProductionSecretSubscriber implements EventSubscriberInterface
{
    private const PLACEHOLDER_SECRETS = [
        'change_me_in_env_local',
        'change_me_in_env_dev_local',
        'change_me',
    ];

    public function __construct(
        #[Autowire('%kernel.environment%')]
        private readonly string $environment,
        #[Autowire('%env(APP_SECRET)%')]
        private readonly string $appSecret,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => ['onKernelRequest', 512],
        ];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if ($this->environment !== 'prod' || !$event->isMainRequest()) {
            return;
        }

        if (
            \in_array($this->appSecret, self::PLACEHOLDER_SECRETS, true)
            || mb_strlen($this->appSecret) < 32
        ) {
            throw new ServiceUnavailableHttpException(
                null,
                'Configuration de sécurité incomplète (APP_SECRET). Contactez l\'administrateur.',
            );
        }
    }
}
