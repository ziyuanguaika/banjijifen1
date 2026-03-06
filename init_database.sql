-- ============================================================
-- 数据库初始化脚本 - init_database.sql
-- 班级积分系统 · MySQL版
-- ============================================================
-- 使用方法：
--   mysql -h 192.168.10.120 -P 3306 -u hrzxuser -p < init_database.sql
-- ============================================================

-- -------------------- 创建数据库 --------------------
CREATE DATABASE IF NOT EXISTS `class_points`
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE `class_points`;

-- ============================================================
-- 表：classes（班级账号表）
-- 对应原 Supabase 中的 classes 表
-- ============================================================
CREATE TABLE IF NOT EXISTS `classes` (
    `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '自增主键',
    `class_id`   VARCHAR(50)  NOT NULL                COMMENT '班级唯一标识（登录用）',
    `class_name` VARCHAR(100) NOT NULL                COMMENT '班级名称（如：高一(3)班）',
    `password`   VARCHAR(100) NOT NULL                COMMENT '登录密码（明文，与原版一致）',
    `role`       VARCHAR(20)  NOT NULL DEFAULT 'teacher' COMMENT '角色（teacher=教师）',
    `created_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
    `updated_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_class_id_role` (`class_id`, `role`),  -- 同一 class_id 同一角色唯一
    KEY `idx_class_id` (`class_id`)                       -- 登录查询索引
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci
  COMMENT='班级账号表';

-- ============================================================
-- 表：students（学生信息表）
-- 对应原 Supabase 中的 students 表
-- ============================================================
CREATE TABLE IF NOT EXISTS `students` (
    `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '自增主键',
    `class_id`    VARCHAR(50)  NOT NULL                COMMENT '所属班级ID（关联 classes.class_id）',
    `student_id`  VARCHAR(50)  NOT NULL                COMMENT '学生编号（班内唯一）',
    `name`        VARCHAR(50)  NOT NULL                COMMENT '学生姓名',
    `points`      INT          NOT NULL DEFAULT 0      COMMENT '当前积分',
    `avatar`      TEXT                                 COMMENT '头像（Base64 或 URL）',
    `group_name`  VARCHAR(50)           DEFAULT NULL   COMMENT '所属小组名称',
    `created_at`  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
    `updated_at`  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_class_student` (`class_id`, `student_id`),
    KEY `idx_class_id` (`class_id`)
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci
  COMMENT='学生信息表';

-- ============================================================
-- 表：point_rules（积分规则表）
-- 对应原 Supabase 中的 point_rules 表
-- ============================================================
CREATE TABLE IF NOT EXISTS `point_rules` (
    `id`          INT UNSIGNED  NOT NULL AUTO_INCREMENT COMMENT '自增主键',
    `class_id`    VARCHAR(50)   NOT NULL               COMMENT '所属班级ID',
    `rule_name`   VARCHAR(100)  NOT NULL               COMMENT '规则名称（如：回答问题）',
    `points`      INT           NOT NULL DEFAULT 0     COMMENT '加减分值（正数加分，负数减分）',
    `category`    VARCHAR(50)            DEFAULT NULL  COMMENT '规则分类',
    `created_at`  DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_class_id` (`class_id`)
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci
  COMMENT='积分规则表';

-- ============================================================
-- 表：point_history（积分历史记录表）
-- 对应原 Supabase 中的 point_history 表
-- ============================================================
CREATE TABLE IF NOT EXISTS `point_history` (
    `id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '自增主键',
    `class_id`    VARCHAR(50)     NOT NULL               COMMENT '所属班级ID',
    `student_id`  VARCHAR(50)     NOT NULL               COMMENT '学生编号',
    `rule_name`   VARCHAR(100)             DEFAULT NULL  COMMENT '触发规则名称',
    `points`      INT             NOT NULL               COMMENT '本次变化积分',
    `note`        VARCHAR(255)             DEFAULT NULL  COMMENT '备注',
    `created_at`  DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '发生时间',
    PRIMARY KEY (`id`),
    KEY `idx_class_student` (`class_id`, `student_id`),
    KEY `idx_created_at` (`created_at`)
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci
  COMMENT='积分变更历史记录表';

-- ============================================================
-- 表：rewards（兑换商品/奖励表）
-- 对应原 Supabase 中的 rewards 表
-- ============================================================
CREATE TABLE IF NOT EXISTS `rewards` (
    `id`          INT UNSIGNED  NOT NULL AUTO_INCREMENT COMMENT '自增主键',
    `class_id`    VARCHAR(50)   NOT NULL               COMMENT '所属班级ID',
    `reward_name` VARCHAR(100)  NOT NULL               COMMENT '奖励名称',
    `cost_points` INT UNSIGNED  NOT NULL DEFAULT 0     COMMENT '兑换所需积分',
    `stock`       INT                    DEFAULT NULL  COMMENT '库存数量（NULL表示无限）',
    `created_at`  DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_class_id` (`class_id`)
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci
  COMMENT='奖励兑换商品表';

-- ============================================================
-- 示例数据：插入一个测试班级账号
-- 登录时使用：班级ID = demo_class，密码 = demo123
-- （正式使用前请删除或修改此测试账号）
-- ============================================================
INSERT IGNORE INTO `classes` (`class_id`, `class_name`, `password`, `role`)
VALUES ('demo_class', '演示班级', 'demo123', 'teacher');

-- ============================================================
-- 完成提示
-- ============================================================
SELECT '✅ 数据库初始化完成！' AS 结果;
SELECT
    TABLE_NAME   AS 表名,
    TABLE_COMMENT AS 说明
FROM information_schema.TABLES
WHERE TABLE_SCHEMA = 'class_points'
ORDER BY TABLE_NAME;
