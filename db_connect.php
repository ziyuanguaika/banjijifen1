<?php
/**
 * ============================================================
 * 数据库连接文件 - db_connect.php
 * 班级积分系统 · MySQL版
 * ============================================================
 * 运行环境：PHP-CGI（端口 9000）
 * 数据库：MySQL 5.7+
 * 字符集：utf8mb4
 * ============================================================
 */

// -------------------- 数据库连接参数配置 --------------------
define('DB_HOST',    '192.168.10.120'); // 数据库服务器地址
define('DB_PORT',    '3306');           // 数据库端口
define('DB_NAME',    'class_points');   // 数据库名称（自定义）
define('DB_USER',    'hrzxuser');       // 数据库用户名
define('DB_PASS',    'Hrzx@1234');      // 数据库密码
define('DB_CHARSET', 'utf8mb4');        // 字符集，支持中文及Emoji

/**
 * 获取 PDO 数据库连接实例
 * 使用 PDO 而非 mysqli，安全性更高，便于参数绑定防注入
 *
 * @return PDO
 * @throws RuntimeException 连接失败时抛出异常
 */
function getDBConnection(): PDO
{
    // DSN（数据源名称）字符串
    $dsn = sprintf(
        'mysql:host=%s;port=%s;dbname=%s;charset=%s',
        DB_HOST,
        DB_PORT,
        DB_NAME,
        DB_CHARSET
    );

    // PDO 选项
    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,  // 异常模式，出错时抛异常
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,         // 默认关联数组返回
        PDO::ATTR_EMULATE_PREPARES   => false,                    // 使用真正的预处理语句
         // 强制字符集
    ];

    try {
        $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        return $pdo;
    } catch (PDOException $e) {
        // 记录错误日志（不向前端暴露详细信息）
        error_log('[DB_CONNECT_ERROR] ' . date('Y-m-d H:i:s') . ' - ' . $e->getMessage());
        throw new RuntimeException('数据库连接失败，请联系管理员');
    }
}

/**
 * 统一 JSON 响应输出
 * 适配 PHP-CGI 运行环境，确保 Header 在任何内容输出前发送
 *
 * @param bool   $success  是否成功
 * @param string $message  提示消息
 * @param array  $data     返回数据
 * @param int    $httpCode HTTP 状态码
 */
function jsonResponse(bool $success, string $message, array $data = [], int $httpCode = 200): void
{
    // PHP-CGI 模式下使用 Status 头替代 http_response_code
    header('Status: ' . $httpCode);
    header('Content-Type: application/json; charset=utf-8');

    // 跨域处理：限定允许的来源（生产环境建议改为具体域名）
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, X-Requested-With');

    // 预检请求直接返回
    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        exit(0);
    }

    echo json_encode([
        'success' => $success,
        'message' => $message,
        'data'    => $data,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}
