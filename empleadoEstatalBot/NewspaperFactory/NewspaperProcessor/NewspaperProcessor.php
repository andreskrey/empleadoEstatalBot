<?php

namespace empleadoEstatalBot\NewspaperFactory\NewspaperProcessor;

use DOMDocument;
use empleadoEstatalBot\Config;
use Guzzle\Service\Client as GuzzleClient;

abstract class NewspaperProcessor
{
    static public $newspapers = [
        'lanacion.com.ar',
        'clarin.com',
        'ieco.clarin.com',
        'infobae.com',
        'cronista.com',
        'telam.com.ar',
        'buenosairesherald.com',
        'pagina12.com.ar',
        'minutouno.com',
        'autoblog.com.ar',
        'perfil.com',
        'cronica.com.ar',
        //'ambito.com',
        'diarioregistrado.com',
        'lavoz.com.ar',
        'mdzol.com',
        'tn.com.ar',
        'infonews.com',
        'lapoliticaonline.com',
    ];

    protected $dom;

    private $URLShorteners = [
        't.co',
        'goo.gl',
    ];

    abstract public function parseText($text);

    public function __construct()
    {
        $this->dom = new DOMDocument();
        libxml_use_internal_errors(true);
    }

    public function parse($text)
    {
        $parsed = $this->parseText($text);
        $solved = $this->solveURLShorteners($parsed);
        $kicified = $this->checkForKicilove($solved);

        return $kicified;
    }

    public function checkForKicilove($text)
    {
        $politicos = [
            'kicillof' => [
                '<p>- - - - - -</p>',
                '<p>En esta nota se menciona a Kicillof y por reglas de /r/argentina es obligatorio recordar este video: <b><a href="https://www.youtube.com/watch?v=htCUanVT4Dg">Prat-Gay humilló a Axel Kicillof</a></b>.</p>'
            ],
            'ottavis' => [
                '<p>- - - - - -</p>',
                '<p>Ottavis, <a href="https://i.redd.it/y1y0s803tuux.jpg">orgullo nacional</a>.</p>'
            ]
        ];
        foreach ($politicos as $politico => $addon) {
            if (stripos($text, $politico) !== false) {
                $this->dom = new DOMDocument('1.0', 'utf-8');
                $this->dom->loadHTML('<?xml encoding="utf-8"?>' . $text);
                $this->dom->encoding = 'utf-8';

                $frag = $this->dom->createDocumentFragment();
                foreach ($addon as $line) {
                    $frag->appendXML($line);
                }

                $firma = $this->dom->getElementById('firma');
                $firma->appendChild($frag);

                return str_replace('<?xml encoding="utf-8"?>', '', $this->dom->saveHTML());
            }
        }

        return $text;
    }

    public function getNewspaperText($url)
    {
        $client = new GuzzleClient();

        // For the gorras out there
        $client->setUserAgent('Mozilla/5.0 (Macintosh; Intel Mac OS X 10.11; rv:50.0) Gecko/20100101 Firefox/50.0');

        $text = $client->get($url)->send();
        $body = $text->getBody(true);

        // Por alguna razon a veces minutouno manda gzippeado y guzzle no lo descomprime
        // Los tres chars son los magic numbers de zip
        $isGZip = 0 === mb_strpos($body, "\x1f" . "\x8b" . "\x08");
        if ($isGZip) $body = gzdecode($body);

        return $body;
    }

    private function solveURLShorteners($html)
    {
        $this->dom = new DOMDocument('1.0', 'utf-8');
        // Hack horrible para evitar que DOMDocument se mande cagadas con UTF8
        $this->dom->loadHTML('<?xml encoding="utf-8"?>' . $html);
        $this->dom->encoding = 'utf-8';

        $links = $this->dom->getElementsByTagName('a');

        foreach ($links as $i) {

            $link = $i->getAttribute("href");
            if (in_array(parse_url($link, PHP_URL_HOST), $this->URLShorteners)) {
                $headers = get_headers($link);

                $headers = array_filter($headers, function ($key) {
                    return (strpos(strtolower($key), 'location:') !== false && strlen($key) > 10) ? true : false;
                });

                $finalURL = substr(end($headers), 10);
                $i->setAttribute('href', $finalURL);
                $i->nodeValue = $finalURL;
            }
        }

        // Hack horrible para sacar el hack horrible anterior
        return str_replace('<?xml encoding="utf-8"?>', '', $this->dom->saveHTML());
    }

    /*
     * La idea es solo publicar notas que tengan una buena cantidad de texto en el cuerpo.
     * Esto se decide multiplicando por dos la cantidad de caracteres que componen el titulo y la bajada
     * Si este numero es mayor a la cantidad de caracteres el cuerpo, no se publica el post.
     */
    public function checkLength($html)
    {
        $this->dom = new DOMDocument('1.0', 'utf-8');
        $this->dom->loadHTML('<?xml encoding="utf-8"?>' . $html);
        $this->dom->encoding = 'utf-8';

        $headerSize = 0;
        $headerSize += mb_strlen($this->dom->getElementsByTagName('h1')->item(0)->nodeValue);
        $headerSize += mb_strlen($this->dom->getElementsByTagName('h2')->item(0)->nodeValue);

        // Cuerpo de la noticia sin la firma (sin tags html), sin los titulos
        $bodySize = mb_strlen($this->dom->textContent) - mb_strlen(strip_tags(Config::$SIGNATURE)) - $headerSize;

        // Bodysize contra headerSize por 2
        return ($bodySize <=> $headerSize * 2) > 0;
    }
}
