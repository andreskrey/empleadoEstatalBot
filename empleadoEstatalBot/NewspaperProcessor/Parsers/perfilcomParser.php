<?php

namespace empleadoEstatalBot\NewspaperProcessor\Parsers;

use empleadoEstatalBot\NewspaperProcessor;
use empleadoEstatalBot\Config;

class perfilcomParser extends NewspaperProcessor
{
    public function __construct()
    {
        parent::__construct();
    }

    public function parseText($text)
    {
        $this->dom->loadHTML('<?xml encoding="utf-8"?>' . $text);
        $this->dom->encoding = 'utf-8';
        $xpath = new \DOMXPath($this->dom);

        $html = '<!DOCTYPE html><html><head><title></title></head><body>';
        $html .= '<h1>' . $xpath->query("//*[contains(@class, 'articulob-title')]")->item(0)->nodeValue . '</h1>';
        $html .= '<h2>' . $xpath->query("//*[contains(@class, 'articulob-subtitle')]")->item(0)->nodeValue . '</h2>';

        foreach ($xpath->query("//*[contains(@class, 'textbody')]")->item(0)->childNodes as $i) {
            if (trim($i->nodeValue)) {
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
