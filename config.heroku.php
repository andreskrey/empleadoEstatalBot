<?php

class empleadoEstatalConfig
{
    static $CLIENT_ID = null;
    static $SECRET_KEY = null;
    static $REDIRECT_URI = null;

    static $SCOPES = ['submit', 'read'];

    static $USERNAME = null;
    static $PASSWORD = null;

    static $SIGNATURE = '<hr><p><em>Hola, soy <strong>empleadoEstatalBot</strong> y como todo empleado estatal a veces hago mal mi trabajo, o por la
    mitad o incluso desaparezco un tiempo sin previo aviso. Si no me llaman para militar o no estoy tomando mate, posteo
    el contenido completo de las notas periodísticas que aparecen en /r/argentina</em>.</p><p><a href="/u/subtepass">Autor</a>
    | <a href="https://github.com/andreskrey/empleadoEstatalBot">Código fuente</a> | <a href="https://github.com/andreskrey/empleadoEstatalBot#que-diarios-soporta">Lista de diarios <soportados></soportados></a></p>';

    public function __construct()
    {
        empleadoEstatalConfig::$CLIENT_ID = getenv('CLIENT_ID');
        empleadoEstatalConfig::$SECRET_KEY = getenv('SECRET_KEY');
        empleadoEstatalConfig::$REDIRECT_URI = getenv('REDIRECT_URI');
        empleadoEstatalConfig::$USERNAME = getenv('USERNAME');
        empleadoEstatalConfig::$PASSWORD = getenv('PASSWORD');
    }
}
