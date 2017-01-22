<?php

$rootUrl = isset($_SERVER['HTTP_HOST']) ? (!empty($_SERVER['HTTPS']) ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] : null;
$rootPath = __DIR__;

$config = [
  'rootUrl' => $rootUrl,
  'rootPath' => $rootPath,
  'services' => [
    'twitter' => [
      'consumerKey' => 'ljbnVRM64PCAKIpYwtO7GuWP8',
      'consumerSecret' => 'cYXYbFeglwSGtd7EWkPy13RFqrj8pFYbn6FK7bmYAF5xsk6zrq',
      'oauthCallback' => ($rootUrl ? $rootUrl . '/oauth.php?type=twitter' : null),
    ]
  ],
  'memcached' => [
    'servers' => getenv("MEMCACHIER_SERVERS") ? getenv("MEMCACHIER_SERVERS") : 'localhost:11211',
  ],
  'ratchet' => [
    'port' => getenv('IS_HEROKU') ? 80 : 5000,
    'host' => getenv('IS_HEROKU') ? 'stream-feed-sock.herokuapp.com' : '',
    'listenPort' => getenv('PORT') ? getenv('PORT') : 5000,
  ],
  'topMessagesCount' => 25,
  'enabledServices' => ['twitter'],
];
return $config;

?>
