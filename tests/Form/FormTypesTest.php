<?php

namespace App\Tests\Form;

use App\Entity\Activity;
use App\Entity\Article;
use App\Entity\BoardGame;
use App\Entity\CarouselSlide;
use App\Entity\Inscription;
use App\Entity\User;
use App\Form\ActivityType;
use App\Form\ArticleType;
use App\Form\BoardGameType;
use App\Form\CarouselSlideType;
use App\Form\ChangePasswordFormType;
use App\Form\InscriptionType;
use App\Form\ResetPasswordRequestFormType;
use App\Form\UserType;
use Symfony\Component\Form\Extension\Validator\ValidatorExtension;
use Symfony\Component\Form\Test\TypeTestCase;
use Symfony\Component\Validator\Validation;

#[\PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations]
class FormTypesTest extends TypeTestCase
{
    protected function getExtensions(): array
    {
        return [
            new ValidatorExtension(Validation::createValidator()),
        ];
    }

    public function testActivityTypeSubmit(): void
    {
        $form = $this->factory->create(ActivityType::class, new Activity(), ['is_admin' => true]);
        $form->submit([
            'title' => 'Soirée JDS',
            'type' => 'JDS',
            'description' => 'Desc',
            'startAt' => '2026-08-01',
            'location' => 'Le Natema',
            'maxParticipants' => 8,
        ]);

        $this->assertTrue($form->isSynchronized());
        $this->assertTrue($form->isValid());
        /** @var Activity $activity */
        $activity = $form->getData();
        $this->assertSame('Soirée JDS', $activity->getTitle());
        $this->assertSame('JDS', $activity->getType());
    }

    public function testActivityTypeHidesAgForNonAdmin(): void
    {
        $form = $this->factory->create(ActivityType::class, new Activity(), ['is_admin' => false]);
        $choices = $form->get('type')->getConfig()->getOption('choices');

        $this->assertNotContains('AG', $choices);
        $this->assertContains('JDS', $choices);
    }

    public function testBoardGameTypeSubmit(): void
    {
        $form = $this->factory->create(BoardGameType::class, new BoardGame());
        $form->submit([
            'title' => 'Catan',
            'category' => 'Stratégie',
            'maxPlayers' => 4,
            'durationMinutes' => 90,
            'condition' => 'Bon état',
            'notes' => 'Un classique',
        ]);

        $this->assertTrue($form->isSynchronized());
        $this->assertTrue($form->isValid());
        $this->assertSame('Catan', $form->getData()->getTitle());
    }

    public function testCarouselSlideTypeRejectsUnsafeUrl(): void
    {
        $form = $this->factory->create(CarouselSlideType::class, new CarouselSlide());
        $form->submit([
            'position' => 0,
            'tag' => 'Tag',
            'tagColor' => 'text-custom-orange',
            'title' => 'Titre',
            'date' => 'DATE',
            'btnText' => 'GO',
            'btnClass' => 'bg-custom-orange group-hover:bg-orange-600 shadow-custom-orange/20',
            'btnUrl' => 'javascript:alert(1)',
        ]);

        $this->assertTrue($form->isSynchronized());
        $this->assertFalse($form->isValid());
    }

    public function testCarouselSlideTypeAcceptsRelativeUrl(): void
    {
        $form = $this->factory->create(CarouselSlideType::class, new CarouselSlide());
        $form->submit([
            'position' => 1,
            'tag' => 'Tag',
            'tagColor' => 'text-cyan-400',
            'title' => 'Titre',
            'date' => 'DATE',
            'btnText' => 'GO',
            'btnClass' => 'bg-cyan-600 group-hover:bg-cyan-700 shadow-cyan-500/20',
            'btnUrl' => '/evenements',
        ]);

        $this->assertTrue($form->isValid());
        $this->assertSame('/evenements', $form->getData()->getBtnUrl());
    }

    public function testArticleTypeRejectsUnsafeUrl(): void
    {
        $article = new Article();
        $article->setImage('existing.jpg');
        $form = $this->factory->create(ArticleType::class, $article);
        $form->submit([
            'position' => 0,
            'tag' => 'NEW',
            'title' => 'Titre',
            'url' => 'javascript:evil',
            'content' => 'texte',
        ]);

        $this->assertFalse($form->isValid());
    }

    public function testInscriptionTypeRequiresFieldsWhenGuest(): void
    {
        $form = $this->factory->create(InscriptionType::class, new Inscription(), ['is_logged_in' => false]);
        $form->submit([
            'participantName' => '',
            'participantEmail' => '',
        ]);

        $this->assertFalse($form->isValid());
    }

    public function testInscriptionTypeSubmitWhenGuest(): void
    {
        $form = $this->factory->create(InscriptionType::class, new Inscription(), ['is_logged_in' => false]);
        $form->submit([
            'participantName' => 'Alice',
            'participantEmail' => 'alice@example.com',
        ]);

        $this->assertTrue($form->isValid());
        $this->assertSame('Alice', $form->getData()->getParticipantName());
    }

    public function testUserTypeCreateRequiresPassword(): void
    {
        $form = $this->factory->create(UserType::class, new User(), [
            'is_edit' => false,
            'is_super_admin' => true,
        ]);

        $this->assertTrue($form->has('plainPassword'));

        $form->submit([
            'email' => 'a@example.com',
            'username' => 'alice',
            'roles' => ['ROLE_USER'],
            'plainPassword' => 'short',
        ]);

        $this->assertFalse($form->isValid());
    }

    public function testUserTypeEditHasNoPasswordField(): void
    {
        $form = $this->factory->create(UserType::class, new User(), [
            'is_edit' => true,
            'is_super_admin' => false,
        ]);

        $this->assertFalse($form->has('plainPassword'));
    }

    public function testResetPasswordRequestFormType(): void
    {
        $form = $this->factory->create(ResetPasswordRequestFormType::class);
        $form->submit(['email' => 'user@example.com']);

        $this->assertTrue($form->isValid());
        $this->assertSame('user@example.com', $form->get('email')->getData());
    }

    public function testChangePasswordFormTypeMismatch(): void
    {
        $form = $this->factory->create(ChangePasswordFormType::class);
        $form->submit([
            'plainPassword' => [
                'first' => 'ValidPassphrase!99',
                'second' => 'DifferentPass!99',
            ],
        ]);

        $this->assertFalse($form->isValid());
    }

    public function testChangePasswordFormTypeMatch(): void
    {
        $form = $this->factory->create(ChangePasswordFormType::class);
        $form->submit([
            'plainPassword' => [
                'first' => 'ValidPassphrase!99',
                'second' => 'ValidPassphrase!99',
            ],
        ]);

        // NotCompromisedPassword may fail offline; ignore that message if present
        if (!$form->isValid()) {
            foreach ($form->getErrors(true) as $error) {
                if (!str_contains($error->getMessage(), 'compromis')) {
                    $this->fail('Unexpected error: ' . $error->getMessage());
                }
            }
        } else {
            $this->assertTrue($form->isValid());
        }
    }
}
