<?php
// Set correct HTTP response code
http_response_code(404);

$year = date("Y");
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>404 | Texol</title>
  <link rel="icon" type="image/svg+xml" href="https://www.texolenergies.com/assets/Texol_icon-AiPT1Z13.png">

  <style>
    * {
      margin: 0;
      padding: 0;
      box-sizing: border-box;
      font-family: "Segoe UI", Tahoma, sans-serif;
    }

    body {
      min-height: 100vh;
      background: linear-gradient(135deg, #0f2027, #203a43, #2c5364);
      color: #ffffff;
      display: flex;
      align-items: center;
      justify-content: center;
      text-align: center;
    }

    .container {
      max-width: 520px;
      padding: 40px;
      animation: fadeIn 1.1s ease-in-out;
    }

    h1 {
      font-size: 120px;
      color: #00e6e6;
      line-height: 1;
      transition: transform 0.4s ease;
    }

    h2 {
      font-size: 28px;
      margin-top: 10px;
    }

    p {
      margin: 18px 0 28px;
      font-size: 16px;
      color: #d6d6d6;
    }

    .btn {
      padding: 12px 30px;
      background: #00e6e6;
      color: #000;
      border: none;
      font-weight: 600;
      border-radius: 30px;
      cursor: pointer;
      transition: all 0.3s ease;
    }

    .btn:hover {
      transform: translateY(-3px);
      box-shadow: 0 8px 20px rgba(0, 230, 230, 0.4);
    }

    .brand {
      margin-top: 35px;
      font-size: 14px;
      opacity: 0.75;
    }

    @keyframes fadeIn {
      from {
        opacity: 0;
        transform: translateY(20px);
      }
      to {
        opacity: 1;
        transform: translateY(0);
      }
    }
  </style>
</head>
<body>

  <div class="container">
    <h1 id="code">404</h1>
    <h2>Page Not Found</h2>

    <p>
      Sorry, the page you’re looking for doesn’t exist or has been moved.
    </p>

    <button onclick="goBack()" class="btn">
      Go Back
    </button>

    <div class="brand">
      © <?= $year ?> Texol
    </div>
  </div>

  <script>
    // Subtle pulse animation for 404 text
    const code = document.getElementById("code");
    let grow = false;

    setInterval(() => {
      grow = !grow;
      code.style.transform = grow ? "scale(1.05)" : "scale(1)";
    }, 900);

    // Go back safely
    function goBack() {
      if (window.history.length > 1) {
        window.history.back();
      } else {
        window.location.href = "/";
      }
    }
  </script>

</body>
</html>
