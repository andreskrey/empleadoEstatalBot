<?php

namespace empleadoEstatalBot\NewspaperFactory\NewspaperProcessor\Parsers;

use empleadoEstatalBot\NewspaperFactory\NewspaperProcessor\NewspaperProcessor;
use empleadoEstatalBot\Config;

class tncomarParser extends NewspaperProcessor
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
        $html .= '<h2>' . $this->dom->getElementsByTagName('h2')->item(0)->nextSibling->nodeValue . '</h2>';

        foreach ($xpath->query("//*[contains(@class, 'entry-content')]")->item(0)->childNodes as $i) {
            if (isset($i->tagName) && $i->tagName == 'div') break;
            if (trim($i->nodeValue) && mb_strpos($i->nodeValue, 'Leé también:') === false) {
                $html .= $this->dom->saveHTML($i);
            }
        }

        $html .= Config::$SIGNATURE;
        $html .= '</body></html>';

        return $html;
    }
}
