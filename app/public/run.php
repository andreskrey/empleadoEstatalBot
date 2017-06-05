<?php
use empleadoEstatalBot\empleadoEstatal;

include '../app/empleadoEstatalBot.php';

$ñoqui = new empleadoEstatal();

if (isset($_GET['ids'])) {
    $ids = explode(',', $_GET['ids']);
    $posts = $ñoqui->laburarPost($ids);
} else {
    $posts = $ñoqui->laburar();
}


echo sprintf('Done. %s posts', $posts);
