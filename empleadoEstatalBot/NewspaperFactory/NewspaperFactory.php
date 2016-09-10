<?php
namespace empleadoEstatalBot\NewspaperFactory;

use empleadoEstatalBot\NewspaperFactory\NewspaperProcessor\Parsers;

class NewspaperFactory
{
    public function getProcessor($domain)
    {
        $newspaper = 'empleadoEstatalBot\\NewspaperFactory\\NewspaperProcessor\\Parsers\\' . str_replace('.', '', $domain) . 'Parser';
        if (class_exists($newspaper)) {
            $parser = new $newspaper();
        } else {
            throw new \BadFunctionCallException();
        }

        return $parser;
    }
}