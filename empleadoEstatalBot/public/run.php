<?php
include '..' . DIRECTORY_SEPARATOR . 'empleadoEstatalBot.php';

$ñoqui = new empleadoEstatal();
$posts = $ñoqui->laburar();

echo sprintf('Done. %s posts', $posts);
