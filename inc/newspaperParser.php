<?php

class newspaperParser
{
    protected $newspaper;

    public function __construct($adapter)
    {
        if (class_exists($adapter . 'Parser')) {
            $this->newspaper = $adapter . 'Parser';
        } else {
            throw new BadFunctionCallException;
        }
    }

    public function parse($text)
    {
        $parser = new $this->newspaper();
        return $parser->parseText($text);
    }
}

interface NewspaperInterface
{
    public function parseText($text);
}

class lanacioncomarParser extends newspaperParser
{
    public $dom;

    public function __construct()
    {
        $this->dom = new DOMDocument();
        libxml_use_internal_errors(true);
    }

    public function parseText($text)
    {
        $this->dom->loadHTML($text);
        $xpath = new DOMXPath($this->dom);

        $html = '<!DOCTYPE html><html><head><title></title></head><body>';
        $html .= '<h1>' . $xpath->query("//*[@itemprop='headline']")->item(0)->nodeValue . '</h1>';
        $html .= '<h2>' . $xpath->query("//*[@itemprop='description']")->item(0)->nodeValue . '</h2>';

        $cuerpo = $this->dom->getElementById('cuerpo');
        foreach ($cuerpo->childNodes as $i) {
            // section define la parte de tags de la nota, que significa que el texto del cuerpo se acabo
            if ($i->tagName == 'section') break;
            // No interesan los divs, por lo general estan vacios o incluyen la parte de "del editor, que significa"
            if ($i->tagName == 'div') continue;

            // Al principio de las notas aparece un 0 con rn por alguna razon, eso se skipea
            if ($i->nodeValue && $i->nodeValue != "0\r\n          ") {
                $html .= $this->dom->saveHTML($i);
            }
        }
        $html .= '</body></html>';

        return $html;
    }
}