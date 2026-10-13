<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($title ?? 'Home'); ?></title>
    <link rel="stylesheet" href="/assets/css/style.css">
</head>
<body>
    <header>
        <nav>
            <ul>
                <li><a href="/">Home</a></li>
                <li><a href="/user">Users</a></li>
            </ul>
        </nav>
    </header>
    
    <main>
        <h1><?php echo htmlspecialchars($message ?? 'Welcome'); ?></h1>
        <p>This is the home page of your PHP application.</p>
    </main>
    
    <footer>
        <p>&copy; <?php echo date('Y'); ?> Your Application</p>
    </footer>
    
    <script src="/assets/js/main.js"></script>
</body>
</html>

