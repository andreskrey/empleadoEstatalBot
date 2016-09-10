<?php

namespace empleadoEstatalBot\NewspaperFactory\NewspaperProcessor\Parsers;

use empleadoEstatalBot\NewspaperFactory\NewspaperProcessor\NewspaperProcessor;
use empleadoEstatalBot\Config;

class buenosairesheraldcomParser extends NewspaperProcessor
{
    public function __construct()
    {
        parent::__construct();
    }

    public function parseText($text)
    {
        $this->dom->loadHTML($text);

        $html = '<!DOCTYPE html><html><head><title></title></head><body>';
        $html .= '<h1>' . $this->dom->getElementsByTagName('h1')->item(0)->nodeValue . '</h1>';

        foreach ($this->dom->getElementById('nota_despliegue')->childNodes as $pos => $i) {
            if ($pos < 8) continue;
            if (trim($i->nodeValue)) {
                $html .= '<p>' . $this->dom->saveHTML($i) . '<p>';
            }
        }

        $html .= Config::$SIGNATURE;
        $html .= '</body></html>';

        return $html;
    }
}
