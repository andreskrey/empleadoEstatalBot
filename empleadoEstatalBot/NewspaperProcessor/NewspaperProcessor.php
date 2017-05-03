<?php

namespace empleadoEstatalBot\NewspaperProcessor;

use andreskrey\Readability\HTMLParser;
use DOMDocument;
use empleadoEstatalBot\Config;
use Guzzle\Service\Client as GuzzleClient;
use \ForceUTF8\Encoding;

class NewspaperProcessor
{

    private $URLShorteners = [
        't.co',
        'goo.gl',
        'bit.ly',
    ];

    private $url;
    private $options;
    private $dom;
    private $redis;

    public function __construct($url, $options)
    {
        $this->url = $url;
        $this->options = $options;

        $this->redis = new \Predis\Client(Config::$REDIS_URL);
    }

    public function parseText($text)
    {
        /*
         * Perdoname, Oh Dios, por parsear HTML con regex.
         * Es culpa de Clarin y su espantoso JS.
         */
        if (strpos($text, 'CLARIN') !== false) {
            $text = preg_replace('/inline: {(.|\n)*?},/', '', $text);
        }
        /*
         * Amen
         */

        $readability = new HTMLParser(
            array_merge(
                $this->options,
                ['originalURL' => $this->url]
            )
        );

        try {
            $result = $readability->parse($text);
        } catch (\Error $e) {
            $result = false;
        }

        if ($result) {
            if ($result['image']) {
                $html = '<h1>[' . htmlspecialchars($result['title']) . '](' . $result['image'] . ')</h1>' . "<br/><br/>";
            } else {
                $html = '<h1>' . htmlspecialchars($result['title']) . '</h1>' . "<br/><br/>";
            }
            return $html . $result['html'];
        }

        return false;
    }

    public function parse($text)
    {
        $parsed = $this->parseText($text);

        if (!$parsed) {
            return false;
        }

        // Envolviendo en blockquote el asunto para triggerear la regla css que oculta el texto
        $parsed = '<blockquote>' . $parsed . '</blockquote>' . "\n";

        $signed = $this->signPost($parsed);
        $solved = $this->solveURLShorteners($signed);

        return $solved;
    }

    public function signPost($text)
    {
        return $text . "<br/><br/><br/>" . Config::$SIGNATURE;
    }

    public function getNewspaperText()
    {
        $client = new GuzzleClient();

        // For the gorras out there
        $client->setUserAgent('Mozilla/5.0 (Macintosh; Intel Mac OS X 10.11; rv:50.0) Gecko/20100101 Firefox/50.0');

        $text = $client->get($this->url)->send();
        $body = $text->getBody(true);

        // Por alguna razon a veces minutouno manda gzippeado y guzzle no lo descomprime
        // Los tres chars son los magic numbers de zip
        $isGZip = 0 === mb_strpos($body, "\x1f" . "\x8b" . "\x08");
        if ($isGZip) $body = gzdecode($body);

        // Algunos diarios mandan texto en UTF8 y Content Type declarado como otra cosa
        // y se rompe todo el texto. Force UTF8 soluciona esto
        $body = Encoding::toUTF8($body);

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

        // Cuerpo de la noticia sin la firma (sin tags html), sin los titulos
        $bodySize = mb_strlen($this->dom->textContent) - mb_strlen(strip_tags(Config::$SIGNATURE)) - $headerSize;

        // Bodysize contra headerSize por 2
        return ($bodySize <=> $headerSize * 2) > 0;
    }
}
