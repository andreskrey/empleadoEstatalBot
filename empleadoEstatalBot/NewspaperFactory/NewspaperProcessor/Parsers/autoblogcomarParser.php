<?php

namespace empleadoEstatalBot\NewspaperFactory\NewspaperProcessor\Parsers;

use empleadoEstatalBot\NewspaperFactory\NewspaperProcessor;
use empleadoEstatalBot\Config;

class autoblogcomarParser extends NewspaperProcessor
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

        foreach ($xpath->query("//*[contains(@class, 'entry-inner')]")->item(0)->childNodes as $i) {
            if ($i->nodeName == 'div') continue;
            if (trim($i->nodeValue)) {
                $html .= $this->dom->saveHTML($i);
            }
        }

        $html .= Config::$SIGNATURE;
        $html .= '</body></html>';

        return $html;
    }
}