<?php
namespace empleadoEstatalBot;

use empleadoEstatalBot\NewspaperManager\NewspaperProcessor\Parsers;

class NewspaperManager
{
    public function getProcessor($domain)
    {
        $newspaper = 'empleadoEstatalBot\\NewspaperManager\\NewspaperProcessor\\Parsers\\' . str_replace('.', '', $domain) . 'Parser';
        if (class_exists($newspaper)) {
            $parser = new $newspaper();
        } else {
            throw new \BadFunctionCallException();
        }

        return $parser;
    }
}