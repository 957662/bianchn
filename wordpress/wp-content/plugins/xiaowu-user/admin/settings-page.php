<?php
/**
 * 管理后台设置页面
 *
 * @package Xiaowu_User
 */

if (!defined('ABSPATH')) {
    exit;
}

// 处理表单提交
if (isset($_POST['xiaowu_user_settings_nonce']) && wp_verify_nonce($_POST['xiaowu_user_settings_nonce'], 'xiaowu_user_settings')) {
    // 基本设置
    update_option('xiaowu_user_require_email_verification', isset($_POST['require_email_verification']) ? 1 : 0);
    update_option('xiaowu_user_enable_2fa', isset($_POST['enable_2fa']) ? 1 : 0);
    update_option('xiaowu_user_enable_social_login', isset($_POST['enable_social_login']) ? 1 : 0);
    update_option('xiaowu_user_enable_private_messages', isset($_POST['enable_private_messages']) ? 1 : 0);
    update_option('xiaowu_user_enable_follow_system', isset($_POST['enable_follow_system']) ? 1 : 0);

    // 社交登录配置
    update_option('xiaowu_user_wechat_app_id', sanitize_text_field($_POST['wechat_app_id'] ?? ''));
    update_option('xiaowu_user_wechat_app_secret', sanitize_text_field($_POST['wechat_app_secret'] ?? ''));
    update_option('xiaowu_user_qq_app_id', sanitize_text_field($_POST['qq_app_id'] ?? ''));
    update_option('xiaowu_user_qq_app_key', sanitize_text_field($_POST['qq_app_key'] ?? ''));
    update_option('xiaowu_user_github_client_id', sanitize_text_field($_POST['github_client_id'] ?? ''));
    update_option('xiaowu_user_github_client_secret', sanitize_text_field($_POST['github_client_secret'] ?? ''));
    update_option('xiaowu_user_google_client_id', sanitize_text_field($_POST['google_client_id'] ?? ''));
    update_option('xiaowu_user_google_client_secret', sanitize_text_field($_POST['google_client_secret'] ?? ''));

    echo '<div class="notice notice-success"><p>设置已保存</p></div>';
}

// 获取当前设置
$require_email_verification = get_option('xiaowu_user_require_email_verification', 1);
$enable_2fa = get_option('xiaowu_user_enable_2fa', 0);
$enable_social_login = get_option('xiaowu_user_enable_social_login', 1);
$enable_private_messages = get_option('xiaowu_user_enable_private_messages', 1);
$enable_follow_system = get_option('xiaowu_user_enable_follow_system', 1);

$wechat_app_id = get_option('xiaowu_user_wechat_app_id', '');
$wechat_app_secret = get_option('xiaowu_user_wechat_app_secret', '');
$qq_app_id = get_option('xiaowu_user_qq_app_id', '');
$qq_app_key = get_option('xiaowu_user_qq_app_key', '');
$github_client_id = get_option('xiaowu_user_github_client_id', '');
$github_client_secret = get_option('xiaowu_user_github_client_secret', '');
$google_client_id = get_option('xiaowu_user_google_client_id', '');
$google_client_secret = get_option('xiaowu_user_google_client_secret', '');
?>

<div class="wrap xiaowu-user-settings">
    <h1>小伍用户系统设置</h1>

    <form method="post" action="">
        <?php wp_nonce_field('xiaowu_user_settings', 'xiaowu_user_settings_nonce'); ?>

        <div class="xiaowu-tabs">
            <nav class="nav-tab-wrapper">
                <a href="#basic" class="nav-tab nav-tab-active">基本设置</a>
                <a href="#social" class="nav-tab">社交登录</a>
                <a href="#security" class="nav-tab">安全设置</a>
                <a href="#features" class="nav-tab">功能管理</a>
            </nav>

            <!-- 基本设置 -->
            <div id="basic" class="tab-content active">
                <h2>基本设置</h2>
                <table class="form-table">
                    <tr>
                        <th scope="row">邮箱验证</th>
                        <td>
                            <label>
                                <input type="checkbox" name="require_email_verification" value="1" <?php checked($require_email_verification, 1); ?>>
                                要求用户验证邮箱后才能登录
                            </label>
                            <p class="description">启用后,新注册用户需要验证邮箱才能登录系统</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">双因素认证</th>
                        <td>
                            <label>
                                <input type="checkbox" name="enable_2fa" value="1" <?php checked($enable_2fa, 1); ?>>
                                启用双因素认证
                            </label>
                            <p class="description">提供额外的安全保护层</p>
                        </td>
                    </tr>
                </table>
            </div>

            <!-- 社交登录 -->
            <div id="social" class="tab-content">
                <h2>社交登录配置</h2>

                <label>
                    <input type="checkbox" name="enable_social_login" value="1" <?php checked($enable_social_login, 1); ?>>
                    启用社交登录
                </label>

                <h3>微信登录</h3>
                <table class="form-table">
                    <tr>
                        <th scope="row">App ID</th>
                        <td>
                            <input type="text" name="wechat_app_id" value="<?php echo esc_attr($wechat_app_id); ?>" class="regular-text">
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">App Secret</th>
                        <td>
                            <input type="text" name="wechat_app_secret" value="<?php echo esc_attr($wechat_app_secret); ?>" class="regular-text">
                        </td>
                    </tr>
                </table>

                <h3>QQ登录</h3>
                <table class="form-table">
                    <tr>
                        <th scope="row">App ID</th>
                        <td>
                            <input type="text" name="qq_app_id" value="<?php echo esc_attr($qq_app_id); ?>" class="regular-text">
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">App Key</th>
                        <td>
                            <input type="text" name="qq_app_key" value="<?php echo esc_attr($qq_app_key); ?>" class="regular-text">
                        </td>
                    </tr>
                </table>

                <h3>GitHub登录</h3>
                <table class="form-table">
                    <tr>
                        <th scope="row">Client ID</th>
                        <td>
                            <input type="text" name="github_client_id" value="<?php echo esc_attr($github_client_id); ?>" class="regular-text">
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Client Secret</th>
                        <td>
                            <input type="text" name="github_client_secret" value="<?php echo esc_attr($github_client_secret); ?>" class="regular-text">
                        </td>
                    </tr>
                </table>

                <h3>Google登录</h3>
                <table class="form-table">
                    <tr>
                        <th scope="row">Client ID</th>
                        <td>
                            <input type="text" name="google_client_id" value="<?php echo esc_attr($google_client_id); ?>" class="regular-text">
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Client Secret</th>
                        <td>
                            <input type="text" name="google_client_secret" value="<?php echo esc_attr($google_client_secret); ?>" class="regular-text">
                        </td>
                    </tr>
                </table>
            </div>

            <!-- 安全设置 -->
            <div id="security" class="tab-content">
                <h2>安全设置</h2>
                <table class="form-table">
                    <tr>
                        <th scope="row">登录限制</th>
                        <td>
                            <p>最大登录尝试次数: 5次</p>
                            <p>锁定时间: 15分钟</p>
                            <p class="description">这些设置在代码中配置,无法在此修改</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">密码强度</th>
                        <td>
                            <p>最小长度: 8个字符</p>
                            <p>要求: 至少包含数字、小写字母、大写字母中的3种</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">JWT密钥</th>
                        <td>
                            <p>密钥已自动生成并保存</p>
                            <button type="button" class="button" onclick="if(confirm('确定要重新生成JWT密钥吗?这将使所有现有令牌失效!')) { alert('请在代码中实现此功能'); }">重新生成密钥</button>
                        </td>
                    </tr>
                </table>
            </div>

            <!-- 功能管理 -->
            <div id="features" class="tab-content">
                <h2>功能管理</h2>
                <table class="form-table">
                    <tr>
                        <th scope="row">私信系统</th>
                        <td>
                            <label>
                                <input type="checkbox" name="enable_private_messages" value="1" <?php checked($enable_private_messages, 1); ?>>
                                启用私信功能
                            </label>
                            <p class="description">允许用户之间发送私信</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">关注系统</th>
                        <td>
                            <label>
                                <input type="checkbox" name="enable_follow_system" value="1" <?php checked($enable_follow_system, 1); ?>>
                                启用关注功能
                            </label>
                            <p class="description">允许用户关注其他用户</p>
                        </td>
                    </tr>
                </table>
            </div>
        </div>

        <p class="submit">
            <input type="submit" name="submit" class="button button-primary" value="保存设置">
        </p>
    </form>
</div>

<style>
.xiaowu-user-settings .nav-tab-wrapper {
    margin-bottom: 20px;
}

.xiaowu-user-settings .tab-content {
    display: none;
    padding: 20px;
    background: #fff;
    border: 1px solid #ccd0d4;
    border-top: none;
}

.xiaowu-user-settings .tab-content.active {
    display: block;
}

.xiaowu-user-settings h3 {
    margin-top: 30px;
    padding-top: 20px;
    border-top: 1px solid #ddd;
}

.xiaowu-user-settings h3:first-of-type {
    margin-top: 20px;
    padding-top: 0;
    border-top: none;
}
</style>

<script>
jQuery(document).ready(function($) {
    $('.nav-tab').on('click', function(e) {
        e.preventDefault();

        var target = $(this).attr('href');

        $('.nav-tab').removeClass('nav-tab-active');
        $(this).addClass('nav-tab-active');

        $('.tab-content').removeClass('active');
        $(target).addClass('active');
    });
});
</script>
