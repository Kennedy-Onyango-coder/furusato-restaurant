<?php
http_response_code(403);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>403 - Access Forbidden | Furusato Japanese Restaurant</title>
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@400;500;600;700&family=DM+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'DM Sans', sans-serif;
            background: #faf8f4;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            text-align: center;
        }
        .error-container { max-width: 500px; padding: 40px 20px; }
        .error-code { font-size: 6rem; font-weight: 700; color: #c9a03d; line-height: 1; }
        h1 { font-family: 'Cormorant Garamond', serif; font-size: 1.8rem; margin: 20px 0 10px; }
        p { color: #6c757d; margin-bottom: 30px; }
        .btn-home {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            padding: 12px 32px;
            background: #0d1b2a;
            color: white;
            text-decoration: none;
            border-radius: 50px;
            font-weight: 600;
            transition: all 0.3s;
        }
        .btn-home:hover { background: #c9a03d; color: #0d1b2a; transform: translateY(-3px); }
    </style>
</head>
<body>
    <div class="error-container">
        <div class="error-code">403</div>
        <h1>Access Forbidden</h1>
        <p>You don't have permission to access this page.</p>
        <a href="/" class="btn-home"><i class="fas fa-home"></i> Return to Homepage</a>
    </div>
</body>
</html>