<?php

namespace empleadoEstatalBot\NewspaperProcessor;

use empleadoEstatalBot\empleadoEstatal;
use empleadoEstatalBot\Post;
use andreskrey\Readability\HTMLParser;
use GuzzleHttp\Client as HttpClient;
use DOMDocument;
use \ForceUTF8\Encoding;
use League\HTMLToMarkdown\HtmlConverter;

class NewspaperProcessor
{
    public function __construct($config)
    {
        $this->config = $config;
    }

    public function getNewspaperText()
    {
        /**
         * @var $client HttpClient
         */
        $client = new HttpClient([
            'headers' => [
                // Lets pretend we are the most average user
                'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/58.0.3029.110 Safari/537.36'
            ]]);


        foreach (Post::where(['status' => empleadoEstatal::THING_TO_FETCH, ['tries', '<', 3]])->get() as $thing) {
            try {
                /**
                 * @var $thing Post
                 */
                $thing->tries++;

                $html = (string)$client->request('GET', $thing->url)->getBody();

                // Por alguna razon a veces minutouno manda gzippeado y guzzle no lo descomprime
                // Los tres chars son los magic numbers de zip
                if (0 === mb_strpos($html, "\x1f" . "\x8b" . "\x08")) {
                    $html = gzdecode($html);
                }


                $html = $this->parseHTML($html, $thing->url);

                // Discard failed parsings
                if ($html === false) {
                    throw new \ErrorException('Readability');
                }

                $html = $this->sanitizeHTML($html);
                $html = $this->signPost($html);
                $markdown = $this->buildMarkdown($html);

                $thing->markdown = $markdown;
                $thing->status = empleadoEstatal::THING_FETCHED;
                $thing->tries = 0;

            } catch (\ErrorException $e) {
                empleadoEstatal::$log->addInfo(sprintf('FetchWorker: Failed to parse in Readability. Thing: %s. URL: %s', $thing->thing, $thing->url));
                $thing->info = 'Failed to parse in Readability';
                $thing->status = empleadoEstatal::THING_REJECTED;
            } catch (\Exception $e) {
                empleadoEstatal::$log->addCritical(sprintf('FetchWorker: Failed to get newspaper (try no %s): %s. URL: %s', $thing->tries, $e->getMessage(), $thing->url));
                $thing->info = substr($e->getMessage(), 0, 254);
            } catch (\Error $e) {
                empleadoEstatal::$log->addCritical(sprintf('FetchWorker: General Error (?) (try no %s): %s. URL: %s', $thing->tries, $e->getMessage(), $thing->url));
                $thing->info = substr($e->getMessage(), 0, 254);
            } finally {
                $thing->save();
            }
        }
    }

    private function parseHTML($html, $url)
    {
        /*
         * Perdoname, Oh Dios, por parsear HTML con regex.
         * Es culpa de Clarin y su espantoso JS.
         */
        if (strpos($html, 'CLARIN') !== false) {
            $html = preg_replace('/inline: {(.|\n)*?},/', '', $html);
        }
        /*
         * Amen
         */

        $readability = new HTMLParser(['originalURL' => $url]);
        $result = $readability->parse($html);

        if ($result) {
            if ($result['image']) {
                $image = '<h1>[' . htmlspecialchars($result['title']) . '](' . $result['image'] . ')</h1>' . "<br/><br/>";
            } else {
                $image = '<h1>' . htmlspecialchars($result['title']) . '</h1>' . "<br/><br/>";
            }
            return $image . $result['html'];
        }

        return false;
    }

    private function signPost($html)
    {
        return $html . "<br/><br/><br/>" . $this->config['signature'];
    }

    private function sanitizeHTML($html)
    {
        $html = $this->solveURLShorteners($html);

        // Eliminar los mails del texto asi reddit no se pone la gorra
        $html = $test = preg_replace_callback('/[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,4}\b/i', function ($match) {
            return str_replace('@', ' at ', $match[0]);
        }, $html);

        // Envolviendo en blockquote el asunto para triggerear la regla css que oculta el texto
        $html = '<blockquote>' . $html . '</blockquote>' . "\n";


        // Algunos diarios mandan texto en UTF8 y Content Type declarado como otra cosa
        // y se rompe todo el texto. Force UTF8 soluciona esto
        $html = Encoding::toUTF8($html);

        return $html;
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
            if (in_array(parse_url($link, PHP_URL_HOST), $this->config['url_shorteners'])) {
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

    private function buildMarkdown($html)
    {
        $converter = new HtmlConverter([
            'strip_tags' => true,
            'header_style' => 'atx'
        ]);

        $markdown = $converter->convert($html);

        // Agregar la marca de markdown para hacer el hover de css

        $markdown = "#####&#009;\n\n######&#009;\n\n####&#009;\n\n" . $markdown;

        return $markdown;
    }
}
