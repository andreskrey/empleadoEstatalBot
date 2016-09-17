<?php

namespace empleadoEstatalBot\NewspaperFactory\NewspaperProcessor\Parsers;

use empleadoEstatalBot\NewspaperFactory\NewspaperProcessor\NewspaperProcessor;
use empleadoEstatalBot\Config;

class cronistacomParser extends NewspaperProcessor
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
        $html .= '<h1>' . $xpath->query("//*[contains(@class, 'header-bottom')]")->item(0)->getElementsByTagName('h1')->item(0)->nodeValue . '</h1>';
        $html .= '<h2>' . $xpath->query("//*[contains(@class, 'header-bottom')]")->item(0)->getElementsByTagName('h2')->item(0)->nodeValue . '</h2>';

        $html .= $this->dom->saveHTML($xpath->query("//*[contains(@class, 'content-txt')]")->item(0));

        $html .= Config::$SIGNATURE;
        $html .= '</body></html>';

        return $html;
    }
}
