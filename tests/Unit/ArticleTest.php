<?php

namespace App\Tests\Unit;

use App\Entity\Article;
use PHPUnit\Framework\TestCase;

class ArticleTest extends TestCase
{
    public function testDefaultPositionIsZero(): void
    {
        $article = new Article();
        $this->assertSame(0, $article->getPosition());
    }

    public function testDefaultActiveIsTrue(): void
    {
        $article = new Article();
        $this->assertTrue($article->isActive());
    }

    public function testSettersAndGetters(): void
    {
        $article = new Article();
        
        $article->setPosition(5);
        $article->setTag('NEW');
        $article->setTitle('Test Title');
        $article->setImage('test.jpg');
        $article->setUrl('/test-url');
        $article->setActive(false);

        $this->assertSame(5, $article->getPosition());
        $this->assertSame('NEW', $article->getTag());
        $this->assertSame('Test Title', $article->getTitle());
        $this->assertSame('test.jpg', $article->getImage());
        $this->assertSame('/test-url', $article->getUrl());
        $this->assertFalse($article->isActive());
    }

    public function testUrlCanBeNull(): void
    {
        $article = new Article();
        $article->setUrl(null);
        $this->assertNull($article->getUrl());
    }

    public function testGetIdReturnsNullOnNewEntity(): void
    {
        $article = new Article();
        $this->assertNull($article->getId());
    }

    public function testContentGetterSetter(): void
    {
        $article = new Article();
        $this->assertNull($article->getContent());

        $content = '<p>Hello <strong>world</strong>!</p>';
        $article->setContent($content);
        $this->assertSame($content, $article->getContent());
    }

    public function testContentCanBeNull(): void
    {
        $article = new Article();
        $article->setContent(null);
        $this->assertNull($article->getContent());
    }

    public function testGalleryGetterSetter(): void
    {
        $article = new Article();
        $this->assertSame([], $article->getGallery());

        $gallery = ['img1.jpg', 'img2.webp'];
        $article->setGallery($gallery);
        $this->assertSame($gallery, $article->getGallery());

        $article->setGallery(null);
        $this->assertNull($article->getGallery());
    }
}
