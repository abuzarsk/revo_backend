<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($title ?? 'Users'); ?></title>
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
        <h1><?php echo htmlspecialchars($title ?? 'Users'); ?></h1>
        
        <?php if (isset($user)): ?>
            <!-- Single user view -->
            <div class="user-detail">
                <h2>User Details</h2>
                <p><strong>ID:</strong> <?php echo htmlspecialchars($user['id']); ?></p>
                <p><strong>Name:</strong> <?php echo htmlspecialchars($user['name']); ?></p>
                <p><strong>Email:</strong> <?php echo htmlspecialchars($user['email']); ?></p>
            </div>
        <?php elseif (isset($users)): ?>
            <!-- Users list view -->
            <div class="users-list">
                <h2>All Users</h2>
                <ul>
                    <?php foreach ($users as $user): ?>
                        <li>
                            <a href="/user/<?php echo htmlspecialchars($user['id']); ?>">
                                <?php echo htmlspecialchars($user['name']); ?> - <?php echo htmlspecialchars($user['email']); ?>
                            </a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>
    </main>
    
    <footer>
        <p>&copy; <?php echo date('Y'); ?> Your Application</p>
    </footer>
    
    <script src="/assets/js/main.js"></script>
</body>
</html>

