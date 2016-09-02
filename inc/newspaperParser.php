<?php

class newspaperParser
{
    protected $newspaper;

    protected $dom;

    private $URLShorteners = [
        't.co',
        'goo.gl',
    ];

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

        $parsed = $parser->parseText($text);
        $solved = $this->solveURLShorteners($parsed);

        return $solved;
    }

    public function solveURLShorteners($html)
    {
        $this->dom = new DOMDocument('1.0', 'utf-8');
        // Hack horrible para evitar que DOMDocument se mande cagadas con UTF8
        $this->dom->loadHTML('<?xml encoding="utf-8"?>' . $html);
        $this->dom->encoding = 'utf-8';

        $links = $this->dom->getElementsByTagName('a');

        if (!count($links)) return $html;

        foreach ($links as $i) {

            $link = $i->getAttribute("href");
            if (in_array(parse_url($link, PHP_URL_HOST), $this->URLShorteners)) {
                $headers = get_headers($link);

                $headers = array_filter($headers, function ($key) {
                    return (strpos(strtolower($key), 'location:') !== false && strlen($key) > 10) ? true : false;
                });

                $finalURL = substr(end($headers), 10);
                $i->setAttribute('href', $finalURL);
            }
        }

        // Hack horrible para sacar el hack horrible anterior
        return str_replace('<?xml encoding="utf-8"?>', '', $this->dom->saveHTML());
    }
}

interface NewspaperInterface
{
    public function parseText($text);
}

class lanacioncomarParser extends newspaperParser
{
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

        $html .= empleadoEstatalConfig::$SIGNATURE;
        $html .= '</body></html>';

        return $html;
    }
}

class clarincomParser extends newspaperParser
{
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
            // Skipear los links de "Mira tambien" y otras bostas
            if (trim($i->nodeValue)
                && mb_substr($i->nodeValue, 0, 12) != 'Mirá también'
                && mb_substr($i->nodeValue, 0, 11) != 'También leé'
                && $i->nodeName != 'script'
                && $i->nodeName != 'div'
            ) {
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
        // Hay que pasar por xpath en vez de agarrar el h1 por que infobae a veces usa el h1 para noticias en el header,
        // antes del titulo de la noticia que esta parseando
        $html .= '<h1>' . $xpath->query("//*[contains(@class, 'article-header')]")->item(0)->getElementsByTagName('h1')->item(0)->nodeValue . '</h1>';
        $html .= '<h2>' . $xpath->query("//*[contains(@class, 'subheadline')]")->item(0)->nodeValue . '</h2>';

        foreach ($this->dom->getElementById('article-content')->childNodes as $i) {
            if (trim($i->nodeValue)) {
                if (strpos('LEA MÁS:', trim($i->nodeValue)) === 0) break;
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

class minutounocomParser extends newspaperParser
{
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
        $html .= '<h2>' . $xpath->query("//*[contains(@class, 'main-quote')]")->item(0)->nodeValue . '</h2>';

        $html .= $this->dom->saveHTML($xpath->query("//*[contains(@class, 'article-content')]")->item(0));

        $html .= empleadoEstatalConfig::$SIGNATURE;
        $html .= '</body></html>';

        return $html;
    }
}

class cronicacomarParser extends newspaperParser
{
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
        $html .= '<h1>' . $xpath->query("//*[contains(@class, 'article-title')]")->item(0)->nodeValue . '</h1>';
        $html .= '<h2>' . $xpath->query("//*[contains(@class, 'article-lead')]")->item(0)->nextSibling->nodeValue . '</h2>';

        $html .= $this->dom->saveHTML($xpath->query("//*[contains(@class, 'article-text')]")->item(0));

        $html .= empleadoEstatalConfig::$SIGNATURE;
        $html .= '</body></html>';

        return $html;
    }
}

class autoblogcomarParser extends newspaperParser
{
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

        foreach ($xpath->query("//*[contains(@class, 'entry-inner')]")->item(0)->childNodes as $i) {
            if ($i->nodeName == 'div') continue;
            if (trim($i->nodeValue)) {
                $html .= $this->dom->saveHTML($i);
            }
        }

        $html .= empleadoEstatalConfig::$SIGNATURE;
        $html .= '</body></html>';

        return $html;
    }
}

class perfilcomParser extends newspaperParser
{
    public function __construct()
    {
        $this->dom = new DOMDocument();
        libxml_use_internal_errors(true);
    }

    public function parseText($text)
    {
        $this->dom->loadHTML('<?xml encoding="utf-8"?>' . $text);
        $this->dom->encoding = 'utf-8';
        $xpath = new DOMXPath($this->dom);

        $html = '<!DOCTYPE html><html><head><title></title></head><body>';
        $html .= '<h1>' . $xpath->query("//*[contains(@class, 'articulob-title')]")->item(0)->nodeValue . '</h1>';
        $html .= '<h2>' . $xpath->query("//*[contains(@class, 'articulob-subtitle')]")->item(0)->nodeValue . '</h2>';

        foreach ($xpath->query("//*[contains(@class, 'textbody')]")->item(0)->childNodes as $i) {
            if (trim($i->nodeValue)) {
                $html .= '<p>' . $this->dom->saveHTML($i) . '</p>';
            }
        }

        $html .= empleadoEstatalConfig::$SIGNATURE;
        $html .= '</body></html>';

        return $html;
    }
}


class ambitocomParser extends newspaperParser
{
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
