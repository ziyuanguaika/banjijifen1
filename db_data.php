<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

$servername = "localhost";
$username = "root";
$password = "123456";
$dbname = "class_points";

$table = $_GET['table'] ?? 'history';

// 表名映射
$tableMap = [
    'history' => 'point_history',
    'students' => 'students',
    'classes' => 'class_info'
];

$actualTable = $tableMap[$table] ?? $table;

try {
    $conn = new PDO("mysql:host=$servername;dbname=$dbname;charset=utf8mb4", $username, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    if ($table === 'classes') {
        $stmt = $conn->query("SELECT keyName, value FROM $actualTable");
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } else {
        $stmt = $conn->query("SELECT * FROM $actualTable ORDER BY id DESC LIMIT 500");
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    echo json_encode($data);
} catch(PDOException $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
?>
