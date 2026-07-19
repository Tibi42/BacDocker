<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class SitemapController extends AbstractController
{
    #[Route('/sitemap.xml', name: 'app_sitemap', defaults: ['_format' => 'xml'])]
    public function index(): Response
    {
        $urls = [
            ['route' => 'app_home',                 'priority' => '1.0',  'changefreq' => 'daily'],
            ['route' => 'app_jds',                  'priority' => '0.8',  'changefreq' => 'monthly'],
            ['route' => 'app_ludotheque',           'priority' => '0.7',  'changefreq' => 'weekly'],
            ['route' => 'app_jdr',                  'priority' => '0.8',  'changefreq' => 'monthly'],
            ['route' => 'app_gn',                   'priority' => '0.8',  'changefreq' => 'monthly'],
            ['route' => 'app_association',           'priority' => '0.8',  'changefreq' => 'monthly'],
            ['route' => 'app_nos_soiree_heb',       'priority' => '0.7',  'changefreq' => 'monthly'],
            ['route' => 'app_nos_soiree_biheb',     'priority' => '0.7',  'changefreq' => 'monthly'],
            ['route' => 'app_nos_soiree_mensuelle', 'priority' => '0.7',  'changefreq' => 'monthly'],
            ['route' => 'app_evenements',           'priority' => '0.6',  'changefreq' => 'weekly'],
            ['route' => 'app_nouvelles',            'priority' => '0.6',  'changefreq' => 'weekly'],
            ['route' => 'app_qui_sommes_nous',      'priority' => '0.5',  'changefreq' => 'monthly'],
            ['route' => 'app_societes',             'priority' => '0.4',  'changefreq' => 'monthly'],
            ['route' => 'app_contact',              'priority' => '0.5',  'changefreq' => 'yearly'],
            ['route' => 'app_mentions_legales',     'priority' => '0.2',  'changefreq' => 'yearly'],
        ];

        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";

        $now = (new \DateTimeImmutable())->format('Y-m-d');

        foreach ($urls as $entry) {
            $loc = $this->generateUrl($entry['route'], [], UrlGeneratorInterface::ABSOLUTE_URL);
            $xml .= "  <url>\n";
            $xml .= "    <loc>" . htmlspecialchars($loc) . "</loc>\n";
            $xml .= "    <lastmod>{$now}</lastmod>\n";
            $xml .= "    <changefreq>{$entry['changefreq']}</changefreq>\n";
            $xml .= "    <priority>{$entry['priority']}</priority>\n";
            $xml .= "  </url>\n";
        }

        $xml .= '</urlset>';

        return new Response($xml, 200, ['Content-Type' => 'application/xml']);
    }
}
