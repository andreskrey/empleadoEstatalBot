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

    static $SIGNATURE = '<hr><p><em><strong>empleadoEstatalBot</strong>, el primer bot de reddit 100% peronista. Ensamblado en TdF. ^(Hecho en China.)</em></p><p><a href="/u/subtepass">Autor</a>
    | <a href="https://github.com/andreskrey/empleadoEstatalBot">Código fuente</a> | <a href="https://github.com/andreskrey/empleadoEstatalBot#que-diarios-soporta">Lista de diarios soportados</a></p>';

    public function __construct()
    {
        Config::$CLIENT_ID = getenv('CLIENT_ID');
        Config::$SECRET_KEY = getenv('SECRET_KEY');
        Config::$REDIRECT_URI = getenv('REDIRECT_URI');
        Config::$USERNAME = getenv('USERNAME');
        Config::$PASSWORD = getenv('PASSWORD');
        Config::$REDIS_URL = getenv('REDIS_URL');
    }
}
