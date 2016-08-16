<?php

class newspaperParser
{
    protected $newspaper;

    public function __construct($adapter)
    {
        if (class_exists($adapter . 'Parser')) {
            $this->newspaper = $adapter . 'Parser';
        } else {
            throw new BadFunctionCallException;
        }
    }

    public function parse($text)
    {
        $parser = new $this->newspaper();
        return $parser->parseText($text);
    }
}

interface NewspaperInterface
{
    public function parseText($text);
}

class lanacioncomarParser extends newspaperParser
{
    protected $dom;

    public function __construct()
    {
        $this->dom = new DOMDocument();
        libxml_use_internal_errors(true);
    }

    public function parseText($text)
    {
        $this->dom->loadHTML($text);
        $xpath = new DOMXPath($this->dom);

        $titulo = utf8_decode('<h1>' . $xpath->query("//*[@itemprop='headline']")->item(0)->nodeValue . '</h1>');
        $bajada = utf8_decode('<h2>' . $xpath->query("//*[@itemprop='description']")->item(0)->nodeValue . '</h2>');
        $cuerpo = utf8_decode($this->dom->saveHTML($xpath->query("//*[@itemprop='articleBody']")->item(0)));

        return [
            'titulo' => $titulo,
            'bajada' => $bajada,
            'cuerpo' => $cuerpo
        ];
    }
}