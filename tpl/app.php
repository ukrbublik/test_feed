<!DOCTYPE html>
<html lang="en">
<head>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta charset="utf-8">
  <title>Test Feed</title>

  <link rel="stylesheet" href="https://fonts.googleapis.com/icon?family=Material+Icons">
  <link rel="stylesheet" href="css/bootstrap/css/bootstrap.css">
  <link rel="stylesheet" href="css/bootstrap/css/bootstrap-theme.css">
  <link rel="stylesheet" href="css/app.css">
</head>
<body>

  <!-- Top nav -->
  <nav class="navbar navbar-inverse navbar-fixed-top">
    <div class="container">
      <div class="navbar-header">
        <button type="button" class="navbar-toggle collapsed" data-toggle="collapse" data-target="#navbar" aria-expanded="false" aria-controls="navbar">
          <span class="sr-only">Toggle navigation</span>
          <span class="icon-bar"></span>
          <span class="icon-bar"></span>
          <span class="icon-bar"></span>
        </button>
        <span class="navbar-brand">Test Feed</span>
      </div>
      <div id="navbar" class="collapse navbar-collapse">
        <ul class="nav navbar-nav">
          <li><a><span class="label label-default twitter-is-online">Offline</span></a></li>
          <li class='twitter-nonauthed'><a id='twitter-login' href="oauth.php?type=twitter"><i class='icon-twitter'></i>Login</a></li>
          <li class='twitter-authed'><a href="http://twitter.com/" target='_blank'><span id='twitter-username' class='label label-primary'>@<?=$this->e($twitterLogin)?></span></a></li>
          <li class='twitter-authed'><a id='twitter-logout' href="oauth.php?type=twitter&action=logout"><i class='icon-twitter'></i>Logout</a></li>
        </ul>
      </div>
    </div>
  </nav>

  <!-- Main content -->
  <div class="container">

    <div id="toast" class="alert" role="alert"></div>

    <div class="list-group" id="feed"></div>

  </div>

  <script>
    window.app = <?php echo json_encode($app) ?>;
    console.log(window.app);
  </script>

  <!-- Load js -->
  <script src="js/jquery.js"></script>
  <script src="js/jquery.cookie.js"></script>
  <script src="css/bootstrap/js/bootstrap.js"></script>
  <script src="js/app.js"></script>
</body>
</html>