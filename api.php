<?php
/**
 * ============================================================
 * 统一数据接口 - api.php
 * 班级积分系统 · MySQL版
 * ============================================================
 * 所有数据读写操作通过此文件处理
 * 请求方式：POST，Content-Type: application/json
 * 请求参数：action（操作类型）+ 对应数据
 * ============================================================
 */

require_once __DIR__ . '/db_connect.php';

// 仅允许 POST 请求
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: POST, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type');
    exit(0);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(false, '仅支持 POST 请求', [], 405);
}

// 解析请求体
$rawBody = file_get_contents('php://input');
$body = json_decode($rawBody, true);

if (!$body) {
    jsonResponse(false, '无效的请求数据', [], 400);
}

$action   = $body['action']   ?? '';
$classId  = $body['class_id'] ?? '';

if (empty($action) || empty($classId)) {
    jsonResponse(false, '缺少必要参数', [], 400);
}

try {
    $pdo = getDBConnection();

    switch ($action) {

        // ==================== 加载所有数据 ====================
        case 'load_all':
            $result = [];

            // 学生数据
            $stmt = $pdo->prepare("SELECT * FROM students WHERE class_id = :class_id ORDER BY student_id");
            $stmt->execute([':class_id' => $classId]);
            $result['students'] = $stmt->fetchAll();

            // 积分规则
            $stmt = $pdo->prepare("SELECT * FROM point_rules WHERE class_id = :class_id");
            $stmt->execute([':class_id' => $classId]);
            $result['point_rules'] = $stmt->fetchAll();

            // 奖励商品
            $stmt = $pdo->prepare("SELECT * FROM rewards WHERE class_id = :class_id");
            $stmt->execute([':class_id' => $classId]);
            $result['rewards'] = $stmt->fetchAll();

            // 历史记录（最多加载5000条）
            $stmt = $pdo->prepare("SELECT * FROM point_history WHERE class_id = :class_id ORDER BY created_at DESC LIMIT 5000");
            $stmt->execute([':class_id' => $classId]);
            $result['history'] = $stmt->fetchAll();

            // 背诵完成数据
            $stmt = $pdo->prepare("SELECT student_id FROM recitation_completed WHERE class_id = :class_id");
            $stmt->execute([':class_id' => $classId]);
            $result['recitation_completed'] = array_column($stmt->fetchAll(), 'student_id');

            jsonResponse(true, '加载成功', $result);
            break;

        // ==================== 同步学生数据 ====================
        case 'sync_students':
            $students = $body['students'] ?? [];
            $pdo->beginTransaction();

            // 删除旧数据
            $stmt = $pdo->prepare("DELETE FROM students WHERE class_id = :class_id");
            $stmt->execute([':class_id' => $classId]);

            // 插入新数据
            if (!empty($students)) {
                $stmt = $pdo->prepare(
                    "INSERT INTO students (class_id, student_id, name, gender, points, available_points, group_number, avatar, created_at)
                     VALUES (:class_id, :student_id, :name, :gender, :points, :available_points, :group_number, :avatar, NOW())"
                );
                foreach ($students as $s) {
                    $avatar = $s['avatar'] ?? '';
                    // 过滤base64头像，只保留emoji
                    if (strpos($avatar, 'data:image') === 0) {
                        $avatar = ($s['gender'] === 'female') ? '👧' : '👦';
                    }
                    $stmt->execute([
                        ':class_id'        => $classId,
                        ':student_id'      => $s['id'] ?? $s['student_id'],
                        ':name'            => $s['name'],
                        ':gender'          => $s['gender'] ?? 'male',
                        ':points'          => $s['points'] ?? 0,
                        ':available_points'=> $s['availablePoints'] ?? $s['available_points'] ?? 0,
                        ':group_number'    => $s['group'] ?? $s['group_number'] ?? 1,
                        ':avatar'          => $avatar,
                    ]);
                }
            }
            $pdo->commit();
            jsonResponse(true, '学生数据已同步', ['count' => count($students)]);
            break;

        // ==================== 同步积分规则 ====================
        case 'sync_rules':
            $rules = $body['rules'] ?? [];
            $pdo->beginTransaction();

            $stmt = $pdo->prepare("DELETE FROM point_rules WHERE class_id = :class_id");
            $stmt->execute([':class_id' => $classId]);

            if (!empty($rules)) {
                $stmt = $pdo->prepare(
                    "INSERT INTO point_rules (id, class_id, rule_name, points, category, created_at)
                     VALUES (:id, :class_id, :rule_name, :points, :category, NOW())"
                );
                $seenIds = [];
                $nextId  = 1;
                foreach ($rules as $r) {
                    $safeId = $r['id'] ?? $r['rule_id'] ?? $nextId;
                    if ($safeId > 2147483647 || in_array($safeId, $seenIds)) {
                        while (in_array($nextId, $seenIds)) $nextId++;
                        $safeId = $nextId++;
                    }
                    $seenIds[] = $safeId;
                    $stmt->execute([
                        ':id'        => $safeId,
                        ':class_id'  => $classId,
                        ':rule_name' => $r['name'],
                        ':points'    => $r['points'],
                        ':category'  => $r['type'] ?? null,
                    ]);
                }
            }
            $pdo->commit();
            jsonResponse(true, '积分规则已同步', ['count' => count($rules)]);
            break;

        // ==================== 同步奖励商品 ====================
        case 'sync_rewards':
            $rewards = $body['rewards'] ?? [];
            $pdo->beginTransaction();

            $stmt = $pdo->prepare("DELETE FROM rewards WHERE class_id = :class_id");
            $stmt->execute([':class_id' => $classId]);

            if (!empty($rewards)) {
                $stmt = $pdo->prepare(
                    "INSERT INTO rewards (id, class_id, reward_name, cost_points, stock, created_at)
                     VALUES (:id, :class_id, :reward_name, :cost_points, :stock, NOW())"
                );
                $seenIds = [];
                $nextId  = 1;
                foreach ($rewards as $r) {
                    $safeId = $r['id'] ?? $r['reward_id'] ?? $nextId;
                    if ($safeId > 2147483647 || in_array($safeId, $seenIds)) {
                        while (in_array($nextId, $seenIds)) $nextId++;
                        $safeId = $nextId++;
                    }
                    $seenIds[] = $safeId;
                    $stmt->execute([
                        ':id'          => $safeId,
                        ':class_id'    => $classId,
                        ':reward_name' => $r['name'],
                        ':cost_points' => $r['points'],
                        ':stock'       => $r['stock'] ?? 99,
                    ]);
                }
            }
            $pdo->commit();
            jsonResponse(true, '商品数据已同步', ['count' => count($rewards)]);
            break;

        // ==================== 同步历史记录 ====================
        case 'sync_history':
            $history = $body['history'] ?? [];
            if (empty($history)) {
                jsonResponse(true, '历史记录为空，跳过同步');
            }
            $pdo->beginTransaction();

            $stmt = $pdo->prepare("DELETE FROM point_history WHERE class_id = :class_id");
            $stmt->execute([':class_id' => $classId]);

            $stmt = $pdo->prepare(
                "INSERT INTO point_history (id, class_id, student_id, rule_name, points, note, created_at)
                 VALUES (:id, :class_id, :student_id, :rule_name, :points, :note, :created_at)"
            );
            foreach ($history as $index => $h) {
                $hId = ($h['id'] ?? 0);
                if ($hId <= 0 || $hId > 9223372036854775807) $hId = $index + 1;
                $time = $h['time'] ?? date('Y-m-d H:i:s');
                if (is_numeric($time)) $time = date('Y-m-d H:i:s', $time / 1000);
                $stmt->execute([
                    ':id'         => $hId,
                    ':class_id'   => $classId,
                    ':student_id' => $h['studentId'] ?? $h['student_id'] ?? null,
                    ':rule_name'  => $h['rule'] ?? $h['reason'] ?? null,
                    ':points'     => $h['points'] ?? 0,
                    ':note'       => $h['rewardName'] ?? $h['reward_name'] ?? null,
                    ':created_at' => $time,
                ]);
            }
            $pdo->commit();
            jsonResponse(true, '历史记录已同步', ['count' => count($history)]);
            break;

        // ==================== 清空历史记录 ====================
        case 'clear_history':
            $stmt = $pdo->prepare("DELETE FROM point_history WHERE class_id = :class_id");
            $stmt->execute([':class_id' => $classId]);
            jsonResponse(true, '历史记录已清空');
            break;

        // ==================== 添加单条历史记录 ====================
        case 'add_history':
            $h    = $body['history_item'] ?? [];
            $time = $h['time'] ?? date('Y-m-d H:i:s');
            if (is_numeric($time)) $time = date('Y-m-d H:i:s', $time / 1000);

            $stmt = $pdo->prepare(
                "INSERT INTO point_history (class_id, student_id, rule_name, points, note, created_at)
                 VALUES (:class_id, :student_id, :rule_name, :points, :note, :created_at)"
            );
            $stmt->execute([
                ':class_id'   => $classId,
                ':student_id' => $h['studentId'] ?? null,
                ':rule_name'  => $h['rule'] ?? null,
                ':points'     => $h['points'] ?? 0,
                ':note'       => $h['rewardName'] ?? null,
                ':created_at' => $time,
            ]);
            jsonResponse(true, '历史记录已添加', ['id' => $pdo->lastInsertId()]);
            break;

        // ==================== 添加商品 ====================
        case 'add_reward':
            $r = $body['reward'] ?? [];
            // 获取最大ID
            $stmt = $pdo->prepare("SELECT MAX(id) as max_id FROM rewards WHERE class_id = :class_id");
            $stmt->execute([':class_id' => $classId]);
            $maxId  = $stmt->fetchColumn() ?: 0;
            $newId  = $maxId + 1;

            $stmt = $pdo->prepare(
                "INSERT INTO rewards (id, class_id, reward_name, cost_points, stock, created_at)
                 VALUES (:id, :class_id, :reward_name, :cost_points, :stock, NOW())"
            );
            $stmt->execute([
                ':id'          => $newId,
                ':class_id'    => $classId,
                ':reward_name' => $r['name'],
                ':cost_points' => $r['points'],
                ':stock'       => $r['stock'] ?? 99,
            ]);
            jsonResponse(true, '商品已添加', ['id' => $newId]);
            break;

        // ==================== 删除商品 ====================
        case 'delete_reward':
            $rewardId = $body['reward_id'] ?? 0;
            $stmt = $pdo->prepare("DELETE FROM rewards WHERE class_id = :class_id AND id = :id");
            $stmt->execute([':class_id' => $classId, ':id' => $rewardId]);
            jsonResponse(true, '商品已删除');
            break;

        // ==================== 更新商品 ====================
        case 'update_reward':
            $r        = $body['reward'] ?? [];
            $rewardId = $body['reward_id'] ?? 0;
            $stmt = $pdo->prepare(
                "UPDATE rewards SET reward_name = :name, cost_points = :points, stock = :stock
                 WHERE class_id = :class_id AND id = :id"
            );
            $stmt->execute([
                ':name'     => $r['name'],
                ':points'   => $r['points'],
                ':stock'    => $r['stock'],
                ':class_id' => $classId,
                ':id'       => $rewardId,
            ]);
            jsonResponse(true, '商品已更新');
            break;

        // ==================== 更新商品库存 ====================
        case 'update_reward_stock':
            $rewardId = $body['reward_id']  ?? 0;
            $newStock = $body['new_stock']   ?? 0;
            $stmt = $pdo->prepare(
                "UPDATE rewards SET stock = :stock WHERE class_id = :class_id AND id = :id"
            );
            $stmt->execute([':stock' => $newStock, ':class_id' => $classId, ':id' => $rewardId]);
            jsonResponse(true, '库存已更新');
            break;

        // ==================== 同步背诵完成数据 ====================
        case 'sync_recitation':
            $completed = $body['completed'] ?? [];
            $pdo->beginTransaction();

            $stmt = $pdo->prepare("DELETE FROM recitation_completed WHERE class_id = :class_id");
            $stmt->execute([':class_id' => $classId]);

            if (!empty($completed)) {
                $stmt = $pdo->prepare(
                    "INSERT INTO recitation_completed (class_id, student_id, completed_at)
                     VALUES (:class_id, :student_id, NOW())"
                );
                foreach ($completed as $studentId) {
                    $stmt->execute([':class_id' => $classId, ':student_id' => $studentId]);
                }
            }
            $pdo->commit();
            jsonResponse(true, '背诵数据已同步', ['count' => count($completed)]);
            break;

        default:
            jsonResponse(false, '未知操作：' . $action, [], 400);
    }

} catch (RuntimeException $e) {
    jsonResponse(false, $e->getMessage(), [], 500);
} catch (PDOException $e) {
    error_log('[API_ERROR] ' . date('Y-m-d H:i:s') . ' action=' . $action . ' - ' . $e->getMessage());
    jsonResponse(false, '数据库操作失败：' . $e->getMessage(), [], 500);
}
