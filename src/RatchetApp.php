<?php
namespace Ukrbublik\TestFeed;
use Ratchet\MessageComponentInterface;
use Ratchet\Wamp\WampServerInterface;
use Ratchet\ConnectionInterface;
use Ratchet\ConnectionInterface as Conn;
use React\EventLoop\LoopInterface;
use Ratchet\Session\Session;
use Ratchet\Session\SessionProvider;

class RatchetApp implements MessageComponentInterface, WampServerInterface 
{
  /*** @var array app config */
  protected $config;
  /*** @var LoopInterface */
  protected $loop;
  /*** @var array to hold Ratchet\ConnectionInterface objs */
  protected $clients;
  /*** @var array to hold StreamSourceInterface objs */
  protected $sources;
  /*** @var array to hold last 25 messages */
  protected $messages;
  /*** @var StreamSourceFactory */
  protected $sourcesFactory;
  /*** @var array to hold last times of sending socket messages */
  protected $lastSendTimes;
  /*** @var SessionProvider */
  protected $sessionProvider = null;

  /**
   * @param LoopInterface $loop
   * @param array $config
   */
  public function __construct($loop, $config) 
  {
    $this->clients = [];
    $this->config = $config;
    $this->loop = $loop;
    $this->sourcesFactory = new StreamSourceFactory($loop, $config);
    $this->sources = [];
    $this->messages = [];
    $this->lastSendTimes = [];

    //Don't let connections to fall asleep if there are no data for a long time 
    // (especially for Heroku)
    $loop->addPeriodicTimer(30, function() {
      $time = time();
      foreach ($this->clients as $sessId => $conns) {
        foreach ($conns as $conn) {
          if (($time - $this->lastSendTimes[$conn->resourceId]) > 30) {
            $conn->send(json_encode([
              'type' => 'hello',
            ]));
            $this->lastSendTimes[$conn->resourceId] = time();
          }
        }
      }
    });
  }

  /**
   * In normal situations we don't need session provider. 
   * But if Ratchet app is separated from regular app, we need to pass sessionid in special
   *  message and apply hook to session provider to set Session object
   * @param SessionProvider $sp
   */
  public function setSessionProvider(SessionProvider $sp) {
    $this->sessionProvider = $sp;
  }

  /**
   * @param Ratchet\ConnectionInterface $conn
   */
  public function onOpen(Conn $conn) 
  {
    $sessId = $conn->Session ? $conn->Session->getId() : null;
    if ($sessId) {
      if (!isset($this->clients[$sessId]))
        $this->clients[$sessId] = [];
      if (!isset($this->lastSendTimes[$sessId]))
      $this->clients[$sessId][] = $conn;
      $this->lastSendTimes[$conn->resourceId] = time();

      echo "[Ratchet] ({$conn->resourceId}) Connected\n";

      $this->updateStreamSources($conn);

      if (isset($this->messages[$sessId])) {
        //User opened 2 tabs in browser
        $ids = array_keys($this->messages[$sessId]);
        $ids = array_reverse($ids);
        foreach ($ids as $id) {
          $msg = $this->messages[$sessId][$id];
          $conn->send(json_encode([
            'type' => 'push',
            'data' => $msg,
            'id' => $id.'',
          ]));
        }
        foreach ($this->sources[$sessId] as $type => $source) {
          $hasToken = $this->sourcesFactory->canCreateServiceStreamSource($type, $conn->Session);
          if ($hasToken)
            $conn->send(json_encode([
              'type' => 'online',
              'val' => $source->isOnline(),
            ]));
        }
      }

    } else if(!$this->config['ratchet']['isSeparated']) {
      echo "[Ratchet] Can't connect without session ({$conn->resourceId})\n";
      $conn->close();
    }
  }

  /**
   * @param Ratchet\ConnectionInterface $conn
   * @param bool $forceClose true to close all stream sources associaited with connection $conn
   */
  protected function updateStreamSources(Conn $conn, $forceClose = false) 
  {
    $sessId = $conn->Session ? $conn->Session->getId() : null;
    if ($sessId) {
      if (!isset($this->sources[$sessId]))
        $this->sources[$sessId] = [];
      if (!isset($this->messages[$sessId]))
        $this->messages[$sessId] = [];
      foreach ($this->config['enabledServices'] as $type) {
        $canCreate = $forceClose ? false : $this->sourcesFactory->canCreateServiceStreamSource($type, $conn->Session);
        if ($canCreate != isset($this->sources[$sessId][$type])) {
          if ($canCreate) {
            echo "[Ratchet] ({$conn->resourceId}) Opened stream source $type\n";
            $source = $this->sourcesFactory->createServiceStreamSource($type, $conn->Session);
            $this->sources[$sessId][$type] = $source;

            $source->on('clear_msgs', function() use ($sessId) {
              $this->sendToSessionConnections($sessId, json_encode([
                'type' => 'clear_msgs',
              ]));
            });

            $source->on('online', function($val) use ($sessId) {
              $this->sendToSessionConnections($sessId, json_encode([
                'type' => 'online',
                'val' => $val,
              ]));
            });

            $source->on('error', function($err) use ($sessId) {
              $this->sendToSessionConnections($sessId, json_encode([
                'type' => 'error',
                'error' => $err,
              ]));
            });

            $source->on('warning', function($err) use ($sessId) {
              $this->sendToSessionConnections($sessId, json_encode([
                'type' => 'warning',
                'error' => $err,
              ]));
            });

            $source->on('msg', function($msg) use ($sessId, &$source) {
              if(isset($msg['delete'])) {
                $id = $msg['delete']['status']['id_str'].'';
                if (isset($this->messages[$sessId][$id])) {
                  unset($this->messages[$sessId][$id]);
                  $this->sendToSessionConnections($sessId, json_encode([
                    'type' => 'del',
                    'id' => $id,
                  ]));
                }
              } else if(isset($msg['text']) && isset($msg['id_str'])) {
                //it's tweet
                $ts = strtotime($msg['created_at']);
                $msg['created_at_ts'] = $ts;
                $id = $msg['id_str'].'';
                $isUpdate = isset($this->messages[$sessId][$id]);
                $this->messages[$sessId][$id] = $msg;
                uasort($this->messages[$sessId], function($a, $b) {
                  return ($a['created_at_ts'] == $b['created_at_ts'] ? 0 : 
                    ($a['created_at_ts'] < $b['created_at_ts'] ? +1 : -1)) ;
                });
                $ids = array_keys($this->messages[$sessId]);
                $del_ids = array_slice($ids, $this->config['topMessagesCount']);
                foreach ($del_ids as $del_id) {
                  $this->sendToSessionConnections($sessId, json_encode([
                    'type' => 'del',
                    'id' => $del_id.'',
                  ]));
                  unset($this->messages[$sessId][$del_id]);
                }
                $ids = array_keys($this->messages[$sessId]);
                $id_ind = array_search($id, $ids);
                if ($id_ind !== false) {
                  $after_id = $id_ind > 0 ? $ids[$id_ind - 1].'' : null;
                  $this->sendToSessionConnections($sessId, json_encode([
                    'type' => ($isUpdate ? 'update' : 'push'),
                    'data' => $msg,
                    'id' => $id,
                    'after_id' => $after_id,
                  ]));
                }
              } else {
                //todo: if follow/unfollow, need to refresh feed? (Twitter doen't do this)
                $this->sendToSessionConnections($sessId, json_encode([
                  'type' => 'other',
                  'data' => $msg,
                ]));
              }
            });

            $source->open();
          } else if (isset($this->sources[$sessId][$type])) {
            echo "[Ratchet] ({$conn->resourceId}) Closed stream source $type\n";
            $source = $this->sources[$sessId][$type];
            $source->cancel();
            $source->removeAllListeners('msg');
            $source->removeAllListeners('error');
            $source->removeAllListeners('warning');
            unset($this->sources[$sessId][$type]);
          }
        }
      }
    }
  }

  /**
   * @param string $sessId
   * @param string $data
   */
  public function sendToSessionConnections($sessId, $data) 
  {
    if (isset($this->clients[$sessId])) {
      foreach ($this->clients[$sessId] as $conn) {
        $this->lastSendTimes[$conn->resourceId] = time();
        $conn->send($data);
      }
    }
  }

  /**
   * @param Ratchet\ConnectionInterface $conn
   * @param string $msg
   */
  public function onMessage(Conn $conn, $msg) 
  {
    $msg = json_decode($msg);
    $sessId = $conn->Session ? $conn->Session->getId() : null;
    if ($sessId) {
      if ($msg->type == "update_stream_sources") {
        $this->updateStreamSources($conn);
      }
    } else {
      if ($msg->type == "set_session") {
        echo "[Ratchet] ({$conn->resourceId}) Session id: {$msg->sid}\n";
        $conn->WebSocket->request->addCookie(ini_get('session.name'), $msg->sid);
        if ($this->sessionProvider)
          $this->sessionProvider->onOpen($conn);
        $this->onOpen($conn);
      }
    }
  }

  /**
   * @param Ratchet\ConnectionInterface $conn
   * @param Exception $e
   */
  public function onError(Conn $conn, \Exception $e) 
  {
    echo "[Ratchet] ({$conn->resourceId}) Error: {$e->getMessage()}\n";

    $conn->close();
  }

  /**
   * @param Ratchet\ConnectionInterface $conn
   */
  public function onClose(Conn $conn) 
  {
    unset($this->lastSendTimes[$conn->resourceId]);
    $sessId = $conn->Session ? $conn->Session->getId() : null;
    if ($sessId) {
      $indx = array_search($conn, $this->clients[$sessId]);
      if ($indx !== false) {
        array_splice($this->clients[$sessId], $indx, 1);
        if (count($this->clients[$sessId]) == 0) {
          $this->updateStreamSources($conn, true);
        }
        if (count($this->sources[$sessId]) == 0) {
          unset($this->clients[$sessId]);
          unset($this->sources[$sessId]);
          unset($this->messages[$sessId]);
        }
      }
    }

    echo "[Ratchet] ({$conn->resourceId}) Disconnected\n";
  }


  public function onPublish(Conn $conn, $topic, $event, array $exclude, array $eligible) 
  {
    //$topic->broadcast($event);
  }
  public function onCall(Conn $conn, $id, $topic, array $params) 
  {
    //$conn->callError($id, $topic, 'RPC not supported on this demo');
  }
  public function onSubscribe(Conn $conn, $topic) {}
  public function onUnSubscribe(Conn $conn, $topic) {}

}




?>