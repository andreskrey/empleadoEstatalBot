<?php

use empleadoEstatalBot\newspaperParser;

class infobaecomParser extends newspaperParser
{
    public function __construct()
    {
        parent::__construct();
    }

    public function parseText($text)
    {
        $this->dom->loadHTML($text);
        $xpath = new DOMXPath($this->dom);

        $html = '<!DOCTYPE html><html><head><title></title></head><body>';
        // Hay que pasar por xpath en vez de agarrar el h1 por que infobae a veces usa el h1 para noticias en el header,
        // antes del titulo de la noticia que esta parseando
        $html .= '<h1>' . $xpath->query("//*[contains(@class, 'article-header')]")->item(0)->getElementsByTagName('h1')->item(0)->nodeValue . '</h1>';
        $html .= '<h2>' . $xpath->query("//*[contains(@class, 'subheadline')]")->item(0)->nodeValue . '</h2>';

        foreach ($this->dom->getElementById('article-content')->childNodes as $i) {
            if (trim($i->nodeValue)) {
                if (mb_strpos('lea más:', mb_strtolower(trim($i->nodeValue))) === 0) break;
                $html .= $this->dom->saveHTML($i);
            }
        }

        $html .= empleadoEstatalConfig::$SIGNATURE;
        $html .= '</body></html>';

        return $html;
    }
}
