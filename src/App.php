<?php
namespace Ukrbublik\TestFeed;
use League\Plates\Engine as PlatesEngine;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\NativeSessionStorage;
use Symfony\Component\HttpFoundation\Session\Storage\Handler\MemcachedSessionHandler;
use Abraham\TwitterOAuth\TwitterOAuth;
use \Memcached;

/**
 * Main app
 */
class App {
  /*** @var array */
  protected $_config = null;
  /*** @var Memcached */
  protected $_memcached = null;
  /*** @var Session */
  protected $_session = null;
  /*** @var PlatesEngine */
  protected $templates = null;
  /*** @var TwitterOAuth */
  protected $_twitter = null;


  public function __construct() {
    $this->_config = require(dirname(__DIR__) . "/config.php");

    //http://platesphp.com/
    $this->templates = new PlatesEngine($this->config['rootPath'] . '/tpl');
  }

  public function __destruct() {
    $this->memcached->quit();
  }


  //
  // Getters, setters
  //

  public function __get($var)
  {
    $getFunc = 'get'.ucfirst($var);
    if (method_exists($this, $getFunc)) {
      return $this->$getFunc();
    } else {
      throw new \Exception("Inexistent property: $var");
    }
  }

  public function __set($var, $value)
  {
    $setFunc = 'set'.ucfirst($var);
    $getFunc = 'get'.ucfirst($var);
    if (method_exists($this, $setFunc)) {
      $this->$setFunc($value);
    } else {
      if (method_exists($this, $getFunc)) {
        throw new \Exception("property $var is read-only");
      } else {
        throw new \Exception("Inexistent property: $var");
      }
    }
  }

  public function getConfig() {
    return $this->_config;
  }

  public function getMemcached() {
    if (!$this->_memcached) {
      if (getenv("IS_HEROKU")) {
        $m = new Memcached("memcached_pool");
        $m->setOption(Memcached::OPT_BINARY_PROTOCOL, TRUE);

        // some nicer default options
        // - nicer TCP options
        $m->setOption(Memcached::OPT_TCP_NODELAY, TRUE);
        $m->setOption(Memcached::OPT_NO_BLOCK, FALSE);
        // - timeouts
        $m->setOption(Memcached::OPT_CONNECT_TIMEOUT, 2000);    // ms
        $m->setOption(Memcached::OPT_POLL_TIMEOUT, 2000);       // ms
        $m->setOption(Memcached::OPT_RECV_TIMEOUT, 750 * 1000); // us
        $m->setOption(Memcached::OPT_SEND_TIMEOUT, 750 * 1000); // us
        // - better failover
        $m->setOption(Memcached::OPT_DISTRIBUTION, Memcached::DISTRIBUTION_CONSISTENT);
        $m->setOption(Memcached::OPT_LIBKETAMA_COMPATIBLE, TRUE);
        $m->setOption(Memcached::OPT_RETRY_TIMEOUT, 2);
        $m->setOption(Memcached::OPT_SERVER_FAILURE_LIMIT, 1);
        $m->setOption(Memcached::OPT_AUTO_EJECT_HOSTS, TRUE);

        // setup authentication
        $m->setSaslAuthData( getenv("MEMCACHIER_USERNAME")
                           , getenv("MEMCACHIER_PASSWORD") );

        // We use a consistent connection to memcached, so only add in the
        // servers first time through otherwise we end up duplicating our
        // connections to the server.
        if (!$m->getServerList()) {
            // parse server config
            $servers = explode(",", getenv("MEMCACHIER_SERVERS"));
            foreach ($servers as $s) {
                $parts = explode(":", $s);
                $m->addServer($parts[0], $parts[1]);
            }
        }
      } else {
        $m = new Memcached();
        $servers = explode(",", $this->config['memcached']['servers']);
        foreach ($servers as $s) {
          $parts = explode(":", $s);
          $m->addServer($parts[0], $parts[1]);
        }
      }

      $this->_memcached = $m;
    }
    return $this->_memcached;
  }

  public function getSession() {
    if (!$this->_session) {
      $sessionStorage = new NativeSessionStorage([], 
        new MemcachedSessionHandler($this->memcached));
      $this->_session = new Session($sessionStorage);
      $this->_session->start();
    }
    return $this->_session;
  }

  //
  // Services
  //

  public function hasTwitterOauthToken() {
    $serviceType = 'twitter';
    return $this->session->has($serviceType . '_' . 'oauth_token')
      && $this->session->has($serviceType . '_' . 'oauth_token_secret');
  }

  public function getTwitterUser() {
    $serviceType = 'twitter';
    if (!$this->hasTwitterOauthToken())
      return null;
    else {
      $serviceUser = $this->session->get($serviceType . '_' . 'user');
      if (!$serviceUser) {
        $serviceUser = $this->twitterOauth->get('account/verify_credentials', []);
        if ($serviceUser && isset($serviceUser->errors))
          throw new \Exception("Twitter error: " . $serviceUser->errors[0]->message);
        $this->session->set($serviceType . '_' . 'user', $serviceUser);
      }
      return $serviceUser;
    }
  }

  public function getTwitterOauth() {
    $serviceType = 'twitter';
    $serviceConfig = $this->config['services'][$serviceType];
    if (!$this->hasTwitterOauthToken())
      $this->_twitter = null;
    else if ($this->_twitter === null)
      $this->_twitter = new TwitterOAuth(
        $serviceConfig['consumerKey'], $serviceConfig['consumerSecret'],
        $this->session->get($serviceType . '_' . 'oauth_token'), 
        $this->session->get($serviceType . '_' . 'oauth_token_secret')
      );
    return $this->_twitter;
  }

  //
  // Templates
  //

  public function render($tpl, $params) {
    echo $this->templates->render($tpl, $params);
  }
}

?>