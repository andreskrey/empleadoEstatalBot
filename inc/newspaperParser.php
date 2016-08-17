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

        // La mayoria de los html de lanacion no validan y sin la siguiente linea DOMDocument tira mil warnings
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

        $html .= empleadoEstatalConfig::$SIGNATURE;
        $html .= '</body></html>';

        return $html;
    }
}

class clarincomParser extends newspaperParser
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
        $html .= '<h1>' . $this->dom->getElementsByTagName('h1')->item(0)->nodeValue . '</h1>';

        // El nodo 6 tiene la bajada. Esta linea es para cagadas pero bue. Eventualmente se va a romper
        $html .= '<h2>' . $xpath->query("//*[contains(@class, 'int-nota-title')]")->item(0)->childNodes->item(6)->nodeValue . '</h2>';

        foreach ($xpath->query('//*[@class="nota"]')->item(0)->childNodes as $i) {
            // Skipear los links de "Mira tambien".
            if (mb_substr($i->nodeValue, 0, 12) != 'Mirá también') {
                $html .= $this->dom->saveHTML($i);
            }
        }

        $html .= empleadoEstatalConfig::$SIGNATURE;
        $html .= '</body></html>';

        return $html;
    }
}

class iecoclarincomParser extends newspaperParser
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
        $html .= '<h1>' . $this->dom->getElementsByTagName('h1')->item(0)->nodeValue . '</h1>';

        $html .= '<h2>' . $xpath->query("//*[contains(@class, 'int-nota-title')]")->item(0)->childNodes->item(10)->nodeValue . '</h2>';

        foreach ($xpath->query('//*[@class="nota"]')->item(0)->childNodes as $i) {
            // Skipear los links de "Mira tambien".
            if (trim($i->nodeValue) && mb_substr($i->nodeValue, 0, 12) != 'Mirá también') {
                $html .= $this->dom->saveHTML($i);
            }
        }

        $html .= empleadoEstatalConfig::$SIGNATURE;
        $html .= '</body></html>';

        return $html;
    }
}

class infobaecomParser extends newspaperParser
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
        $html .= '<h1>' . $this->dom->getElementsByTagName('h1')->item(0)->nodeValue . '</h1>';
        $html .= '<h2>' . $xpath->query("//*[contains(@class, 'subheadline')]")->item(0)->nodeValue . '</h2>';

        foreach ($this->dom->getElementById('article-content')->childNodes as $i) {
            if (trim($i->nodeValue)) {
                $html .= $this->dom->saveHTML($i);
            }
        }

        $html .= empleadoEstatalConfig::$SIGNATURE;
        $html .= '</body></html>';

        return $html;
    }
}

class cronistacomParser extends newspaperParser
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
        $html .= '<h1>' . $xpath->query("//*[@itemprop='name']")->item(0)->nodeValue . '</h1>';
        $html .= '<h2>' . $xpath->query("//*[@itemprop='description']")->item(0)->nodeValue . '</h2>';

        $html .= $this->dom->saveHTML($xpath->query("//*[@itemprop='articleBody']")->item(0));

        $html .= empleadoEstatalConfig::$SIGNATURE;
        $html .= '</body></html>';

        return $html;
    }
}

class telamcomarParser extends newspaperParser
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
        $html .= '<h1>' . $this->dom->getElementsByTagName('h2')->item(0)->nodeValue . '</h1>';
        $html .= '<h2>' . $xpath->query("//*[contains(@class, 'copete')]")->item(0)->nodeValue . '</h2>';


        foreach ($xpath->query("//*[contains(@class, 'editable-content')]")->item(0)->childNodes as $i) {
            if (trim($i->nodeValue)) {
                $html .= '<p>' . $this->dom->saveHTML($i) . '<p>';
            }
        }

        $html .= empleadoEstatalConfig::$SIGNATURE;
        $html .= '</body></html>';

        return $html;
    }
}

class buenosairesheraldcomParser extends newspaperParser
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

class pagina12comarParser extends newspaperParser
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
        $html .= '<h1>' . $this->dom->getElementsByTagName('h2')->item(0)->nodeValue . '</h1>';
        $html .= '<h2>' . $xpath->query("//*[contains(@class, 'intro')]")->item(0)->nodeValue . '</h2>';

        $html .= $this->dom->saveHTML($this->dom->getElementById('cuerpo'));

        $html .= empleadoEstatalConfig::$SIGNATURE;
        $html .= '</body></html>';

        return $html;
    }
}
