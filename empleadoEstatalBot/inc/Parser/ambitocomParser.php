<?php

use empleadoEstatalBot\newspaperParser;

class ambitocomParser extends newspaperParser
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
        $html .= '<h1>' . $this->dom->getElementsByTagName('h2')->item(0)->nodeValue . '</h1>';
        // H6!!! WTF!
        $html .= '<h2>' . $this->dom->getElementsByTagName('h6')->item(2)->nodeValue . '</h2>';

        // Clusterfuck para traer el texto de la noticia, por que no viene con el articulo, sino que hay que llamarlo especificamente
        parse_str(parse_url($xpath->query("//*[@data-target='#myLoginComentario']")->item(0)->getAttribute('href'), PHP_URL_QUERY), $id);
        $body = file_get_contents('http://data.ambito.com/diario/cuerpo_noticia.asp?id=' . $id['id']);

        $html .= $body;

        $html .= empleadoEstatalConfig::$SIGNATURE;
        $html .= '</body></html>';

        return $html;
    }
}
