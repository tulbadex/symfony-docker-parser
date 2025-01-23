<?php
namespace App\Repository;

use App\Entity\Article;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\Cache\CacheInterface;

class ArticleRepository extends ServiceEntityRepository
{
    private $cache;
    private const LIMIT = 10;

    public function __construct(ManagerRegistry $registry, CacheInterface $newsCache)
    {
        parent::__construct($registry, Article::class);
        $this->cache = $newsCache;
    }

    public function findByTitle(string $title): ?Article
    {
        $cacheKey = 'article_by_title_' . md5($title);
        
        return $this->cache->get($cacheKey, function(ItemInterface $item) use ($title) {
            $item->expiresAfter(3600); // Cache for 1 hour
            
            return $this->createQueryBuilder('a')
                ->andWhere('a.title = :title')
                ->setParameter('title', $title)
                ->getQuery()
                ->getOneOrNullResult();
        });
    }

    public function getPaginatedArticles(int $page = 1, int $limit = self::LIMIT): array
    {
        $cacheKey = sprintf('news_articles_page_%d', $page);

        return $this->cache->get($cacheKey, function(ItemInterface $item) use ($page, $limit) {
            $item->expiresAfter(3600); // Cache for 1 hour

            $query = $this->createBaseQuery()
                ->setFirstResult(($page - 1) * $limit)
                ->setMaxResults($limit)
                ->getQuery();

            return $query->getResult();
        });
    }

    public function getTotalArticlesCount(): int
    {
        return $this->cache->get('total_articles_count', function(ItemInterface $item) {
            $item->expiresAfter(3600);
            return $this->createQueryBuilder('a')
                ->select('COUNT(a.id)')
                ->getQuery()
                ->getSingleScalarResult();
        });
    }

    public function getTotalPages(int $limit = self::LIMIT): int
    {
        $totalCount = $this->getTotalArticlesCount();
        return max(1, ceil($totalCount / $limit));
    }

    private function createBaseQuery(): QueryBuilder
    {
        return $this->createQueryBuilder('a')
            ->select('partial a.{id, title, shortDescription, imageUrl, dateAdded, url}')
            ->orderBy('a.dateAdded', 'DESC');
    }
}