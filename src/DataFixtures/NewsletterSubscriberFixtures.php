<?php

namespace App\DataFixtures;

use App\Entity\NewsletterSubscriber;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

class NewsletterSubscriberFixtures extends Fixture implements FixtureGroupInterface
{
    public static function getGroups(): array
    {
        return ['newsletter'];
    }

    public function __construct(
        #[Autowire('%kernel.environment%')]
        private readonly string $environment,
    ) {
    }

    public function load(ObjectManager $manager): void
    {
        if ($this->environment === 'prod') {
            throw new \RuntimeException('Les fixtures newsletter sont interdites en production.');
        }

        $subscribers = [
            ['email' => 'alice.confirmed@example.com', 'status' => NewsletterSubscriber::STATUS_CONFIRMED],
            ['email' => 'bob.confirmed@example.com', 'status' => NewsletterSubscriber::STATUS_CONFIRMED],
            ['email' => 'carol.confirmed@example.com', 'status' => NewsletterSubscriber::STATUS_CONFIRMED],
            ['email' => 'dave.pending@example.com', 'status' => NewsletterSubscriber::STATUS_PENDING],
            ['email' => 'eve.pending@example.com', 'status' => NewsletterSubscriber::STATUS_PENDING],
            ['email' => 'frank.pending@example.com', 'status' => NewsletterSubscriber::STATUS_PENDING],
            ['email' => 'grace.unsubscribed@example.com', 'status' => NewsletterSubscriber::STATUS_UNSUBSCRIBED],
            ['email' => 'henry.unsubscribed@example.com', 'status' => NewsletterSubscriber::STATUS_UNSUBSCRIBED],
        ];

        $entities = [];
        foreach ($subscribers as $data) {
            $subscriber = new NewsletterSubscriber();
            $subscriber->setEmail($data['email']);
            $manager->persist($subscriber);
            $entities[] = [$subscriber, $data['status']];
        }
        $manager->flush();

        foreach ($entities as [$subscriber, $status]) {
            match ($status) {
                NewsletterSubscriber::STATUS_CONFIRMED => $subscriber->confirm(),
                NewsletterSubscriber::STATUS_UNSUBSCRIBED => $subscriber->unsubscribe(),
                default => null,
            };
        }

        $manager->flush();
    }
}
