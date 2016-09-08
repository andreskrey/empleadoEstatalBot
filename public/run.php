<?php
use empleadoEstatalBot\empleadoEstatal;

include '../empleadoEstatalBot/empleadoEstatalBot.php';

$ñoqui = new empleadoEstatal();
$posts = $ñoqui->laburar();

echo sprintf('Done. %s posts', $posts);
