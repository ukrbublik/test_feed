<?php
namespace Ukrbublik\TestFeed;
use Evenement\EventEmitterInterface;
use Ratchet\Session\Session;

interface StreamSourceInterface extends EventEmitterInterface 
{

  /**
   * @return string
   */
  public static function getServiceType();

  /**
   * Has access token to create stream?
   * @param Session $session
   * @return bool
   */
  public static function canCreate($session);

  /**
   * Create stream
   * @param Session $session
   * @param array options
   */
  public function create($session, $options = []);

  /**
   * Open stream to read data
   */
  public function open();

  /**
   * Stop streaming data, close stream
   */
  public function cancel();

  /**
   * @return bool
   */
  public function isOnline();
}

?>
