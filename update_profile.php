<?php
session_start();

// Конектирање со базата
$conn = new mysqli('localhost', 'root', 'usbw', 'travel_blog');

// Проверка на конекцијата
if ($conn->connect_error) {
    die('Database connection failed: ' . $conn->connect_error);
}

// Проверка дали корисникот е логиран
if (!isset($_SESSION['user_id'])) {
    $_SESSION['error'] = "You must be logged in to update your profile.";
    header("Location: index.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Подготви променливи за пораки и upload патека
$profile_picture_path = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Проверка за слика
    if (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] == 0) {
        $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
        $max_file_size = 5 * 1024 * 1024; // 5MB

        $file_type = $_FILES['profile_picture']['type'];
        $file_size = $_FILES['profile_picture']['size'];

        // Проверка на тип и големина на фајлот
        if (!in_array($file_type, $allowed_types)) {
            $_SESSION['error'] = "Invalid file type. Only JPG, PNG, and GIF are allowed.";
            header("Location: edit_profile.php");
            exit();
        }

        if ($file_size > $max_file_size) {
            $_SESSION['error'] = "File is too large. Maximum size is 5MB.";
            header("Location: edit_profile.php");
            exit();
        }

        // Креирање на директориумот за upload ако не постои
        $upload_dir = 'uploads/profile_pictures/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }

        // Генерирање уникатно име за сликата
        $file_extension = pathinfo($_FILES['profile_picture']['name'], PATHINFO_EXTENSION);
        $new_filename = uniqid('profile_', true) . '.' . $file_extension;
        $upload_path = $upload_dir . $new_filename;

        // Преместување на upload фајлот
        if (move_uploaded_file($_FILES['profile_picture']['tmp_name'], $upload_path)) {
            $profile_picture_path = $upload_path;

            // Бришење на старата слика
            $sql_get_old_pic = "SELECT profile_picture FROM users WHERE user_id = ?";
            $stmt = $conn->prepare($sql_get_old_pic);
            $stmt->bind_param('i', $user_id);
            $stmt->execute();
            $stmt->bind_result($old_picture);
            $stmt->fetch();
            $stmt->close();

            if ($old_picture && file_exists($old_picture)) {
                unlink($old_picture);
            }
        } else {
            $_SESSION['error'] = "Failed to upload profile picture.";
            header("Location: edit_profile.php");
            exit();
        }
    }

    // Валидација и обработка на влезните податоци
    $username = trim($_POST['username']);
    $bio = trim($_POST['bio']);

    if (empty($username)) {
        $_SESSION['error'] = "Username cannot be empty.";
        header("Location: edit_profile.php");
        exit();
    }

    // Проверка дали корисничкото име веќе постои
    $sql_check_username = "SELECT user_id FROM users WHERE username = ? AND user_id != ?";
    $stmt = $conn->prepare($sql_check_username);
    $stmt->bind_param('si', $username, $user_id);
    $stmt->execute();
    $stmt->store_result();

    if ($stmt->num_rows > 0) {
        $_SESSION['error'] = "Username is already taken.";
        $stmt->close();
        header("Location: edit_profile.php");
        exit();
    }
    $stmt->close();

    // Подготвување на SQL за ажурирање
    if ($profile_picture_path) {
        $sql_update = "UPDATE users SET username = ?, bio = ?, profile_picture = ? WHERE user_id = ?";
        $stmt = $conn->prepare($sql_update);
        $stmt->bind_param('sssi', $username, $bio, $profile_picture_path, $user_id);
    } else {
        $sql_update = "UPDATE users SET username = ?, bio = ? WHERE user_id = ?";
        $stmt = $conn->prepare($sql_update);
        $stmt->bind_param('ssi', $username, $bio, $user_id);
    }

    // Извршување на ажурирањето
    if ($stmt->execute()) {
        $_SESSION['success'] = "Profile updated successfully!";
        $stmt->close();
        header("Location: profile.php");
        exit();
    } else {
        $_SESSION['error'] = "Failed to update profile. Please try again.";
        $stmt->close();
        header("Location: edit_profile.php");
        exit();
    }
}
$conn->close();
?>

