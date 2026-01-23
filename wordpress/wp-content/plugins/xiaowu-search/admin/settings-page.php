<?php
/**
 * 搜索设置页面
 *
 * @package Xiaowu_Search
 */

if (!defined('ABSPATH')) {
    exit;
}

// 保存设置
if (isset($_POST['xiaowu_search_settings_submit'])) {
    check_admin_referer('xiaowu_search_settings');

    update_option('xiaowu_search_replace_default', isset($_POST['replace_default']));
    update_option('xiaowu_search_enable_ai', isset($_POST['enable_ai']));
    update_option('xiaowu_search_enable_suggestions', isset($_POST['enable_suggestions']));
    update_option('xiaowu_search_results_per_page', intval($_POST['results_per_page']));
    update_option('xiaowu_search_min_word_length', intval($_POST['min_word_length']));
    update_option('xiaowu_search_enable_fuzzy', isset($_POST['enable_fuzzy']));
    update_option('xiaowu_search_enable_synonyms', isset($_POST['enable_synonyms']));
    update_option('xiaowu_search_index_posts', isset($_POST['index_posts']));
    update_option('xiaowu_search_index_pages', isset($_POST['index_pages']));
    update_option('xiaowu_search_index_comments', isset($_POST['index_comments']));
    update_option('xiaowu_search_index_users', isset($_POST['index_users']));
    update_option('xiaowu_search_enable_analytics', isset($_POST['enable_analytics']));

    echo '<div class="notice notice-success"><p>设置已保存</p></div>';
}
?>

<div class="wrap xiaowu-search-admin">
    <h1>智能搜索设置</h1>

    <form method="post" action="">
        <?php wp_nonce_field('xiaowu_search_settings'); ?>

        <table class="form-table">
            <tr>
                <th scope="row">基本设置</th>
                <td>
                    <fieldset>
                        <label>
                            <input type="checkbox" name="replace_default" <?php checked(get_option('xiaowu_search_replace_default', true)); ?>>
                            替换WordPress默认搜索
                        </label>
                        <p class="description">使用智能搜索引擎替换WordPress内置搜索</p>

                        <label>
                            <input type="checkbox" name="enable_ai" <?php checked(get_option('xiaowu_search_enable_ai', true)); ?>>
                            启用AI增强搜索
                        </label>
                        <p class="description">使用AI技术优化搜索结果排序和相关性</p>

                        <label>
                            <input type="checkbox" name="enable_suggestions" <?php checked(get_option('xiaowu_search_enable_suggestions', true)); ?>>
                            启用搜索建议
                        </label>
                        <p class="description">在用户输入时显示搜索建议</p>

                        <label>
                            <input type="checkbox" name="enable_analytics" <?php checked(get_option('xiaowu_search_enable_analytics', true)); ?>>
                            启用搜索分析
                        </label>
                        <p class="description">记录搜索数据用于分析优化</p>
                    </fieldset>
                </td>
            </tr>

            <tr>
                <th scope="row">搜索参数</th>
                <td>
                    <label>
                        每页显示结果数:
                        <input type="number" name="results_per_page" value="<?php echo esc_attr(get_option('xiaowu_search_results_per_page', 20)); ?>" min="1" max="100" class="small-text">
                    </label>
                    <p class="description">每页显示的搜索结果数量</p>

                    <label>
                        最小词长:
                        <input type="number" name="min_word_length" value="<?php echo esc_attr(get_option('xiaowu_search_min_word_length', 2)); ?>" min="1" max="10" class="small-text">
                    </label>
                    <p class="description">搜索词的最小字符数</p>
                </td>
            </tr>

            <tr>
                <th scope="row">搜索算法</th>
                <td>
                    <fieldset>
                        <label>
                            <input type="checkbox" name="enable_fuzzy" <?php checked(get_option('xiaowu_search_enable_fuzzy', true)); ?>>
                            启用模糊搜索
                        </label>
                        <p class="description">允许部分匹配和拼写纠错</p>

                        <label>
                            <input type="checkbox" name="enable_synonyms" <?php checked(get_option('xiaowu_search_enable_synonyms', true)); ?>>
                            启用同义词扩展
                        </label>
                        <p class="description">自动包含同义词在搜索中</p>
                    </fieldset>
                </td>
            </tr>

            <tr>
                <th scope="row">索引内容</th>
                <td>
                    <fieldset>
                        <label>
                            <input type="checkbox" name="index_posts" <?php checked(get_option('xiaowu_search_index_posts', true)); ?>>
                            索引文章
                        </label><br>

                        <label>
                            <input type="checkbox" name="index_pages" <?php checked(get_option('xiaowu_search_index_pages', true)); ?>>
                            索引页面
                        </label><br>

                        <label>
                            <input type="checkbox" name="index_comments" <?php checked(get_option('xiaowu_search_index_comments', true)); ?>>
                            索引评论
                        </label><br>

                        <label>
                            <input type="checkbox" name="index_users" <?php checked(get_option('xiaowu_search_index_users', true)); ?>>
                            索引用户
                        </label>
                        <p class="description">选择要包含在搜索索引中的内容类型</p>
                    </fieldset>
                </td>
            </tr>
        </table>

        <p class="submit">
            <input type="submit" name="xiaowu_search_settings_submit" class="button button-primary" value="保存设置">
        </p>
    </form>

    <hr>

    <h2>快捷操作</h2>
    <table class="form-table">
        <tr>
            <th scope="row">重建索引</th>
            <td>
                <button type="button" id="xiaowu-rebuild-index" class="button">重建所有索引</button>
                <p class="description">清空并重建所有搜索索引（可能需要几分钟）</p>
                <div id="rebuild-progress" style="display:none; margin-top:10px;">
                    <progress id="rebuild-progress-bar" value="0" max="100" style="width:100%;"></progress>
                    <p id="rebuild-status">正在处理...</p>
                </div>
            </td>
        </tr>

        <tr>
            <th scope="row">优化索引</th>
            <td>
                <button type="button" id="xiaowu-optimize-index" class="button">优化索引</button>
                <p class="description">清理无效数据并优化数据库表</p>
            </td>
        </tr>

        <tr>
            <th scope="row">清除历史</th>
            <td>
                <button type="button" id="xiaowu-clear-history" class="button">清除搜索历史</button>
                <p class="description">清除所有搜索历史记录（不影响热门搜索）</p>
            </td>
        </tr>
    </table>
</div>

<style>
.xiaowu-search-admin .form-table th {
    width: 200px;
}

.xiaowu-search-admin fieldset label {
    display: block;
    margin-bottom: 10px;
}

.xiaowu-search-admin .description {
    margin-top: 5px;
    font-style: italic;
}

#rebuild-progress-bar {
    height: 30px;
}

#rebuild-status {
    margin-top: 10px;
    font-weight: bold;
}
</style>

<script>
jQuery(document).ready(function($) {
    // 重建索引
    $('#xiaowu-rebuild-index').on('click', function() {
        if (!confirm('确定要重建所有索引吗？这可能需要几分钟时间。')) {
            return;
        }

        const $btn = $(this);
        const $progress = $('#rebuild-progress');
        const $bar = $('#rebuild-progress-bar');
        const $status = $('#rebuild-status');

        $btn.prop('disabled', true);
        $progress.show();
        $bar.val(0);
        $status.text('正在重建索引...');

        $.ajax({
            url: xiaowuSearchAdmin.restUrl + 'index/rebuild',
            method: 'POST',
            headers: {
                'X-WP-Nonce': xiaowuSearchAdmin.restNonce
            },
            success: function(response) {
                $bar.val(100);
                $status.text('索引重建完成!');
                setTimeout(function() {
                    $progress.hide();
                    $btn.prop('disabled', false);
                    location.reload();
                }, 2000);
            },
            error: function() {
                $status.text('重建失败，请重试');
                $btn.prop('disabled', false);
            }
        });
    });

    // 优化索引
    $('#xiaowu-optimize-index').on('click', function() {
        const $btn = $(this);
        $btn.prop('disabled', true).text('正在优化...');

        $.ajax({
            url: xiaowuSearchAdmin.ajaxUrl,
            method: 'POST',
            data: {
                action: 'xiaowu_optimize_index',
                nonce: xiaowuSearchAdmin.nonce
            },
            success: function(response) {
                alert('索引优化完成');
                $btn.prop('disabled', false).text('优化索引');
            },
            error: function() {
                alert('优化失败');
                $btn.prop('disabled', false).text('优化索引');
            }
        });
    });

    // 清除历史
    $('#xiaowu-clear-history').on('click', function() {
        if (!confirm('确定要清除所有搜索历史吗？此操作不可恢复。')) {
            return;
        }

        const $btn = $(this);
        $btn.prop('disabled', true).text('正在清除...');

        $.ajax({
            url: xiaowuSearchAdmin.ajaxUrl,
            method: 'POST',
            data: {
                action: 'xiaowu_clear_search_history',
                nonce: xiaowuSearchAdmin.nonce
            },
            success: function(response) {
                alert('搜索历史已清除');
                $btn.prop('disabled', false).text('清除搜索历史');
            },
            error: function() {
                alert('清除失败');
                $btn.prop('disabled', false).text('清除搜索历史');
            }
        });
    });
});
</script>
