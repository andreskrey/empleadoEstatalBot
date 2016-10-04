<?php

namespace empleadoEstatalBot\NewspaperFactory\NewspaperProcessor\Parsers;

use empleadoEstatalBot\Config;
use empleadoEstatalBot\NewspaperFactory\NewspaperProcessor\NewspaperProcessor;

class infobaecomParser extends NewspaperProcessor
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
        // Hay que pasar por xpath en vez de agarrar el h1 por que infobae a veces usa el h1 para noticias en el header,
        // antes del titulo de la noticia que esta parseando
        $html .= '<h1>' . $xpath->query("//*[contains(@class, 'entry-title')]")->item(0)->nodeValue . '</h1>';
        $html .= '<h2>' . $xpath->query("//*[contains(@class, 'preview')]")->item(0)->nodeValue . '</h2>';

        $html .= $this->dom->saveHTML($xpath->query("//*[contains(@class, 'cuerposmart')]")->item(0)->getElementsByTagName('div')->item(0));

        $html .= Config::$SIGNATURE;
        $html .= '</body></html>';

        return $html;
    }
}
