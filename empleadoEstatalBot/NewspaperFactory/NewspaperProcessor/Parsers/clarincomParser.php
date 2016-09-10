<?php

namespace empleadoEstatalBot\NewspaperFactory\NewspaperProcessor\Parsers;

use empleadoEstatalBot\NewspaperFactory\NewspaperProcessor;
use empleadoEstatalBot\Config;

class clarincomParser extends NewspaperProcessor
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
        $html .= '<h1>' . $this->dom->getElementsByTagName('h1')->item(0)->nodeValue . '</h1>';

        // El nodo 6 tiene la bajada. Esta linea es para cagadas pero bue. Eventualmente se va a romper
        $html .= '<h2>' . $xpath->query("//*[contains(@class, 'int-nota-title')]")->item(0)->childNodes->item(6)->nodeValue . '</h2>';

        foreach ($xpath->query('//*[@class="nota"]')->item(0)->childNodes as $i) {
            // Skipear los links de "Mira tambien" y otras bostas
            if (trim($i->nodeValue)
                && mb_substr($i->nodeValue, 0, 12) != 'Mirá también'
                && mb_substr($i->nodeValue, 0, 11) != 'También leé'
                && $i->nodeName != 'script'
                && $i->nodeName != 'div'
            ) {
                $html .= $this->dom->saveHTML($i);
            }
        }

        $html .= Config::$SIGNATURE;
        $html .= '</body></html>';

        return $html;
    }
}
