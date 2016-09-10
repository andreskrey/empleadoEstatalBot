<?php

namespace empleadoEstatalBot\NewspaperFactory\NewspaperProcessor\Parsers;

use empleadoEstatalBot\NewspaperFactory\NewspaperProcessor;
use empleadoEstatalBot\Config;

class iecoclarincomParser extends NewspaperProcessor
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

        $html .= '<h2>' . $xpath->query("//*[contains(@class, 'int-nota-title')]")->item(0)->childNodes->item(10)->nodeValue . '</h2>';

        foreach ($xpath->query('//*[@class="nota"]')->item(0)->childNodes as $i) {
            // Skipear los links de "Mira tambien".
            if (trim($i->nodeValue) && mb_substr($i->nodeValue, 0, 12) != 'Mirá también') {
                $html .= $this->dom->saveHTML($i);
            }
        }

        $html .= Config::$SIGNATURE;
        $html .= '</body></html>';

        return $html;
    }
}
