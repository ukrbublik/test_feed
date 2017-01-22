<?php
namespace Ukrbublik\TestFeed;
use Evenement\EventEmitter;
use React\EventLoop\LoopInterface;
use Ratchet\Session\Session;

use Ukrbublik\ReactStreamingBird\StreamReader;
use Ukrbublik\ReactStreamingBird\StreamingBird;

class TwitterStreamSource extends EventEmitter implements StreamSourceInterface 
{
  /*** @var array */
  protected $config;
  /*** @var array */
  protected $serviceConfig;
  /*** @var LoopInterface */
  protected $loop;
  /*** @var StreamReader */
  protected $reader = null;
  /*** @var bool */
  private $_isOnline = false;

  /**
   * @param LoopInterface $loop
   * @param array $config app config
   */
  public function __construct($loop, $config) 
  {
    $this->config = $config;
    $this->loop = $loop;
    $this->serviceConfig = $config['services'][self::getServiceType()];
  }

  /**
   * @return string
   */
  public static function getServiceType() 
  {
    return "twitter";
  }

  /**
   * @return bool
   */
  public function isOnline() 
  {
    return $this->_isOnline;
  }

  /**
   * @param arary options
   */
  public function create($session, $options = []) 
  {
    if (!$this->canCreate($session))
      return null;
    $oauth_token = $session->get(self::getServiceType() . '_' . 'oauth_token');
    $oauth_token_secret = $session->get(self::getServiceType() . '_' . 'oauth_token_secret');
    $bird = new StreamingBird(
      $this->serviceConfig['consumerKey'], $this->serviceConfig['consumerSecret'], 
      $oauth_token, $oauth_token_secret
    );
    $this->reader = $bird->createStreamReader($this->loop, StreamReader::METHOD_USER);
    $this->reader->on('error', function($err) {
      $errStr = ($err instanceof \Exception ? $err->getMessage() : $err);
      $this->emit('error', [$errStr]);
    });
    $this->reader->on('warning', function($err) {
      $errStr = ($err instanceof \Exception ? $err->getMessage() : $err);
      $this->emit('warning', [$errStr]);
    });
    $this->reader->on('tweet', function($tweet) {
      $this->emit('msg', [$tweet]);
    });
    $this->reader->on('clear_tweets', function() {
      $this->emit('clear_msgs', []);
    });
    $this->reader->on('online', function($val) {
      $this->_isOnline = (bool) $val;
      $this->emit('online', [$val]);
    });
    return $this->reader;
  }

  /**
   * Has access token to create stream?
   * @return bool
   */
  public static function canCreate($session) 
  {
    $oauth_token = $session->get(self::getServiceType() . '_' . 'oauth_token');
    $oauth_token_secret = $session->get(self::getServiceType() . '_' . 'oauth_token_secret');
    return ($oauth_token && $oauth_token_secret);
  }

  /**
   * Open stream to read data
   */
  public function open() 
  {
    if ($this->reader)
      $this->reader->openAsync();
  }

  /**
   * Stop streaming data, close stream
   */
  public function cancel() 
  {
    if ($this->reader)
      $this->reader->stop();
  }
}

?>
