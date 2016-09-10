<?php

namespace empleadoEstatalBot\NewspaperManager\NewspaperProcessor\Parsers;

use empleadoEstatalBot\NewspaperManager\NewspaperProcessor;
use empleadoEstatalBot\Config;

class pagina12comarParser extends NewspaperProcessor
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
        $html .= '<h1>' . $this->dom->getElementsByTagName('h2')->item(0)->nodeValue . '</h1>';
        $html .= '<h2>' . $xpath->query("//*[contains(@class, 'intro')]")->item(0)->nodeValue . '</h2>';

        $html .= $this->dom->saveHTML($this->dom->getElementById('cuerpo'));

        $html .= Config::$SIGNATURE;
        $html .= '</body></html>';

        return $html;
    }
}
