<?php
/**
 * WordPress基础配置文件
 *
 * 本文件包含以下配置选项： MySQL设置、数据库表名前缀、密钥、
 * WordPress语言设定以及ABSPATH。
 *
 * @package WordPress
 */

// 加载环境变量
if (file_exists(dirname(__FILE__) . '/../.env')) {
    $lines = file(dirname(__FILE__) . '/../.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) {
            continue;
        }
        if (strpos($line, '=') !== false) {
            list($name, $value) = explode('=', $line, 2);
            $name = trim($name);
            $value = trim($value);
            putenv("$name=$value");
            $_ENV[$name] = $value;
            $_SERVER[$name] = $value;
        }
    }
}

// ** MySQL 设置 ** //
/** WordPress数据库的名称 */
define('DB_NAME', getenv('DB_NAME') ?: 'xiaowu_blog');

/** MySQL数据库用户名 */
define('DB_USER', getenv('DB_USER') ?: 'xiaowu_user');

/** MySQL数据库密码 */
define('DB_PASSWORD', getenv('DB_PASSWORD') ?: 'your_secure_password');

/** MySQL主机 */
define('DB_HOST', getenv('DB_HOST') ?: 'localhost');

/** 创建数据表时默认的文字编码 */
define('DB_CHARSET', getenv('DB_CHARSET') ?: 'utf8mb4');

/** 数据库整理类型 */
define('DB_COLLATE', '');

/**
 * 身份验证密钥与盐
 *
 * 您可以访问 {@link https://api.wordpress.org/secret-key/1.1/salt/ WordPress.org 密钥服务}
 * 生成随机的密钥和盐。
 */
define('AUTH_KEY',         'put your unique phrase here');
define('SECURE_AUTH_KEY',  'put your unique phrase here');
define('LOGGED_IN_KEY',    'put your unique phrase here');
define('NONCE_KEY',        'put your unique phrase here');
define('AUTH_SALT',        'put your unique phrase here');
define('SECURE_AUTH_SALT', 'put your unique phrase here');
define('LOGGED_IN_SALT',   'put your unique phrase here');
define('NONCE_SALT',       'put your unique phrase here');

/**
 * WordPress 数据表前缀
 */
$table_prefix = 'wp_';

/**
 * Redis 缓存配置
 */
define('WP_REDIS_HOST', getenv('REDIS_HOST') ?: '127.0.0.1');
define('WP_REDIS_PORT', getenv('REDIS_PORT') ?: 6379);
if (getenv('REDIS_PASSWORD')) {
    define('WP_REDIS_PASSWORD', getenv('REDIS_PASSWORD'));
}
define('WP_REDIS_DATABASE', getenv('REDIS_DB') ?: 0);

/**
 * 开发者专用：WordPress调试模式
 */
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
define('WP_DEBUG_DISPLAY', false);

/**
 * AI服务配置
 */
define('XIAOWU_AI_PROVIDER', getenv('AI_PROVIDER') ?: 'openai');
define('XIAOWU_AI_API_KEY', getenv('AI_API_KEY'));
define('XIAOWU_AI_MODEL', getenv('AI_MODEL') ?: 'gpt-4');
define('XIAOWU_AI_MAX_TOKENS', getenv('AI_MAX_TOKENS') ?: 4000);
define('XIAOWU_AI_TEMPERATURE', getenv('AI_TEMPERATURE') ?: 0.7);

/**
 * 图像生成配置
 */
define('XIAOWU_IMG_GEN_PROVIDER', getenv('IMG_GEN_PROVIDER') ?: 'dall-e');
define('XIAOWU_IMG_GEN_API_KEY', getenv('IMG_GEN_API_KEY'));

/**
 * CDN配置
 */
define('XIAOWU_CDN_PROVIDER', getenv('CDN_PROVIDER') ?: 'tencent');
define('XIAOWU_CDN_SECRET_ID', getenv('CDN_SECRET_ID'));
define('XIAOWU_CDN_SECRET_KEY', getenv('CDN_SECRET_KEY'));
define('XIAOWU_CDN_BUCKET', getenv('CDN_BUCKET'));
define('XIAOWU_CDN_REGION', getenv('CDN_REGION') ?: 'ap-shanghai');

/**
 * 上传文件路径配置
 */
define('UPLOADS', 'wp-content/uploads');

/** 绝对路径 */
if (!defined('ABSPATH')) {
    define('ABSPATH', dirname(__FILE__) . '/');
}

/** 设置WordPress变量和包含文件 */
require_once ABSPATH . 'wp-settings.php';
