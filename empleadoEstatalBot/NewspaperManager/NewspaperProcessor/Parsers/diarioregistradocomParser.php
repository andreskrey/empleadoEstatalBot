<?php

namespace empleadoEstatalBot\NewspaperManager\NewspaperProcessor\Parsers;

use empleadoEstatalBot\NewspaperManager\NewspaperProcessor;
use empleadoEstatalBot\Config;

class diarioregistradocomParser extends NewspaperProcessor
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
        $html .= '<h2>' . $xpath->query("//*[contains(@class, 'article-deck')]")->item(0)->nodeValue . '</h2>';

        foreach ($this->dom->getElementById('body')->childNodes as $i) {
            if (trim($i->nodeValue)
                && (strpos(trim($i->nodeValue), 'Nota relacionada') === false)) {
                $html .= $this->dom->saveHTML($i);
            }
        }

        $html .= Config::$SIGNATURE;
        $html .= '</body></html>';

        return $html;
    }
}
