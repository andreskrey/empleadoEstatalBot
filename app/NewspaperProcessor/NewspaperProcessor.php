<?php

namespace empleadoEstatalBot\NewspaperProcessor;

use andreskrey\Readability\Configuration;
use andreskrey\Readability\ParseException;
use andreskrey\Readability\Readability;
use empleadoEstatalBot\empleadoEstatal;
use empleadoEstatalBot\Post;
use GuzzleHttp\Client as HttpClient;
use DOMDocument;
use Illuminate\Database\QueryException;
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

                /*
                 * Some newspapers like to mix every possible encoding out there making a mess after the processing
                 * We need to force everything into UTF8 no matter what original encoding we have.
                 */
                // Disabling for now
//                $markdown = Encoding::fixUTF8($markdown);

                $thing->markdown = $markdown;
                $thing->status = empleadoEstatal::THING_FETCHED;
                $thing->tries = 0;

            } catch (\ErrorException $e) {
                empleadoEstatal::$log->addInfo(sprintf('FetchWorker: Failed to parse in Readability. Thing: %s. URL: %s', $thing->thing, $thing->url));
                $thing->info = 'Failed to parse in Readability';
                $thing->status = empleadoEstatal::THING_REJECTED;
            } catch (\Exception $e) {
                empleadoEstatal::$log->addNotice(sprintf('FetchWorker: Failed to get newspaper (try no %s): %s. URL: %s', $thing->tries, $e->getMessage(), $thing->url));
                $thing->info = substr($e->getMessage(), 0, 254);
            } finally {
                try {
                    $thing->save();
                    $this->checkLength($thing);
                } catch (QueryException $e) {
                    // Catch any query errors for really weird markdown (5 bytes unicode that MySQL doesn't like)

                    $thing->markdown = null;
                    $thing->status = empleadoEstatal::THING_REJECTED;
                    $thing->info = substr($e->getMessage(), 0, 254);
                    empleadoEstatal::$log->addEmergency(sprintf('FetchWorker: Failed to save text to db (try no %s): %s. URL: %s', $thing->tries, $e->getMessage(), $thing->url));

                    // Pleeeease work this time :D
                    $thing->save();
                }
            }
        }
    }

    /**
     * Comments cannot exceed a certain amount of characters. Longer comments must be split in chained comments
     *
     * @param Post $thing
     */
    private function checkLength(Post $thing)
    {
        if (mb_strlen($thing->markdown) > $this->config['max_length']) {
            $splits = [];
            $text = $thing->markdown;

            while (true) {
                if (mb_strlen($text) < $this->config['max_length']) {
                    $splits[] = $text;
                    break;
                }

                $newLine = mb_strrpos($text, "\n", -(mb_strlen($text)) + $this->config['max_length']);

                if ($newLine === false) {
                    $thing->status = empleadoEstatal::THING_REJECTED;
                    $thing->info = sprintf('Too long, impossible to split with current limits. Limit is %s.', $this->config['max_length']);
                    $thing->save();
                    empleadoEstatal::$log->addAlert(sprintf('FetchWorker: Failed to split long post. Thing: %s, limit: %s, text length: %s', $thing->thing, $this->config['max_length'], mb_strlen($thing->markdown)));
                    return;
                }

                $splits[] = mb_substr($text, 0, $newLine + 1) . "\n" . '> ***(continues in next comment)***';
                $text = mb_substr($text, $newLine + 1);
            }

            $thing->markdown = array_shift($splits);
            $thing->save();

            $parent_id = $thing->id;

            foreach ($splits as $split) {
                $post = $thing->replicate(['markdown']);
                $post->parent_id = $parent_id;
                $post->markdown = $split;
                $post->info = sprintf('Multicomment. Parent is %s.', $thing->thing);
                $post->save();
                $parent_id = $post->id;
            }
        }
    }

    private function parseHTML($html, $url)
    {
        $readability = new Readability((new Configuration())
            ->setOriginalURL($url)
            ->setSummonCthulhu(true)
            ->setFixRelativeURLs(true)
        );

        try {
            $readability->parse($html);

            if (mb_strlen($readability->getContent()) < 1000) {
                throw new ParseException();
            }
        } catch (ParseException $e) {
            return false;
        }

        if ($readability->getImage()) {
            $image = sprintf('<h1><img src="%s" alt="%s"></h1><br /><br />', $readability->getImage(), htmlspecialchars($readability->getTitle()));
        } else {
            $image = sprintf('<h1>%s</h1><br/><br/>', htmlspecialchars($readability->getTitle()));
        }

        return $image . $readability->getContent();
    }

    private function signPost($html)
    {
        return $html . "<br/><br/><br/>" . $this->config['signature'];
    }

    private function sanitizeHTML($html)
    {
        $html = $this->solveURLShorteners($html);

        // Eliminar los mails del texto asi reddit no se pone la gorra
        $html = preg_replace_callback('/[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,4}\b/i', function ($match) {
            return str_replace('@', ' at ', $match[0]);
        }, $html);

        // Envolviendo en blockquote el asunto para triggerear la regla css que oculta el texto
        $html = '<blockquote>' . $html . '</blockquote>' . "\n";

        return $html;
    }

    private function solveURLShorteners($html)
    {
        $dom = new DOMDocument('1.0', 'utf-8');
        $dom->loadHTML('<?xml encoding="UTF-8">' . $html);
        $dom->encoding = 'utf-8';

        $links = $dom->getElementsByTagName('a');

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

        return str_ireplace('<?xml encoding="UTF-8"?>', '', $dom->C14N());
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
