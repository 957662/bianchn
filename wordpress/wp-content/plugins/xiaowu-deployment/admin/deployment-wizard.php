<?php
/**
 * 部署向导管理界面
 *
 * @package Xiaowu_Deployment
 * @since 2.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * 渲染向导页面
 */
function xiaowu_deployment_wizard_page()
{
    // 检查是否已完成部署
    $completed = get_option('xiaowu_deployment_completed');

    ?>
    <div class="wrap xiaowu-deployment-wrap">
        <h1>
            <span class="dashicons dashicons-admin-generic"></span>
            小伍博客部署向导
        </h1>

        <?php if ($completed): ?>
            <div class="xiaowu-deployment-completed">
                <h2>🎉 部署已完成！</h2>
                <p>您的博客已成功部署，所有配置均已就绪。</p>

                <div class="xiaowu-deployment-stats">
                    <h3>系统状态</h3>
                    <table class="widefat">
                        <tr>
                            <td>PHP 版本</td>
                            <td><?php echo phpversion(); ?></td>
                        </tr>
                        <tr>
                            <td>WordPress 版本</td>
                            <td><?php echo get_bloginfo('version'); ?></td>
                        </tr>
                        <tr>
                            <td>MySQL 版本</td>
                            <td><?php echo $GLOBALS['wpdb']->get_var('SELECT VERSION()'); ?></td>
                        </tr>
                        <tr>
                            <td>部署时间</td>
                            <td><?php echo get_option('xiaowu_deployment_completed'); ?></td>
                        </tr>
                    </table>
                </div>

                <p>
                    <a href="<?php echo admin_url(); ?>" class="button button-primary">进入控制台</a>
                    <button type="button" class="button" onclick="xiaowuResetDeployment()">重新部署</button>
                </p>
            </div>
        <?php else: ?>
            <div class="xiaowu-deployment-wizard">
                <!-- 步骤指示器 -->
                <div class="wizard-steps">
                    <div class="step active" data-step="1">
                        <span class="step-number">1</span>
                        <span class="step-title">环境检查</span>
                    </div>
                    <div class="step" data-step="2">
                        <span class="step-number">2</span>
                        <span class="step-title">数据库配置</span>
                    </div>
                    <div class="step" data-step="3">
                        <span class="step-number">3</span>
                        <span class="step-title">AI服务配置</span>
                    </div>
                    <div class="step" data-step="4">
                        <span class="step-number">4</span>
                        <span class="step-title">CDN配置</span>
                    </div>
                    <div class="step" data-step="5">
                        <span class="step-number">5</span>
                        <span class="step-title">完成部署</span>
                    </div>
                </div>

                <!-- 步骤内容 -->
                <div class="wizard-content">
                    <?php require_once XIAOWU_DEPLOY_PLUGIN_DIR . 'admin/templates/wizard-page.php'; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <script>
    function xiaowuResetDeployment() {
        if (confirm('确定要重新部署吗？这将清除所有配置。')) {
            location.href = '<?php echo admin_url('admin.php?page=xiaowu-deployment&action=reset'); ?>';
        }
    }
    </script>
    <?php
}
