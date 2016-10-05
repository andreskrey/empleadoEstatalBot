<?php

namespace empleadoEstatalBot;

class Config
{
    static $CLIENT_ID = null;
    static $SECRET_KEY = null;
    static $REDIRECT_URI = null;

    static $SCOPES = ['submit', 'read'];

    static $USERNAME = null;
    static $PASSWORD = null;

    static $REDIS_URL = null;

    static $SIGNATURE = '<hr><p><em><strong>empleadoEstatalBot</strong>, el bot que por solo $XXX por mes te ahorra unos clicks.</em></p><p><a href="/u/subtepass">Autor</a>
    | <a href="https://github.com/andreskrey/empleadoEstatalBot">Código fuente</a> | <a href="https://github.com/andreskrey/empleadoEstatalBot#que-diarios-soporta">Lista de diarios</a></p>';

    public function __construct()
    {
        Config::$CLIENT_ID = getenv('CLIENT_ID');
        Config::$SECRET_KEY = getenv('SECRET_KEY');
        Config::$REDIRECT_URI = getenv('REDIRECT_URI');
        Config::$USERNAME = getenv('USERNAME');
        Config::$PASSWORD = getenv('PASSWORD');
        Config::$REDIS_URL = getenv('REDIS_URL');

        Config::$SIGNATURE = str_replace('XXX', round((time() - strtotime('05/10/2016')) * 1.6 / 150000, 2), Config::$SIGNATURE);
    }
}
