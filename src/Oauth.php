<?php
namespace Ukrbublik\TestFeed;
use Abraham\TwitterOAuth\TwitterOAuth;

$app = new App();

$serviceType = isset($_GET['type']) ? $_GET['type'] : null;
if (!$serviceType)
  throw new \Exception("No service type provided");
if (!array_key_exists($serviceType, $app->getConfig()['services']))
  throw new \Exception("Unknown service type " . $serviceType);
$serviceConfig = $app->config['services'][$serviceType];
$action = $_GET['action'] == 'logout' ? 'logout' : 'login';

if ($action == 'logout') {
  foreach([
    $serviceType . '_' . 'req_oauth_token',
    $serviceType . '_' . 'req_oauth_token_secret',
    $serviceType . '_' . 'oauth_token',
    $serviceType . '_' . 'oauth_token_secret',
    $serviceType . '_' . 'user',
  ] as $key) {
    $app->session->remove($key);
  }
  $redirectUrl = $app->config['rootUrl'];
  header("Location: " . $redirectUrl);
} else if ($action == 'login') {
  switch ($serviceType) {
    case 'twitter':
      if (isset($_GET['oauth_token']) && isset($_GET['oauth_verifier'])) {
        //3. Convert request token to access token
        $oauth_token = $_GET['oauth_token'];
        $oauth_verifier = $_GET['oauth_verifier'];
        if ($app->session->get($serviceType . '_' . 'req_oauth_token') == $oauth_token 
         && $app->session->has($serviceType . '_' . 'req_oauth_token_secret')) {
          $twitter = new TwitterOAuth(
            $serviceConfig['consumerKey'], $serviceConfig['consumerSecret'],
            $app->session->get($serviceType . '_' . 'req_oauth_token'), 
            $app->session->get($serviceType . '_' . 'req_oauth_token_secret')
          );
          $res = $twitter->oauth('oauth/access_token', [
            'oauth_verifier' => $oauth_verifier
          ]);
          assert(isset($res['oauth_token']) && isset($res['oauth_token_secret']));
          $app->session->set($serviceType . '_' . 'oauth_token', $res['oauth_token']);
          $app->session->set($serviceType . '_' . 'oauth_token_secret', $res['oauth_token_secret']);
          $app->session->remove($serviceType . '_' . 'req_oauth_token');
          $app->session->remove($serviceType . '_' . 'req_oauth_token_secret');
          $app->getTwitterUser();
          $redirectUrl = $app->config['rootUrl'];
          header("Location: " . $redirectUrl);
        } else {
          throw new \Exception("Probably you opened 2 login windows or cleared cookies");
        }
      } else {
        //1. Get request token
        $twitter = new TwitterOAuth(
          $serviceConfig['consumerKey'], $serviceConfig['consumerSecret']
        );
        $res = $twitter->oauth('oauth/request_token', [
          'oauth_callback' => $serviceConfig['oauthCallback'] //'oob',
        ]);
        assert($res['oauth_callback_confirmed'] == 'true');
        $app->session->set($serviceType . '_' . 'req_oauth_token', $res['oauth_token']);
        $app->session->set($serviceType . '_' . 'req_oauth_token_secret', $res['oauth_token_secret']);
        
        //2. Redirect user
        $redirectUrl = TwitterOAuth::API_HOST . '/oauth/authorize' . '?' . http_build_query([
          'oauth_token' => $res['oauth_token'],
        ]);
        header("Location: " . $redirectUrl);
      }
      break;
    default:
      throw new Exception("Not supported service type " . $serviceType);
      break;
  }
}

?>
