<?php

namespace empleadoEstatalBot\NewspaperFactory\NewspaperProcessor\Parsers;

use empleadoEstatalBot\NewspaperFactory\NewspaperProcessor\NewspaperProcessor;
use empleadoEstatalBot\Config;

class lapoliticaonlinecomParser extends NewspaperProcessor
{
    public function __construct()
    {
        parent::__construct();
    }

    public function parseText($text)
    {
        $this->dom->loadHTML('<?xml encoding="utf-8"?>' . $text);
        $xpath = new \DOMXPath($this->dom);

        $html = '<!DOCTYPE html><html><head><title></title></head><body>';
        $html .= '<h1>' . $xpath->query("//*[contains(@class, 'zleftbig')]/*[contains(@class, 'title')]")->item(0)->nodeValue . '</h1>';
        $html .= '<h2>' . $xpath->query("//*[contains(@class, 'description')]")->item(0)->nodeValue . '</h2>';

        foreach ($this->dom->getElementById('vsmcontent')->childNodes as $i) {
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
