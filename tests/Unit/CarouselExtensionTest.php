<?php

namespace App\Tests\Unit;

use App\Entity\CarouselSlide;
use App\Repository\CarouselSlideRepository;
use App\Security\HtmlSanitizer;
use App\Twig\CarouselExtension;
use PHPUnit\Framework\TestCase;

class CarouselExtensionTest extends TestCase
{
    private function createExtension(CarouselSlideRepository $repo): CarouselExtension
    {
        return new CarouselExtension($repo, new HtmlSanitizer());
    }

    public function testRegistersTwigFunction(): void
    {
        $repo = $this->createStub(CarouselSlideRepository::class);
        $extension = $this->createExtension($repo);

        $functions = $extension->getFunctions();

        $this->assertCount(1, $functions);
        $this->assertSame('carousel_slides', $functions[0]->getName());
    }

    public function testReturnsDefaultSlidesWhenRepositoryIsEmpty(): void
    {
        $repo = $this->createMock(CarouselSlideRepository::class);
        $repo->expects($this->once())
            ->method('findActiveOrderByPosition')
            ->willReturn([]);

        $extension = $this->createExtension($repo);
        $slides = $extension->getCarouselSlides();

        $this->assertCount(5, $slides);
        $this->assertSame('Prochain événement', $slides[0]['tag']);
        $this->assertSame('Nouvel article', $slides[1]['tag']);
    }

    public function testReturnsMappedSlidesFromEntities(): void
    {
        $slide = new CarouselSlide();
        $slide->setTag('GN');
        $slide->setTagColor('text-purple-400');
        $slide->setTitle('Mon événement');
        $slide->setDate('25 JAN');
        $slide->setBtnText('RÉSERVER');
        $slide->setBtnClass('bg-purple-600');
        $slide->setBtnUrl('/event/1');

        $repo = $this->createMock(CarouselSlideRepository::class);
        $repo->expects($this->once())
            ->method('findActiveOrderByPosition')
            ->willReturn([$slide]);

        $extension = $this->createExtension($repo);
        $slides = $extension->getCarouselSlides();

        $this->assertCount(1, $slides);
        $this->assertSame('GN', $slides[0]['tag']);
        $this->assertSame('text-purple-400', $slides[0]['tag_color']);
        $this->assertSame('Mon événement', $slides[0]['title']);
        $this->assertSame('25 JAN', $slides[0]['date']);
        $this->assertSame('RÉSERVER', $slides[0]['btn_text']);
        $this->assertSame('bg-purple-600', $slides[0]['btn_class']);
        $this->assertSame('/event/1', $slides[0]['btn_url']);
    }

    public function testStripsUnsafeBtnUrls(): void
    {
        $slide = new CarouselSlide();
        $slide->setTag('Hack');
        $slide->setBtnUrl('javascript:alert(1)');

        $repo = $this->createMock(CarouselSlideRepository::class);
        $repo->expects($this->once())
            ->method('findActiveOrderByPosition')
            ->willReturn([$slide]);

        $extension = $this->createExtension($repo);
        $slides = $extension->getCarouselSlides();

        $this->assertNull($slides[0]['btn_url']);
    }

    public function testFallsBackToDefaultsForNullEntityFields(): void
    {
        $slide = new CarouselSlide();
        // All nullable fields remain null

        $repo = $this->createMock(CarouselSlideRepository::class);
        $repo->expects($this->once())
            ->method('findActiveOrderByPosition')
            ->willReturn([$slide]);

        $extension = $this->createExtension($repo);
        $slides = $extension->getCarouselSlides();

        $this->assertCount(1, $slides);
        $this->assertSame('', $slides[0]['tag']);
        $this->assertSame('text-custom-orange', $slides[0]['tag_color']);
        $this->assertSame('', $slides[0]['title']);
        $this->assertSame('', $slides[0]['date']);
        $this->assertSame('', $slides[0]['btn_text']);
        $this->assertSame('bg-custom-orange group-hover:bg-orange-600 shadow-custom-orange/20', $slides[0]['btn_class']);
        $this->assertNull($slides[0]['btn_url']);
    }
}
