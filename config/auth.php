<?php
/**
 * Authentication Helper
 * Fungsi untuk mengelola sesi login admin
 * 
 * @package IoT Pesantren
 */

// Mulai session jika belum dimulai
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Cek apakah user sudah login
 * @return bool
 */
function isLoggedIn() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

/**
 * Redirect ke halaman login jika belum login
 * Panggil di awal setiap halaman admin
 */
function requireLogin() {
    if (!isLoggedIn()) {
        // Tentukan path relatif ke login.php
        $loginPath = '/SISTEM IOT PESANTREN/login.php';
        header("Location: $loginPath");
        exit();
    }
}

/**
 * Dapatkan data user yang sedang login
 * @param mysqli $conn Koneksi database
 * @return array|null Data user atau null
 */
function getCurrentUser($conn) {
    if (!isLoggedIn()) return null;
    
    $userId = $_SESSION['user_id'];
    $stmt = $conn->prepare("SELECT id, username, full_name, email, role FROM users WHERE id = ?");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    $stmt->close();
    
    return $user;
}

/**
 * Login user
 * @param mysqli $conn Koneksi database
 * @param string $username Username
 * @param string $password Password (plain text)
 * @return array ['success' => bool, 'message' => string]
 */
function loginUser($conn, $username, $password) {
    $stmt = $conn->prepare("SELECT id, username, password, full_name, role FROM users WHERE username = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        $stmt->close();
        return ['success' => false, 'message' => 'Username tidak ditemukan'];
    }
    
    $user = $result->fetch_assoc();
    $stmt->close();
    
    if (!password_verify($password, $user['password'])) {
        return ['success' => false, 'message' => 'Password salah'];
    }
    
    // Set session
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['username'] = $user['username'];
    $_SESSION['full_name'] = $user['full_name'];
    $_SESSION['role'] = $user['role'];
    $_SESSION['login_time'] = time();
    
    return ['success' => true, 'message' => 'Login berhasil'];
}

/**
 * Logout user
 */
function logoutUser() {
    $_SESSION = [];
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
    session_destroy();
}

/**
 * Generate CSRF token
 * @return string Token
 */
function generateCSRFToken() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Validasi CSRF token
 * @param string $token Token dari form
 * @return bool
 */
function validateCSRFToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}
