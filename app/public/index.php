<?php
use empleadoEstatalBot\Post;

require '../empleadoEstatalBot.php';
new \empleadoEstatalBot\empleadoEstatal();
$mapping = [
    -1 => 'Rejected',
    1 => 'To fetch',
    2 => 'To post',
    3 => 'Posted',
];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Title</title>
    <link rel="stylesheet" href="bootstrap.min.css">
    <link rel="stylesheet" href="styles.css">
</head>
<body>
<div class="container-fluid">
    <h1>empleadoEstatalBot</h1>

    <h2>Ultimos diez posts</h2>

    <table class="table-striped">
        <tr>
            <th>Subreddit</th>
            <th>Thing ID</th>
            <th>URL</th>
            <th>Status</th>
            <th>Tries</th>
            <th>Info</th>
        </tr>
        <?php foreach (Post::limit(10)->orderBy('updated_at', 'desc')->get() as $thing) { ?>
            <tr>
                <td><?= $thing->subreddit ?></td>
                <td><?= $thing->thing ?></td>
                <td><?= $thing->url ?></td>
                <td><?= $mapping[$thing->status] ?></td>
                <td><?= $thing->tries ?></td>
                <td><?= $thing->info ?></td>
            </tr>
        <?php } ?>
    </table>
</div>
</body>
</html>
