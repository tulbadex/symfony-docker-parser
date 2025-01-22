<?php

namespace App\Controller;

use App\Repository\ArticleRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;
use Psr\Log\LoggerInterface;

class NewsController extends AbstractController
{
    /**
     * @Route("/news", name="news_list")
     * @IsGranted("ROLE_USER")
     */
    public function list(Request $request, ArticleRepository $articleRepository, LoggerInterface $logger): Response
    {
        try {
            $logger->info('Starting news list action', [
                'user' => $this->getUser() ? $this->getUser()->getUserIdentifier() : null,
                'page' => $request->query->get('page')
            ]);            

            $page = $request->query->getInt('page', 1);
            
            $logger->debug('Fetching articles from repository');
            $articles = $articleRepository->getPaginatedArticles($page);
            
            $logger->debug('Articles fetched', [
                'count' => $articles->count(),
                'page' => $page
            ]);

            return $this->render('news/list.html.twig', [
                'articles' => $articles,
                'page' => $page,
                'total_pages' => ceil($articles->count() / 10)
            ]);
        } catch (\Exception $e) {
            $logger->error('Error in news list action', [
                'exception' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }

    /**
     * @Route("/news/{id}/delete", name="news_delete", methods={"POST"})
     * @IsGranted("ROLE_ADMIN")
     */
    public function delete($id, ArticleRepository $articleRepository): Response
    {
        $article = $articleRepository->find($id);
        
        if (!$article) {
            throw $this->createNotFoundException('Article not found');
        }

        $entityManager = $this->getDoctrine()->getManager();
        $entityManager->remove($article);
        $entityManager->flush();

        $this->addFlash('success', 'Article deleted successfully');

        return $this->redirectToRoute('news_list');
    }
}