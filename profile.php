<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php?error=unauthorized');
    exit();
}

// Database connection
$conn = new mysqli('localhost', 'root', 'usbw', 'travel_blog');

// Check connection
if ($conn->connect_error) {
    die('Database connection failed: ' . $conn->connect_error);
}

// Get user_id from session
$user_id = $_SESSION['user_id'];

// Fetch user data
$sql_user = "SELECT * FROM users WHERE user_id = ?";
$stmt_user = $conn->prepare($sql_user);
$stmt_user->bind_param('i', $user_id);
$stmt_user->execute();
$result_user = $stmt_user->get_result();

if ($result_user->num_rows > 0) {
    $user = $result_user->fetch_assoc();
} else {
    echo "<p>Error: User not found.</p>";
    exit();
}

// Fetch user posts with images
$sql_posts = "SELECT * FROM posts WHERE user_id = ? ORDER BY created_at DESC";
$stmt_posts = $conn->prepare($sql_posts);
$stmt_posts->bind_param('i', $user_id);
$stmt_posts->execute();
$result_posts = $stmt_posts->get_result();

$posts = [];
if ($result_posts->num_rows > 0) {
    while ($row = $result_posts->fetch_assoc()) {
        $posts[] = $row;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>My Profile</title>
    
    <style>
        body {
            font-family: 'Arial', sans-serif;
            background-color: #f4f7f6;
            margin: 0;
            padding: 0;
        }

        .container {
            max-width: 1200px;
            margin: 20px auto;
            padding: 20px;
        }

        .profile-header {
            display: flex;
            align-items: center;
            justify-content: flex-start;
            background-color: #fff;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
            margin-bottom: 40px;
        }

        .profile-picture {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            object-fit: cover;
            margin-right: 20px;
            border: 3px solid #4CAF50; /* Green border */
        }

        .profile-info h1 {
            font-size: 2rem;
            margin: 10px 0;
            color: #333;
        }

        .profile-info p {
            font-size: 1rem;
            margin: 5px 0;
            color: #555;
        }

        .profile-actions {
            display: flex;
            justify-content: space-around;
            margin-bottom: 20px;
        }

        .profile-actions .btn {
            padding: 10px 20px;
            background-color: #4CAF50;
            color: white;
            border-radius: 5px;
            text-decoration: none;
            font-weight: bold;
            transition: background-color 0.3s;
        }

        .profile-actions .btn:hover {
            background-color: #388E3C;
        }

        .posts-section {
            background-color: #fff;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
        }

        .posts-section h2 {
            font-size: 1.8rem;
            color: #333;
            margin-bottom: 20px;
        }

        .post {
            border-bottom: 1px solid #e0e0e0;
            padding: 20px 0;
        }

        .post:last-child {
            border-bottom: none;
        }

        .post-header h3 {
            font-size: 1.5rem;
            margin-bottom: 10px;
            color: #333;
        }

        .post-header small {
            font-size: 0.9rem;
            color: #777;
        }

        .post-image {
            width: 100%;
            max-height: 500px;
            object-fit: contain;
            margin: 15px 0;
            border-radius: 8px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }

        .post-content {
            font-size: 1rem;
            color: #555;
        }

        .post-details p {
            font-size: 1rem;
            color: #777;
        }

        em {
            color: #999;
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Action Buttons at the Top -->
        <div class="profile-actions">
            <a href="edit_profile.php" class="btn">Edit Profile</a>
            <a href="logout.php" class="btn">Logout</a>
            <a href="home.php" class="btn">Home page</a>
        </div>

        <!-- Profile Header Section -->
        <div class="profile-header">
            <!-- Profile Picture -->
            <?php if (!empty($user['profile_picture'])): ?>
                <img src="<?php echo htmlspecialchars($user['profile_picture']); ?>" alt="Profile Picture" class="profile-picture">
            <?php else: ?>
                <img src="default-profile.png" alt="Default Profile" class="profile-picture">
            <?php endif; ?>

            <!-- Profile Info -->
            <div class="profile-info">
                <h1><?php echo htmlspecialchars($user['username']); ?></h1>
                <p><strong>Email:</strong> <?php echo htmlspecialchars($user['email'] ?? 'Not provided'); ?></p>
                <?php if (!empty($user['bio'])): ?>
                    <p><strong>Bio:</strong> <?php echo htmlspecialchars($user['bio']); ?></p>
                <?php else: ?>
                    <p><em>No bio available</em></p>
                <?php endif; ?>
            </div>
        </div>

        <!-- Inside the Post Section -->
<div class="posts-section">
    <h2>My Posts</h2>
    
    <?php if (empty($posts)): ?>
        <p>You haven't made any posts yet.</p>
    <?php else: ?>
        <?php foreach ($posts as $post): ?>
            <div class="post">
                <div class="post-header">
                    <h3><?php echo htmlspecialchars($post['title'] ?? 'Untitled Post'); ?></h3>
                    <small><?php echo date('F j, Y, g:i a', strtotime($post['created_at'])); ?></small>
                </div>

                <!-- Check if image path exists and is not empty -->
                <?php 
                $imagePath = $post['image'];
                if (!empty($imagePath)): 
                    // Assuming images are stored in the 'uploads/' folder
                    $fullImagePath = 'uploads/' . basename($imagePath);
                    if (file_exists($fullImagePath)): 
                ?>
                    <img 
                        src="<?php echo htmlspecialchars($fullImagePath); ?>" 
                        alt="Post Image" 
                        class="post-image"
                        onerror="this.style.display='none'; console.error('Image failed to load:', this.src)"
                    >
                <?php else: ?>
                    <p><em>Image not found.</em></p>
                <?php endif; ?>
                <?php endif; ?>

                <!-- Post Content -->
                <p><?php echo htmlspecialchars($post['content']); ?></p>

                <!-- Additional Post Details -->
                <div class="post-details">
                    <?php if (!empty($post['location'])): ?>
                        <p><strong>Location:</strong> <?php echo htmlspecialchars($post['location']); ?></p>
                    <?php endif; ?>
                    <?php if (!empty($post['category'])): ?>
                        <p><strong>Category:</strong> <?php echo htmlspecialchars($post['category']); ?></p>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>
</body>
</html>
