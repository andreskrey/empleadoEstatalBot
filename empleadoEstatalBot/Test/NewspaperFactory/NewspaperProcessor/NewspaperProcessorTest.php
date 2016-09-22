<?php

namespace empleadoEstatalBot\Test;

use empleadoEstatalBot;

class NewspaperProcessorTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @param string $originalString Short HTML to check length
     *
     * @dataProvider providerTestCheckLengthRejectsShortArticles
     */

    public function testCheckLengthRejectsShortArticles($originalString)
    {
        $newspaper = new empleadoEstatalBot\NewspaperFactory\NewspaperProcessor\Parsers\lanacioncomarParser();
        $result = $newspaper->checkLength($originalString);

        $this->assertFalse($result);
    }

    public function providerTestCheckLengthRejectsShortArticles()
    {
        include '../../../../config/config.php';

        $signature = empleadoEstatalBot\Config::$SIGNATURE;

        return array(
            array("<!DOCTYPE html><html><head><title></title></head><body>
<h1>Titulo prueba de considerable longitud</h1>
<h2>Subtitulo prueba, de considerable longitud, aun un poco mas largo que el titulo, ya que explica cosas en general</h2>
<p>Parrafo pequeño</p>
<p>Otro parrafo pequeño</p>
$signature
</body></html>"),
            array("<!DOCTYPE html><html><head><title></title></head><body>
<h1>Titulo prueba de considerable longitud</h1>
<h2>Subtitulo prueba, de considerable longitud, aun un poco mas largo que el titulo, ya que explica cosas en general</h2>
<p>Titulo prueba de considerable longitud</p>
<p>Subtitulo prueba, de considerable longitud, aun un poco mas largo que el titulo, ya que explica cosas en general</p>
$signature
</body></html>"),
        );
    }
}