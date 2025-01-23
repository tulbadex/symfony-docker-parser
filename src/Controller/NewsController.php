<?php

namespace App\Controller;

use App\Repository\ArticleRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;
// use Symfony\Component\Cache\Annotation\Cache;
use Doctrine\Persistence\ManagerRegistry;

class NewsController extends AbstractController
{
    /**
     * @Route("/news", name="news_list")
     * @IsGranted("ROLE_USER")
     */
    public function list(Request $request, ArticleRepository $articleRepository): Response
    {
        $page = $request->query->getInt('page', 1);
        $totalPages = $articleRepository->getTotalPages();
        
        // Ensure page is within valid range
        $page = max(1, min($page, $totalPages));
        
        $articles = $articleRepository->getPaginatedArticles($page);

        return $this->render('news/list.html.twig', [
            'articles' => $articles,
            'page' => $page,
            'total_pages' => $totalPages
        ])->setCache([
            'max_age' => 3600,
            's_maxage' => 3600,
            'public' => true
        ]);
    }

    /**
     * @Route("/news/{id}/delete", name="news_delete", methods={"POST"})
     * @IsGranted("ROLE_ADMIN")
     */
    public function delete($id, ArticleRepository $articleRepository, ManagerRegistry $doctrine): Response
    {
        $article = $articleRepository->find($id);
        
        if (!$article) {
            throw $this->createNotFoundException('Article not found');
        }

        $entityManager = $doctrine->getManager();
        $entityManager->remove($article);
        $entityManager->flush();

        $this->addFlash('success', 'Article deleted successfully');

        return $this->redirectToRoute('news_list');
    }
}