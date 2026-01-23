<?php
/**
 * 评论通知类
 *
 * @package Xiaowu_Comments
 */

if (!defined('ABSPATH')) {
    exit;
}

class Xiaowu_Comment_Notification
{
    /**
     * 发送新评论通知
     */
    public function notify_new_comment($comment_id)
    {
        $comment = get_comment($comment_id);
        if (!$comment) {
            return false;
        }

        // 通知管理员
        if (get_option('xiaowu_comments_notify_admin', true)) {
            $this->notify_admin($comment);
        }

        // 如果是回复评论，通知被回复者
        if ($comment->comment_parent > 0) {
            $this->notify_parent_author($comment);
        }

        // 通知文章作者
        if (get_option('xiaowu_comments_notify_author', true)) {
            $this->notify_post_author($comment);
        }

        // 通知@提及的用户
        $this->notify_mentioned_users($comment);

        return true;
    }

    /**
     * 通知管理员
     */
    private function notify_admin($comment)
    {
        $admin_email = get_option('admin_email');
        $post = get_post($comment->comment_post_ID);

        $subject = sprintf('[%s] 新评论: "%s"', get_bloginfo('name'), $post->post_title);

        $message = $this->get_email_template('admin', array(
            'comment' => $comment,
            'post' => $post,
            'approve_link' => admin_url("comment.php?action=approve&c={$comment->comment_ID}"),
            'spam_link' => admin_url("comment.php?action=spam&c={$comment->comment_ID}"),
            'trash_link' => admin_url("comment.php?action=trash&c={$comment->comment_ID}")
        ));

        $this->send_email($admin_email, $subject, $message);
    }

    /**
     * 通知被回复者
     */
    private function notify_parent_author($comment)
    {
        $parent_comment = get_comment($comment->comment_parent);
        if (!$parent_comment) {
            return;
        }

        // 不要通知自己
        if ($parent_comment->comment_author_email === $comment->comment_author_email) {
            return;
        }

        $post = get_post($comment->comment_post_ID);

        $subject = sprintf('[%s] 您的评论有新回复', get_bloginfo('name'));

        $message = $this->get_email_template('reply', array(
            'comment' => $comment,
            'parent_comment' => $parent_comment,
            'post' => $post,
            'post_link' => get_permalink($post->ID) . '#comment-' . $comment->comment_ID
        ));

        $this->send_email($parent_comment->comment_author_email, $subject, $message);
    }

    /**
     * 通知文章作者
     */
    private function notify_post_author($comment)
    {
        $post = get_post($comment->comment_post_ID);
        $author = get_userdata($post->post_author);

        if (!$author) {
            return;
        }

        // 不要通知自己的评论
        if ($author->user_email === $comment->comment_author_email) {
            return;
        }

        $subject = sprintf('[%s] 您的文章有新评论: "%s"', get_bloginfo('name'), $post->post_title);

        $message = $this->get_email_template('author', array(
            'comment' => $comment,
            'post' => $post,
            'author' => $author,
            'post_link' => get_permalink($post->ID) . '#comment-' . $comment->comment_ID
        ));

        $this->send_email($author->user_email, $subject, $message);
    }

    /**
     * 通知@提及的用户
     */
    private function notify_mentioned_users($comment)
    {
        $mentions = get_comment_meta($comment->comment_ID, 'mentions', true);

        if (empty($mentions) || !is_array($mentions)) {
            return;
        }

        $post = get_post($comment->comment_post_ID);

        foreach ($mentions as $mention) {
            $user = get_user_by('id', $mention['id']);
            if (!$user) {
                continue;
            }

            // 不要通知自己
            if ($user->user_email === $comment->comment_author_email) {
                continue;
            }

            $subject = sprintf('[%s] %s 在评论中提到了你', get_bloginfo('name'), $comment->comment_author);

            $message = $this->get_email_template('mention', array(
                'comment' => $comment,
                'post' => $post,
                'user' => $user,
                'post_link' => get_permalink($post->ID) . '#comment-' . $comment->comment_ID
            ));

            $this->send_email($user->user_email, $subject, $message);
        }
    }

    /**
     * 获取邮件模板
     */
    private function get_email_template($type, $data)
    {
        $templates = array(
            'admin' => "
                <h2>新评论通知</h2>
                <p><strong>文章:</strong> {$data['post']->post_title}</p>
                <p><strong>评论者:</strong> {$data['comment']->comment_author} ({$data['comment']->comment_author_email})</p>
                <p><strong>评论内容:</strong></p>
                <blockquote>{$data['comment']->comment_content}</blockquote>
                <p>
                    <a href='{$data['approve_link']}'>批准</a> |
                    <a href='{$data['spam_link']}'>标记为垃圾</a> |
                    <a href='{$data['trash_link']}'>删除</a>
                </p>
            ",
            'reply' => "
                <h2>评论回复通知</h2>
                <p>您好 {$data['parent_comment']->comment_author},</p>
                <p>{$data['comment']->comment_author} 回复了您在《{$data['post']->post_title}》中的评论:</p>
                <blockquote><strong>您的评论:</strong><br>{$data['parent_comment']->comment_content}</blockquote>
                <blockquote><strong>回复内容:</strong><br>{$data['comment']->comment_content}</blockquote>
                <p><a href='{$data['post_link']}'>查看完整讨论</a></p>
            ",
            'author' => "
                <h2>新评论通知</h2>
                <p>您好 {$data['author']->display_name},</p>
                <p>您的文章《{$data['post']->post_title}》有新评论:</p>
                <p><strong>评论者:</strong> {$data['comment']->comment_author}</p>
                <blockquote>{$data['comment']->comment_content}</blockquote>
                <p><a href='{$data['post_link']}'>查看评论</a></p>
            ",
            'mention' => "
                <h2>评论提及通知</h2>
                <p>您好 {$data['user']->display_name},</p>
                <p>{$data['comment']->comment_author} 在《{$data['post']->post_title}》的评论中提到了你:</p>
                <blockquote>{$data['comment']->comment_content}</blockquote>
                <p><a href='{$data['post_link']}'>查看评论</a></p>
            "
        );

        $template = isset($templates[$type]) ? $templates[$type] : '';

        // 允许主题自定义模板
        return apply_filters("xiaowu_comments_email_template_{$type}", $template, $data);
    }

    /**
     * 发送邮件
     */
    private function send_email($to, $subject, $message)
    {
        $headers = array(
            'Content-Type: text/html; charset=UTF-8',
            'From: ' . get_bloginfo('name') . ' <' . get_option('admin_email') . '>'
        );

        // 添加邮件样式
        $styled_message = "
            <html>
            <head>
                <style>
                    body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                    h2 { color: #667eea; }
                    blockquote {
                        background: #f9f9f9;
                        border-left: 4px solid #667eea;
                        padding: 10px 15px;
                        margin: 15px 0;
                    }
                    a { color: #667eea; text-decoration: none; }
                    a:hover { text-decoration: underline; }
                </style>
            </head>
            <body>
                {$message}
                <hr>
                <p style='font-size: 12px; color: #999;'>
                    此邮件由 " . get_bloginfo('name') . " 自动发送，请勿直接回复。
                </p>
            </body>
            </html>
        ";

        return wp_mail($to, $subject, $styled_message, $headers);
    }
}
