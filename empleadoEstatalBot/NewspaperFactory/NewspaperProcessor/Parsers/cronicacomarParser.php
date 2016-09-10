<?php

namespace empleadoEstatalBot\NewspaperFactory\NewspaperProcessor\Parsers;

use empleadoEstatalBot\NewspaperFactory\NewspaperProcessor;
use empleadoEstatalBot\Config;

class cronicacomarParser extends NewspaperProcessor
{
    public function __construct()
    {
        parent::__construct();
    }

    public function parseText($text)
    {
        $this->dom->loadHTML($text);
        $xpath = new \DOMXPath($this->dom);

        $html = '<!DOCTYPE html><html><head><title></title></head><body>';
        $html .= '<h1>' . $xpath->query("//*[contains(@class, 'article-title')]")->item(0)->nodeValue . '</h1>';
        $html .= '<h2>' . $xpath->query("//*[contains(@class, 'article-lead')]")->item(0)->nextSibling->nodeValue . '</h2>';

        $html .= $this->dom->saveHTML($xpath->query("//*[contains(@class, 'article-text')]")->item(0));

        $html .= Config::$SIGNATURE;
        $html .= '</body></html>';

        return $html;
    }
}
