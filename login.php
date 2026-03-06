<?php
/**
 * ============================================================
 * 登录接口 - login.php
 * 班级积分系统 · MySQL版
 * ============================================================
 * 接口说明：
 *   POST /login.php
 *   Content-Type: application/json 或 application/x-www-form-urlencoded
 *
 * 请求参数：
 *   class_id  班级ID（字符串）
 *   password  密码（字符串）
 *
 * 返回示例（成功）：
 *   {"success":true,"message":"登录成功","data":{"class_id":"...","class_name":"...","role":"teacher"}}
 *
 * 返回示例（失败）：
 *   {"success":false,"message":"密码错误，请重试","data":[]}
 *
 * 运行环境：PHP-CGI（端口 9000）
 * ============================================================
 */

// 引入数据库连接文件（路径与本文件同目录）
require_once __DIR__ . '/db_connect.php';

// -------------------- 仅允许 POST 请求 --------------------
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(false, '仅支持 POST 请求', [], 405);
}

// -------------------- 解析请求体 --------------------
// 支持 JSON 格式和传统表单格式两种提交方式
$contentType = $_SERVER['CONTENT_TYPE'] ?? '';
if (stripos($contentType, 'application/json') !== false) {
    // JSON 格式提交（前端 fetch/axios 常用）
    $rawBody = file_get_contents('php://input');
    $body    = json_decode($rawBody, true);
    $classId  = trim($body['class_id'] ?? '');
    $password = trim($body['password'] ?? '');
} else {
    // 传统表单格式提交
    $classId  = trim($_POST['class_id'] ?? '');
    $password = trim($_POST['password'] ?? '');
}

// -------------------- 参数校验 --------------------
if (empty($classId) || empty($password)) {
    jsonResponse(false, '请输入完整信息', [], 400);
}

// 防止超长输入（与原 Supabase 版本保持一致的安全边界）
if (mb_strlen($classId) > 50 || mb_strlen($password) > 100) {
    jsonResponse(false, '输入内容过长', [], 400);
}

// -------------------- 数据库登录校验 --------------------
// 与原 Supabase 版本逻辑一致：
//   1. 根据 class_id 和 role='teacher' 查找班级记录
//   2. 比对密码明文（与原版一致，如需升级可改为 password_verify）
try {
    $pdo = getDBConnection();

    // 使用预处理语句防止 SQL 注入
    // 查询条件：class_id 匹配 且 role 为 teacher（对应原 Supabase .eq('role','teacher')）
    $stmt = $pdo->prepare(
        "SELECT class_id, class_name, role, password
         FROM classes
         WHERE class_id = :class_id
           AND role = 'teacher'
         LIMIT 1"
    );
    $stmt->execute([':class_id' => $classId]);
    $row = $stmt->fetch(); // 默认 FETCH_ASSOC，返回关联数组

    // 班级不存在
    if (!$row) {
        jsonResponse(false, '班级不存在，请检查班级ID是否正确', [], 401);
    }

    // 密码校验（明文比对，与原 Supabase 版本 data.password !== password 逻辑一致）
    if ($row['password'] !== $password) {
        jsonResponse(false, '密码错误，请重试', [], 401);
    }

    // 登录成功，返回班级信息（不返回密码字段）
    jsonResponse(true, '登录成功', [
        'class_id'   => $row['class_id'],
        'class_name' => $row['class_name'],
        'role'       => $row['role'],
    ], 200);

} catch (RuntimeException $e) {
    // 数据库连接失败（db_connect.php 已记录详细日志）
    jsonResponse(false, $e->getMessage(), [], 500);
} catch (PDOException $e) {
    error_log('[LOGIN_ERROR] ' . date('Y-m-d H:i:s') . ' - ' . $e->getMessage());
    jsonResponse(false, '服务器内部错误，请稍后重试', [], 500);
}
