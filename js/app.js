/**
 *
 */

$( document ).ready(function() {
  $('.twitter-authed').toggle(app.twitterUser !== null);
  $('.twitter-nonauthed').toggle(app.twitterUser === null);
  setIsOnline(false);

  ratchet_connect();

  setInterval(function() {
    updateHumanTimes();
  }, 20*1000);
});

function ratchet_connect() {
  console.log("[Ratchet] Connecting...");
  var conn = new WebSocket('ws://'+(app.ratchet.host != '' ? app.ratchet.host : location.hostname)+':'+app.ratchet.port+'/');

  conn.onopen = function(e) {
    console.log("[Ratchet] Connected", e);
    if (app.ratchet.isSeparated) {
      var sid = $.cookie(app.sessionName);
      conn.send(JSON.stringify({type: "set_session", sid: sid}));
    }
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
  msgHtml += '<div class="list-group-item feed-msg" id="msg-'+ msg.id_str +'">';
  
  msgHtml += '<div class="stream-item-header">';
  msgHtml += '<a class="account-group" href="'+ ('https://twitter.com/'+msg.user.screen_name) +'">';
  if (msg.user.profile_image_url) {
    msgHtml += '<img class="avatar" src="'+ msg.user.profile_image_url +'" />';
  }
  msgHtml += '<strong class="fullname">'+ msg.user.name +'</strong>';
  msgHtml += '<span>&rlm;</span>';
  msgHtml += '<span class="username">'+ '<b>@</b>' + '<b>'+msg.user.screen_name+'</b>' +'</span>';
  msgHtml += '</a>';
  msgHtml += '<span class="time" data-ts="'+ (msg.created_at_ts*1000) +'">'
    + humanTime(msg.created_at_ts*1000) 
    +'</span>';
  msgHtml += '</div>';

  var textHtml = twttr.txt.autoLink(msg.text, {
    urlEntities: msg.entities.urls
  });
  msgHtml += '<p class="tweet-text">' + textHtml + '</p>';

  if (msg.entities.media && msg.entities.media.length) {
    var media = msg.entities.media[0];
    if (media.type == 'photo')
    msgHtml += '<img src="'+media.media_url+'" width='+media.sizes.small.w+' height='+media.sizes.small.h+' />';
  }

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

function updateHumanTimes() {
  $('.time').each(function(ind, el) {
    var $el = $(el);
    var date = parseInt( $el.data('ts') );
    var str = humanTime(date);
    $el.text(str);
  });
}

function humanTime(date) {
  var delta = Math.round((+new Date - date) / 1000);

  var minute = 60,
      hour = minute * 60,
      day = hour * 24,
      week = day * 7,
      month = day * 30,
      year = day * 365;

  var fuzzy;
  if (delta < 30) {
      fuzzy = 'now';
  } else if (delta < minute) {
      fuzzy = delta + ' seconds ago';
  } else if (delta < 2 * minute) {
      fuzzy = 'a minute ago'
  } else if (delta < hour) {
      fuzzy = Math.floor(delta / minute) + ' minutes ago';
  } else if (Math.floor(delta / hour) == 1) {
      fuzzy = '1 hour ago'
  } else if (delta < day) {
      fuzzy = Math.floor(delta / hour) + ' hours ago';
  } else if (delta < day * 2) {
      fuzzy = 'yesterday';
  } else if (delta < week) {
      fuzzy = Math.floor(delta / day) + ' days ago';
  } else if (delta < month) {
      fuzzy = Math.floor(delta / week) + ' weeks ago';
  } else if (Math.floor(delta / month) == 1) {
      fuzzy = '1 month ago';
  } else if (delta < year) {
      fuzzy = Math.floor(delta / month) + ' months ago';
  } else if (Math.floor(delta / year) == 1) {
      fuzzy = '1 year ago';
  } else {
      fuzzy = Math.floor(delta / year) + ' years ago';
  }
  return fuzzy;
}
