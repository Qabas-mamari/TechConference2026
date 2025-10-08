<?php
$DB_HOST = 'localhost';
$DB_NAME = 'chat_system';
$DB_USER = 'root';   // اسم المستخدم الصحيح في XAMPP
$DB_PASS = '';       // لا توجد كلمة مرور عادة في XAMPP

// كلمة مرور المدير (يمكنك تغييرها)
$ADMIN_PASS = '123';

try {
    $pdo = new PDO(
        "mysql:host=$DB_HOST;dbname=$DB_NAME;charset=utf8mb4",
        $DB_USER,
        $DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]
    );
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database connection failed: ' . $e->getMessage()]);
    exit;
}
?>
