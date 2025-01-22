<?php

namespace App\Message;

class ParseArticleMessage
{
    private $url;
    private ?string $imageUrl;
    private ?string $shortDesc;

    public function __construct(string $url, ?string $imageUrl = null, ?string $shortDesc = null)
    {
        $this->url = $url;
        $this->imageUrl = $imageUrl;
        $this->shortDesc = $shortDesc;
    }

    public function getUrl(): string
    {
        return $this->url;
    }

    public function getImageUrl(): ?string
    {
        return $this->imageUrl;
    }

    public function getShortDesc(): ?string
    {
        return $this->shortDesc;
    }
}