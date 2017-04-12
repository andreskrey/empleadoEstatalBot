<?php
use empleadoEstatalBot\Config;

include('../empleadoEstatalBot/empleadoEstatalBot.php');
$redis = new \Predis\Client(Config::$REDIS_URL);

$firma = $redis->get('firma');
if (!$firma) $firma = 'Sin firma.';
$fechaTimeStamp = $redis->get('firma_fecha');
if ($fechaTimeStamp) {
    $fechaTimeStamp = (time() - $fechaTimeStamp);
    if ($fechaTimeStamp / 60 > 60) {
        $fecha = 'Ahora!';
    } else {
        $fecha = (int)(60 - $fechaTimeStamp / 60) . ' minutos.';
    }
} else {
    $fecha = 'Ahora!';
    $fechaTimeStamp = 3660;
}

if (!empty($_POST)) {
    $success = true;
    if (isset($_POST['firma'])) {
        if (strlen($_POST['firma'] > 120)) {
            $success = false;
            $resultMessage = 'Maximo 120 caracteres.';
        }

        if ($fechaTimeStamp / 60 < 60) {
            $success = false;
            $resultMessage = 'Falta todavia para poder actualizar la firma, papu.';
        }
    } else {
        $success = false;
    }

    if ($success) {
        $firma = $_POST['firma'];
        $resultMessage = 'Actualizado!';
        $redis->set('firma', $_POST['firma']);
        $redis->set('firma_fecha', time());
    }
}

?>
<html>
<head>
    <link href='http://fonts.googleapis.com/css?family=Open+Sans+Condensed:300' rel='stylesheet' type='text/css'>
    <style type="text/css">
        .form-style-8 {
            font-family: 'Open Sans Condensed', arial, sans;
            width: 500px;
            padding: 30px;
            background: #FFFFFF;
            margin: 50px auto;
            box-shadow: 0px 0px 15px rgba(0, 0, 0, 0.22);
            -moz-box-shadow: 0px 0px 15px rgba(0, 0, 0, 0.22);
            -webkit-box-shadow: 0px 0px 15px rgba(0, 0, 0, 0.22);

        }

        .form-style-8 h2 {
            background: #4D4D4D;
            text-transform: uppercase;
            font-family: 'Open Sans Condensed', sans-serif;
            color: #797979;
            font-size: 18px;
            font-weight: 100;
            padding: 20px;
            margin: -30px -30px 30px -30px;
        }

        .form-style-8 #error {
            background: #094d0a;
            text-transform: uppercase;
            font-family: 'Open Sans Condensed', sans-serif;
            color: #ffffff;
            font-size: 18px;
            font-weight: 100;
            padding: 20px;
            margin: -30px -30px 30px -30px;
        }

        .form-style-8 input[type="text"],
        .form-style-8 select {
            box-sizing: border-box;
            -webkit-box-sizing: border-box;
            -moz-box-sizing: border-box;
            outline: none;
            display: block;
            width: 100%;
            padding: 7px;
            border: none;
            border-bottom: 1px solid #ddd;
            background: transparent;
            margin-bottom: 10px;
            font: 16px Arial, Helvetica, sans-serif;
            height: 45px;
        }

        .form-style-8 input[type="button"],
        .form-style-8 input[type="submit"] {
            -moz-box-shadow: inset 0px 1px 0px 0px #45D6D6;
            -webkit-box-shadow: inset 0px 1px 0px 0px #45D6D6;
            box-shadow: inset 0px 1px 0px 0px #45D6D6;
            background-color: #2CBBBB;
            border: 1px solid #27A0A0;
            display: inline-block;
            cursor: pointer;
            color: #FFFFFF;
            font-family: 'Open Sans Condensed', sans-serif;
            font-size: 14px;
            padding: 8px 18px;
            text-decoration: none;
            text-transform: uppercase;
        }

        .form-style-8 input[type="button"]:hover,
        .form-style-8 input[type="submit"]:hover {
            background: linear-gradient(to bottom, #34CACA 5%, #30C9C9 100%);
            background-color: #34CACA;
        }


    </style>
</head>
<body>
<div class="form-style-8">
    <h2>Personalizá la firma del empleadoEstatalBot</h2>
    <?php if (isset($resultMessage)) { ?><h2 id="error"><?= $resultMessage ?></h2><?php } ?>
    <h3>Firma actual: <?= htmlspecialchars($firma) ?></h3>
    <h4>Proxima actualización: <?= $fecha ?></h4>
    <form action="firma.php" method="post">
        <input type="text" name="firma" placeholder="Tu firma aquí"/>
        <input type="submit" value="Guardar"/>
    </form>
</div>
</body>
</html>
