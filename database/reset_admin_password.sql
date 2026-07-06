-- Reset / buat ulang akun admin default.
-- Jalankan di phpMyAdmin pada database hosting jika login admin gagal.
--
-- Username: admin
-- Password: password

INSERT INTO users (username, password, full_name, email, role)
VALUES (
    'admin',
    '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
    'Administrator',
    'admin@pesantren.id',
    'admin'
)
ON DUPLICATE KEY UPDATE
    password = VALUES(password),
    full_name = VALUES(full_name),
    email = VALUES(email),
    role = VALUES(role),
    updated_at = CURRENT_TIMESTAMP;
