<?php

namespace empleadoEstatalBot\NewspaperProcessor\Parsers;

use empleadoEstatalBot\NewspaperProcessor;
use empleadoEstatalBot\Config;

class telamcomarParser extends NewspaperProcessor
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
        $html .= '<h2>' . $xpath->query("//*[contains(@class, 'copete')]")->item(0)->nodeValue . '</h2>';


        foreach ($xpath->query("//*[contains(@class, 'editable-content')]")->item(0)->childNodes as $i) {
            if (trim($i->nodeValue)) {
                $html .= '<p>' . $this->dom->saveHTML($i) . '<p>';
            }
        }

        $html .= Config::$SIGNATURE;
        $html .= '</body></html>';

        return $html;
    }
}
