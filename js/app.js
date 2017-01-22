/**
 *
 */

$( document ).ready(function() {
  $('.twitter-authed').toggle(app.twitterUser !== null);
  $('.twitter-nonauthed').toggle(app.twitterUser === null);
  setIsOnline(false);

  ratchet_connect();
});

function ratchet_connect() {
  console.log("[Ratchet] Connecting...");
  var conn = new WebSocket('ws://'+location.hostname+':'+app.ratchet.port+'/');

  conn.onopen = function(e) {
    console.log("[Ratchet] Connected", e);
  };

  conn.onclose = function(e) {
    console.log("[Ratchet] Connection closed", e);
    setIsOnline(false);
    setError(null, null);

    setTimeout(function() {
      ratchet_connect();
    }, 5*1000);
  };

  conn.onmessage = function(e) {
    var msg = JSON.parse(e.data);
    console.log("[Ratchet] msg", msg);
    if (msg.type == 'del') {
      $('#msg-'+msg.id).remove();
    } else if (msg.type == 'push' || msg.type == 'update') {
      var msgHtml = renderTweet(msg.data);

      if ($('#msg-'+msg.id).length == 1) {
        $('#msg-'+msg.id).replaceWith(msgHtml);
        if (msg.after_id && $('#msg-'+msg.after_id).length == 1)
          $('#msg-'+msg.after_id).after( $('#msg-'+msg.id) );
      } else if (msg.after_id && $('#msg-'+msg.after_id).length == 1) {
        $('#msg-'+msg.after_id).after(msgHtml);
      } else {
        $('#feed').prepend(msgHtml);
      }
    } else if (msg.type == 'warning' || msg.type == 'error') {
      var error = msg.error;
      setError(error, msg.type);
    } else if (msg.type == 'online') {
      var isOnline = (msg.val == 1);
      setIsOnline(isOnline);
    } else if (msg.type == 'clear_msgs') {
      $('.feed-msg').remove();
    }
  };
}

function renderTweet(msg) {
  var msgHtml = '';
  msgHtml += '<div class="list-group-item" class="feed-msg" id="msg-'+ msg.id_str +'">';
  msgHtml += '<a href="https://twitter.com/'+msg.user.screen_name+'">'+ '@' + msg.user.screen_name +'</a>';
  msgHtml += '<span>' + msg.text + '</span>';
  msgHtml += '</div>';
  return msgHtml;
}

function setIsOnline(val) {
  $('.twitter-is-online').text(val ? "Online" : "Offline");
  $('.twitter-is-online').toggleClass("label-default", !val);
  $('.twitter-is-online').toggleClass("label-success", val);
  if (val)
    setError(null, null);
}

function setError(error, type) {
  $('#toast').toggleClass("alert-warning", type == 'warning');
  $('#toast').toggleClass("alert-danger", type == 'error');
  $('#toast').text(error);
}