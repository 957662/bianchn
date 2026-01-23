<?php
/**
 * 表情包处理类
 *
 * @package Xiaowu_Comments
 */

if (!defined('ABSPATH')) {
    exit;
}

class Xiaowu_Comment_Emoji
{
    /**
     * 表情包映射
     */
    private $emoji_map = array();

    /**
     * 构造函数
     */
    public function __construct()
    {
        $this->load_emoji_map();
    }

    /**
     * 加载表情包映射
     */
    private function load_emoji_map()
    {
        $this->emoji_map = array(
            // 笑脸类
            ':)' => '😊',
            ':-)' => '😊',
            ':D' => '😀',
            ':-D' => '😀',
            'XD' => '😆',
            ':P' => '😛',
            ':-P' => '😛',
            ';)' => '😉',
            ';-)' => '😉',

            // 悲伤类
            ':(' => '😢',
            ':-(' => '😢',
            'T_T' => '😭',
            'T.T' => '😭',

            // 其他表情
            ':o' => '😮',
            ':-o' => '😮',
            ':*' => '😘',
            ':-*' => '😘',
            '<3' => '❤️',
            '</3' => '💔',

            // 中文表情
            '[微笑]' => '😊',
            '[大笑]' => '😂',
            '[爱心]' => '❤️',
            '[赞]' => '👍',
            '[思考]' => '🤔',
            '[疑问]' => '❓',
            '[惊讶]' => '😮',
            '[哭]' => '😭',
            '[生气]' => '😠',
            '[酷]' => '😎',
            '[鼓掌]' => '👏',
            '[握手]' => '🤝',
            '[拥抱]' => '🤗',
            '[庆祝]' => '🎉',
            '[礼物]' => '🎁',
            '[火箭]' => '🚀',
            '[星星]' => '⭐',
            '[太阳]' => '☀️',
            '[月亮]' => '🌙',
            '[花]' => '🌸',
            '[咖啡]' => '☕',
            '[书]' => '📚',
            '[电脑]' => '💻',
            '[手机]' => '📱',
            '[相机]' => '📷',
            '[音乐]' => '🎵',
            '[电影]' => '🎬'
        );

        // 允许主题或插件自定义表情包
        $this->emoji_map = apply_filters('xiaowu_comments_emoji_map', $this->emoji_map);
    }

    /**
     * 转换文本中的表情代码为表情符号
     */
    public function convert($text)
    {
        if (!get_option('xiaowu_comments_emoji_enabled', true)) {
            return $text;
        }

        // 先转换短代码格式的表情
        foreach ($this->emoji_map as $code => $emoji) {
            $text = str_replace($code, $emoji, $text);
        }

        // 转换 :emoji_name: 格式
        $text = preg_replace_callback('/:([a-z0-9_+-]+):/i', function($matches) {
            return $this->get_unicode_emoji($matches[1]);
        }, $text);

        return $text;
    }

    /**
     * 获取Unicode表情
     */
    private function get_unicode_emoji($name)
    {
        $unicode_emojis = array(
            'smile' => '😊',
            'laugh' => '😂',
            'heart' => '❤️',
            'thumbsup' => '👍',
            'thumbsdown' => '👎',
            'clap' => '👏',
            'fire' => '🔥',
            'star' => '⭐',
            'rocket' => '🚀',
            'check' => '✅',
            'cross' => '❌',
            'warning' => '⚠️',
            'info' => 'ℹ️',
            'question' => '❓',
            'exclamation' => '❗',
            'plus' => '➕',
            'minus' => '➖',
            'arrow_up' => '⬆️',
            'arrow_down' => '⬇️',
            'arrow_left' => '⬅️',
            'arrow_right' => '➡️'
        );

        return isset($unicode_emojis[$name]) ? $unicode_emojis[$name] : ":{$name}:";
    }

    /**
     * 获取表情包列表
     */
    public function get_emoji_list()
    {
        $categories = array(
            'faces' => array(
                'label' => '表情',
                'emojis' => array('😊', '😂', '😍', '😎', '🤔', '😮', '😭', '😠', '🥰', '😘')
            ),
            'gestures' => array(
                'label' => '手势',
                'emojis' => array('👍', '👎', '👏', '🤝', '🙏', '💪', '✌️', '🤞', '👌', '✊')
            ),
            'hearts' => array(
                'label' => '爱心',
                'emojis' => array('❤️', '💔', '💕', '💖', '💗', '💙', '💚', '💛', '🧡', '💜')
            ),
            'symbols' => array(
                'label' => '符号',
                'emojis' => array('✅', '❌', '⚠️', 'ℹ️', '❓', '❗', '🔥', '⭐', '🚀', '🎉')
            ),
            'objects' => array(
                'label' => '物品',
                'emojis' => array('☕', '📚', '💻', '📱', '📷', '🎵', '🎬', '🎮', '🎨', '⚽')
            ),
            'nature' => array(
                'label' => '自然',
                'emojis' => array('☀️', '🌙', '⭐', '🌸', '🌺', '🌻', '🌹', '🌷', '🌲', '🍀')
            )
        );

        return apply_filters('xiaowu_comments_emoji_categories', $categories);
    }

    /**
     * 渲染表情选择器
     */
    public function render_picker()
    {
        if (!get_option('xiaowu_comments_emoji_enabled', true)) {
            return '';
        }

        $categories = $this->get_emoji_list();

        ob_start();
        ?>
        <div class="xiaowu-emoji-picker" style="display: none;">
            <div class="xiaowu-emoji-tabs">
                <?php foreach ($categories as $key => $category): ?>
                    <button type="button"
                            class="xiaowu-emoji-tab"
                            data-category="<?php echo esc_attr($key); ?>">
                        <?php echo esc_html($category['label']); ?>
                    </button>
                <?php endforeach; ?>
            </div>
            <div class="xiaowu-emoji-content">
                <?php foreach ($categories as $key => $category): ?>
                    <div class="xiaowu-emoji-category" data-category="<?php echo esc_attr($key); ?>">
                        <?php foreach ($category['emojis'] as $emoji): ?>
                            <button type="button"
                                    class="xiaowu-emoji-item"
                                    data-emoji="<?php echo esc_attr($emoji); ?>">
                                <?php echo $emoji; ?>
                            </button>
                        <?php endforeach; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * 获取常用表情
     */
    public function get_recent_emojis($user_id = 0)
    {
        if ($user_id > 0) {
            $recent = get_user_meta($user_id, 'recent_emojis', true);
        } else {
            $recent = isset($_COOKIE['recent_emojis']) ? json_decode(stripslashes($_COOKIE['recent_emojis']), true) : array();
        }

        return is_array($recent) ? array_slice($recent, 0, 20) : array();
    }

    /**
     * 记录常用表情
     */
    public function add_recent_emoji($emoji, $user_id = 0)
    {
        $recent = $this->get_recent_emojis($user_id);

        // 移除重复项
        $recent = array_diff($recent, array($emoji));

        // 添加到开头
        array_unshift($recent, $emoji);

        // 限制数量
        $recent = array_slice($recent, 0, 20);

        if ($user_id > 0) {
            update_user_meta($user_id, 'recent_emojis', $recent);
        } else {
            setcookie('recent_emojis', json_encode($recent), time() + (86400 * 30), '/');
        }
    }
}
