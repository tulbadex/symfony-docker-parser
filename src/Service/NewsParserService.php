<?php

namespace App\Service;

use App\Entity\Article;
use App\Message\ParseArticleMessage;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Psr\Log\LoggerInterface;

class NewsParserService
{
    private $em;
    private $client;
    private $messageBus;
    private $logger;

    public function __construct(
        EntityManagerInterface $em,
        HttpClientInterface $client,
        MessageBusInterface $messageBus,
        LoggerInterface $logger
    ) {
        $this->em = $em;
        $this->client = $client;
        $this->messageBus = $messageBus;
        $this->logger = $logger;
    }

    public function parseNewsUrls(string $categoryUrl = 'https://highload.today/category/novosti/'): void
    {
        try {

            $response = $this->client->request('GET', $categoryUrl);
            $content = $response->getContent();

            $crawler = new Crawler($content);
            $articleLinks = $crawler->filter('.col.sidebar-center .lenta-item')->slice(1);

            $articleLinks->each(function (Crawler $item) {
                try {
                    // Extract URL
                    $linkElement = $item->filter('a')->reduce(function ($node) {
                        return $node->getNode(0)->parentNode->nodeName === 'div';
                    })->first();
                    $url = $linkElement->attr('href');

                    // Extract image URL (first visible image)
                    // $imageUrl = $item->filter('.lenta-image img')->first()->attr('src', '');
                    $imageNode = $item->filter('.lenta-image img')->first();
                    $imageUrl = $imageNode->attr('data-lazy-src') ?: $imageNode->attr('src');
                    $shortDesc = '';
                    $item->filter('p')->each(function (Crawler $p) use (&$shortDesc) {
                        $parentNode = $p->getNode(0)->parentNode;
                        if ($parentNode && $parentNode->nodeName === 'div') {
                            $shortDesc = $p->text('');
                            return false; // Stop further iterations once the correct <p> is found
                        }
                    });
                    $this->messageBus->dispatch(new ParseArticleMessage($url, $imageUrl, $shortDesc));
                } catch (\Exception $e) {
                    $this->logger->warning("Skipping invalid article element: " . $e->getMessage());
                }
            });
        } catch (\Exception $e) {
            $this->logger->error("Error parsing news URLs: " . $e->getMessage());
            throw $e;
        }
    }

    public function parseArticle(ParseArticleMessage $message): void
    {
        $url = $message->getUrl();
        $previewImageUrl = $message->getImageUrl();
        $shortDesc = $message->getShortDesc();

        try {
            $response = $this->client->request('GET', $url);
            $crawler = new Crawler($response->getContent());

            $title = $crawler->filter('h1.main-title')->text('');
            $content = $crawler->filter('.content-inner')->each(function (Crawler $node) {
                $node->filter('.mobile-show, .mobile-hide, script')->each(function (Crawler $unwanted) {
                    foreach ($unwanted as $node) {
                        $node->parentNode->removeChild($node);
                    }
                });

                return $node->text();
            });
            $description = implode("\n", $content);

            // Check if article already exists
            $existingArticle = $this->em->getRepository(Article::class)->findOneBy(['url' => $url]);

            if ($existingArticle) {
                $existingArticle->setLastUpdated(new \DateTime());
                if ($previewImageUrl) {
                    $existingArticle->setImageUrl($previewImageUrl);
                }
            } else {
                $article = new Article();
                $article->setTitle($title);
                $article->setDescription($description);
                $article->setShortDescription($shortDesc);
                $article->setUrl($url);
                $article->setImageUrl($previewImageUrl);
                $article->setDateAdded(new \DateTime());

                $this->em->persist($article);
            }

            $this->em->flush();
        } catch (\Throwable $e) {
            $this->logger->error("Error parsing article URL: $url", ['exception' => $e]);
            throw $e;
        }
    }
}