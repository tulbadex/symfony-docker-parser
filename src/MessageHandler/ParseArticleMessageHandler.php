<?php

namespace App\MessageHandler;

use App\Message\ParseArticleMessage;
use App\Service\NewsParserService;
use Symfony\Component\Messenger\Handler\MessageHandlerInterface;

class ParseArticleMessageHandler implements MessageHandlerInterface
{
    private $newsParserService;

    public function __construct(NewsParserService $newsParserService)
    {
        $this->newsParserService = $newsParserService;
    }

    public function __invoke(ParseArticleMessage $message)
    {
        $this->newsParserService->parseArticle($message);
    }
}