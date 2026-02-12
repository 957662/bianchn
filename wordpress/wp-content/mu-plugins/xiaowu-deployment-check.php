<?php
/**
 * 小伍博客 - 部署状态检测
 *
 * 这个插件在 WordPress 加载前运行，检测是否为首次部署
 * - 首次部署：显示部署向导
 * - 已部署：正常显示博客
 *
 * @package Xiaowu_Deployment
 * @since 2.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * 检测部署状态并重定向
 */
function xiaowu_check_deployment_status()
{
    // 如果在管理后台，不进行检测
    if (is_admin() || wp_installing()) {
        return;
    }

    // 如果已经标记为完成，不进行检测
    if (get_option('xiaowu_deployment_completed')) {
        return;
    }

    // 检查 WordPress 是否已安装（是否有管理员用户）
    $admins = get_users(array(
        'role'   => 'administrator',
        'number' => 1
    ));

    // 如果有管理员用户，说明已安装
    if (!empty($admins)) {
        // 自动标记部署完成
        update_option('xiaowu_deployment_completed', current_time('mysql'));
        return;
    }

    // 未安装，显示部署向导
    // 仅在首页显示部署页面
    $current_url = home_url($_SERVER['REQUEST_URI']);
    $home_url = home_url('/');

    // 如果访问的是首页或根目录，显示部署页面
    if ($current_url === $home_url || $current_url === trailingslashit($home_url)) {
        // 加载部署向导模板
        require_once dirname(__FILE__) . '/../plugins/xiaowu-deployment/admin/deployment-landing.php';
        exit;
    }
}

// 在 WordPress 完全加载后执行检测
add_action('wp_loaded', 'xiaowu_check_deployment_status', 1);
