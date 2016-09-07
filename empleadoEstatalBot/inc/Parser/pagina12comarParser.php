<?php

use empleadoEstatalBot\newspaperParser;

class pagina12comarParser extends newspaperParser
{
    public function __construct()
    {
        parent::__construct();
    }

    public function parseText($text)
    {
        $this->dom->loadHTML($text);
        $xpath = new DOMXPath($this->dom);

        $html = '<!DOCTYPE html><html><head><title></title></head><body>';
        $html .= '<h1>' . $this->dom->getElementsByTagName('h2')->item(0)->nodeValue . '</h1>';
        $html .= '<h2>' . $xpath->query("//*[contains(@class, 'intro')]")->item(0)->nodeValue . '</h2>';

        $html .= $this->dom->saveHTML($this->dom->getElementById('cuerpo'));

        $html .= empleadoEstatalConfig::$SIGNATURE;
        $html .= '</body></html>';

        return $html;
    }
}
