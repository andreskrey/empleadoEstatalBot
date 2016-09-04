<?php

class empleadoEstatalConfig
{
    static $CLIENT_ID = null;
    static $SECRET_KEY = null;
    static $REDIRECT_URI = null;

    static $SCOPES = ['submit', 'read'];

    static $USERNAME = null;
    static $PASSWORD = null;

    static $REDIS_URL = null;

    static $SIGNATURE = '<hr><p><em><strong>empleadoEstatalBot</strong>, el primer bot de reddit 100% peronista. Ensamblado en TdF. ^(Hecho en China.)</em></p><p><a href="/u/subtepass">Autor</a>
    | <a href="https://github.com/andreskrey/empleadoEstatalBot">Código fuente</a> | <a href="https://github.com/andreskrey/empleadoEstatalBot#que-diarios-soporta">Lista de diarios <soportados></soportados></a></p>';

    public function __construct()
    {
        empleadoEstatalConfig::$CLIENT_ID = getenv('CLIENT_ID');
        empleadoEstatalConfig::$SECRET_KEY = getenv('SECRET_KEY');
        empleadoEstatalConfig::$REDIRECT_URI = getenv('REDIRECT_URI');
        empleadoEstatalConfig::$USERNAME = getenv('USERNAME');
        empleadoEstatalConfig::$PASSWORD = getenv('PASSWORD');
        empleadoEstatalConfig::$REDIS_URL = getenv('REDIS_URL');
    }
}
