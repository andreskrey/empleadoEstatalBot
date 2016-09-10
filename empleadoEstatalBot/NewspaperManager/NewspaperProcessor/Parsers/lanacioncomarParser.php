<?php

namespace empleadoEstatalBot\NewspaperManager\NewspaperProcessor\Parsers;

use empleadoEstatalBot\NewspaperManager\NewspaperProcessor;
use empleadoEstatalBot\Config;

class lanacioncomarParser extends NewspaperProcessor
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
        $html .= '<h1>' . $xpath->query("//*[@itemprop='headline']")->item(0)->nodeValue . '</h1>';
        $html .= '<h2>' . $xpath->query("//*[@itemprop='description']")->item(0)->nodeValue . '</h2>';

        $cuerpo = $this->dom->getElementById('cuerpo');

        foreach ($cuerpo->getElementsByTagName('a') as $i) {
            $link = $i->getAttribute("href");
            if (!parse_url($link, PHP_URL_HOST)) {
                $i->setAttribute('href', 'http://www.lanacion.com.ar' . $link);
            }
        }

        foreach ($cuerpo->childNodes as $i) {
            // section define la parte de tags de la nota, que significa que el texto del cuerpo se acabo
            if ($i->tagName == 'section') break;
            /*
             * No interesan los divs, por lo general estan vacios o incluyen la parte de "del editor, que significa"
             * Tampoco los figure, que son fotos con bajada.
             * El strpos de breadcrum es para no sacar los links que aparecen abajo como breadcrum
             */
            if ($i->tagName == 'div'
                || $i->tagName == 'figure'
                || $i->tagName == 'aside'
                || (isset($i->attributes->item(2)->value) && strpos($i->attributes->item(2)->value, 'breadcrum') !== false
                    || mb_strpos($i->nodeValue, 'Break: más noticias') === 0
                    || strpos($i->nodeValue, 'Break, noticias ') === 0)
            ) continue;

            // Al principio de las notas aparece un 0 con rn por alguna razon, eso se skipea
            if (trim($i->nodeValue)) {
                $html .= $this->dom->saveHTML($i);
            }
        }

        $html .= Config::$SIGNATURE;
        $html .= '</body></html>';

        return $html;
    }
}
