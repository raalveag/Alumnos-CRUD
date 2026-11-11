

<?php
// Compatible con PHP 5.6.23

$host = '127.0.0.1';
$dbname = 'escuela_crud';
$username = 'root';
$password = ''; // Configuración habitual de XAMPP local

try {
    $pdo = new PDO(
        'mysql:host=' . $host . ';dbname=' . $dbname . ';charset=utf8mb4',
        $username,
        $password,
        array(
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false
        )
    );
} catch (PDOException $e) {
    error_log($e->getMessage());
    http_response_code(500);
    exit('Error de conexión. Verifica que MySQL esté iniciado y que la configuración sea correcta.');
}