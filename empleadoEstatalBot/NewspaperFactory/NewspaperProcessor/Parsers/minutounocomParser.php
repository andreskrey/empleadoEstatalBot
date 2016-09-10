<?php

namespace empleadoEstatalBot\NewspaperFactory\NewspaperProcessor\Parsers;

use empleadoEstatalBot\NewspaperFactory\NewspaperProcessor;
use empleadoEstatalBot\Config;

class minutounocomParser extends NewspaperProcessor
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
        $html .= '<h2>' . $xpath->query("//*[contains(@class, 'main-quote')]")->item(0)->nodeValue . '</h2>';

        foreach ($xpath->query("//*[contains(@class, 'article-content')]")->item(0)->childNodes as $i) {
            if (trim($i->nodeValue)
                && $i->nodeName != 'div'
            ) {
                $html .= $this->dom->saveHTML($i);
            } else {
                if ($i->nodeName == 'br') $html .= '<br>';
            }
        }

        $html .= Config::$SIGNATURE;
        $html .= '</body></html>';

        return $html;
    }
}
