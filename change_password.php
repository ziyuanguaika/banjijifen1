<?php
/**
 * ============================================================
 * 修改密码接口 - change_password.php
 * 班级积分系统 · MySQL版
 * ============================================================
 * 接口说明：
 *   POST /change_password.php
 *   Content-Type: application/json 或 application/x-www-form-urlencoded
 *
 * 请求参数：
 *   class_id      班级ID（字符串）
 *   old_password  原密码（字符串）
 *   new_password  新密码（字符串，至少6位）
 *
 * 返回示例（成功）：
 *   {"success":true,"message":"密码修改成功","data":[]}
 *
 * 运行环境：PHP-CGI（端口 9000）
 * ============================================================
 */

require_once __DIR__ . '/db_connect.php';

// -------------------- 仅允许 POST 请求 --------------------
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(false, '仅支持 POST 请求', [], 405);
}

// -------------------- 解析请求体 --------------------
$contentType = $_SERVER['CONTENT_TYPE'] ?? '';
if (stripos($contentType, 'application/json') !== false) {
    $rawBody    = file_get_contents('php://input');
    $body       = json_decode($rawBody, true);
    $classId    = trim($body['class_id']     ?? '');
    $oldPassword = trim($body['old_password'] ?? '');
    $newPassword = trim($body['new_password'] ?? '');
} else {
    $classId    = trim($_POST['class_id']     ?? '');
    $oldPassword = trim($_POST['old_password'] ?? '');
    $newPassword = trim($_POST['new_password'] ?? '');
}

// -------------------- 参数校验（与原 Supabase 版本逻辑一致） --------------------
if (empty($classId) || empty($oldPassword) || empty($newPassword)) {
    jsonResponse(false, '请填写完整信息', [], 400);
}

// 新密码长度限制（对应原版 newPassword.length < 6 校验）
if (mb_strlen($newPassword) < 6) {
    jsonResponse(false, '新密码至少需要6位', [], 400);
}

// -------------------- 数据库操作 --------------------
try {
    $pdo = getDBConnection();

    // 第一步：查询原密码（对应原版 Supabase .select('password') 查询）
    $stmt = $pdo->prepare(
        "SELECT password FROM classes
         WHERE class_id = :class_id AND role = 'teacher'
         LIMIT 1"
    );
    $stmt->execute([':class_id' => $classId]);
    $row = $stmt->fetch();

    if (!$row) {
        jsonResponse(false, '验证失败，请重新登录', [], 401);
    }

    // 第二步：验证原密码（对应原版 classData.password !== oldPassword）
    if ($row['password'] !== $oldPassword) {
        jsonResponse(false, '原密码错误', [], 401);
    }

    // 第三步：更新新密码（对应原版 .update({ password: newPassword })）
    $updateStmt = $pdo->prepare(
        "UPDATE classes SET password = :new_password
         WHERE class_id = :class_id AND role = 'teacher'"
    );
    $updateStmt->execute([
        ':new_password' => $newPassword,
        ':class_id'     => $classId,
    ]);

    jsonResponse(true, '密码修改成功', [], 200);

} catch (RuntimeException $e) {
    jsonResponse(false, $e->getMessage(), [], 500);
} catch (PDOException $e) {
    error_log('[CHANGE_PWD_ERROR] ' . date('Y-m-d H:i:s') . ' - ' . $e->getMessage());
    jsonResponse(false, '服务器内部错误，请稍后重试', [], 500);
}
