<?php
namespace Ukrbublik\TestFeed;
use React\EventLoop\LoopInterface;
use Ratchet\Session\Session;

class StreamSourceFactory 
{
  /*** @var array app config */
  protected $config;
  /*** @var LoopInterface */
  protected $loop;

  /**
   * @param LoopInterface $loop
   * @param array $config app config
   */
  public function __construct($loop, $config) 
  {
    $this->config = $config;
    $this->loop = $loop;
  }

  /**
   * Fabric of StreamSourceInterface objects
   * @param string $serviceType
   * @param Session $session
   * @return StreamSourceInterface
   */
  public function createServiceStreamSource($serviceType, $session) 
  {
    $source = null;
    switch ($serviceType) {
      case 'twitter':
        $source = new TwitterStreamSource($this->loop, $this->config);
        $source->create($session, []);
      break;
      default:
        throw new \Exception("Unknown service type " . $serviceType);
      break;
    }
    return $source;
  }

  /**
   * Fabric method
   * @param string $serviceType
   * @param Session $session
   * @return bool
   */
  public function canCreateServiceStreamSource($serviceType, $session) 
  {
    switch ($serviceType) {
      case 'twitter':
        return TwitterStreamSource::canCreate($session);
      break;
      default:
        throw new \Exception("Unknown service type " . $serviceType);
      break;
    }
  }
}

?>
