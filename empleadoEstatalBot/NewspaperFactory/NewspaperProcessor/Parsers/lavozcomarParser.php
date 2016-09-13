<?php

namespace empleadoEstatalBot\NewspaperFactory\NewspaperProcessor\Parsers;

use empleadoEstatalBot\NewspaperFactory\NewspaperProcessor\NewspaperProcessor;
use empleadoEstatalBot\Config;

class lavozcomarParser extends NewspaperProcessor
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
        $html .= '<h1>' . $xpath->query("//*[contains(@class, 'Main')]")->item(0)->getElementsByTagName('h1')->item(0)->nodeValue . '</h1>';
        $html .= '<h2>' . $xpath->query("//*[contains(@class, 'Main')]")->item(0)->getElementsByTagName('h2')->item(0)->nextSibling->nodeValue . '</h2>';

        foreach ($xpath->query("//*[contains(@class, 'TextoNota')]")->item(0)->childNodes as $i) {
            if (trim($i->nodeValue) && strpos($i->nodeValue, 'Aparecen en esta nota') !== 0) {
                $html .= $this->dom->saveHTML($i);
            }
        }

        $html .= Config::$SIGNATURE;
        $html .= '</body></html>';

        return $html;
    }
}
