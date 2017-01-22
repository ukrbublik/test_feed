<?php
namespace Ukrbublik\TestFeed;
require_once "vendor/autoload.php";

$app = new App();

$tplParams = [
  'app' => [
    'twitterUser' => $app->twitterUser,
    'ratchet' => $app->config['ratchet'],
    'sessionName' => ini_get('session.name'),
  ],
  'twitterLogin' => ($app->twitterUser ? $app->twitterUser->screen_name : null),
];
$app->render('app', $tplParams);

?>
