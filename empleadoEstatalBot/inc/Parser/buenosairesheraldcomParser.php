<?php

use empleadoEstatalBot\newspaperParser;

class buenosairesheraldcomParser extends newspaperParser
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

        $html .= empleadoEstatalConfig::$SIGNATURE;
        $html .= '</body></html>';

        return $html;
    }
}
