<?php
/**
 * 缓存管理类
 *
 * @package Xiaowu_AI
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Xiaowu_Cache_Manager 类
 */
class Xiaowu_Cache_Manager
{
    /**
     * 缓存类型
     */
    private $cache_type;

    /**
     * Redis连接
     */
    private $redis;

    /**
     * 构造函数
     */
    public function __construct()
    {
        $this->cache_type = $this->detect_cache_type();

        if ($this->cache_type === 'redis') {
            $this->init_redis();
        }
    }

    /**
     * 检测缓存类型
     */
    private function detect_cache_type()
    {
        // 检查Redis是否可用
        if (class_exists('Redis') && defined('WP_REDIS_HOST')) {
            return 'redis';
        }

        // 使用WordPress对象缓存
        return 'wp_cache';
    }

    /**
     * 初始化Redis连接
     */
    private function init_redis()
    {
        try {
            $this->redis = new Redis();

            $host = defined('WP_REDIS_HOST') ? WP_REDIS_HOST : '127.0.0.1';
            $port = defined('WP_REDIS_PORT') ? WP_REDIS_PORT : 6379;
            $database = defined('WP_REDIS_DATABASE') ? WP_REDIS_DATABASE : 0;

            $this->redis->connect($host, $port);

            if (defined('WP_REDIS_PASSWORD') && !empty(WP_REDIS_PASSWORD)) {
                $this->redis->auth(WP_REDIS_PASSWORD);
            }

            $this->redis->select($database);
        } catch (Exception $e) {
            error_log('Redis连接失败：' . $e->getMessage());
            $this->cache_type = 'wp_cache';
            $this->redis = null;
        }
    }

    /**
     * 获取缓存
     */
    public function get($key, $group = 'xiaowu_ai')
    {
        if ($this->cache_type === 'redis' && $this->redis) {
            return $this->get_redis($key, $group);
        }

        return $this->get_wp_cache($key, $group);
    }

    /**
     * 设置缓存
     */
    public function set($key, $value, $expiration = 3600, $group = 'xiaowu_ai')
    {
        if ($this->cache_type === 'redis' && $this->redis) {
            return $this->set_redis($key, $value, $expiration, $group);
        }

        return $this->set_wp_cache($key, $value, $expiration, $group);
    }

    /**
     * 删除缓存
     */
    public function delete($key, $group = 'xiaowu_ai')
    {
        if ($this->cache_type === 'redis' && $this->redis) {
            return $this->delete_redis($key, $group);
        }

        return $this->delete_wp_cache($key, $group);
    }

    /**
     * 清空缓存组
     */
    public function flush_group($group = 'xiaowu_ai')
    {
        if ($this->cache_type === 'redis' && $this->redis) {
            return $this->flush_redis_group($group);
        }

        return $this->flush_wp_cache_group($group);
    }

    /**
     * Redis获取
     */
    private function get_redis($key, $group)
    {
        try {
            $redis_key = $this->build_redis_key($key, $group);
            $value = $this->redis->get($redis_key);

            if ($value === false) {
                return false;
            }

            return maybe_unserialize($value);
        } catch (Exception $e) {
            error_log('Redis获取失败：' . $e->getMessage());
            return false;
        }
    }

    /**
     * Redis设置
     */
    private function set_redis($key, $value, $expiration, $group)
    {
        try {
            $redis_key = $this->build_redis_key($key, $group);
            $serialized = maybe_serialize($value);

            if ($expiration > 0) {
                return $this->redis->setex($redis_key, $expiration, $serialized);
            } else {
                return $this->redis->set($redis_key, $serialized);
            }
        } catch (Exception $e) {
            error_log('Redis设置失败：' . $e->getMessage());
            return false;
        }
    }

    /**
     * Redis删除
     */
    private function delete_redis($key, $group)
    {
        try {
            $redis_key = $this->build_redis_key($key, $group);
            return $this->redis->del($redis_key) > 0;
        } catch (Exception $e) {
            error_log('Redis删除失败：' . $e->getMessage());
            return false;
        }
    }

    /**
     * Redis清空组
     */
    private function flush_redis_group($group)
    {
        try {
            $pattern = "xiaowu:{$group}:*";
            $keys = $this->redis->keys($pattern);

            if (!empty($keys)) {
                return $this->redis->del($keys) > 0;
            }

            return true;
        } catch (Exception $e) {
            error_log('Redis清空组失败：' . $e->getMessage());
            return false;
        }
    }

    /**
     * 构建Redis键
     */
    private function build_redis_key($key, $group)
    {
        return "xiaowu:{$group}:{$key}";
    }

    /**
     * WordPress缓存获取
     */
    private function get_wp_cache($key, $group)
    {
        return wp_cache_get($key, $group);
    }

    /**
     * WordPress缓存设置
     */
    private function set_wp_cache($key, $value, $expiration, $group)
    {
        return wp_cache_set($key, $value, $group, $expiration);
    }

    /**
     * WordPress缓存删除
     */
    private function delete_wp_cache($key, $group)
    {
        return wp_cache_delete($key, $group);
    }

    /**
     * WordPress缓存清空组
     */
    private function flush_wp_cache_group($group)
    {
        // WordPress没有直接清空组的方法，这里使用全局flush
        return wp_cache_flush();
    }

    /**
     * 获取缓存统计
     */
    public function get_stats()
    {
        $stats = array(
            'type' => $this->cache_type,
            'enabled' => true
        );

        if ($this->cache_type === 'redis' && $this->redis) {
            try {
                $info = $this->redis->info();
                $stats['redis'] = array(
                    'connected' => true,
                    'used_memory' => isset($info['used_memory_human']) ? $info['used_memory_human'] : 'N/A',
                    'total_keys' => $this->count_xiaowu_keys(),
                    'version' => isset($info['redis_version']) ? $info['redis_version'] : 'N/A'
                );
            } catch (Exception $e) {
                $stats['redis'] = array(
                    'connected' => false,
                    'error' => $e->getMessage()
                );
            }
        }

        return $stats;
    }

    /**
     * 统计小伍AI缓存键数量
     */
    private function count_xiaowu_keys()
    {
        try {
            $pattern = "xiaowu:*";
            $keys = $this->redis->keys($pattern);
            return count($keys);
        } catch (Exception $e) {
            return 0;
        }
    }

    /**
     * 缓存记忆（支持回调函数）
     */
    public function remember($key, $callback, $expiration = 3600, $group = 'xiaowu_ai')
    {
        $value = $this->get($key, $group);

        if ($value !== false) {
            return $value;
        }

        $value = call_user_func($callback);
        $this->set($key, $value, $expiration, $group);

        return $value;
    }

    /**
     * 批量获取
     */
    public function get_multiple($keys, $group = 'xiaowu_ai')
    {
        $results = array();

        foreach ($keys as $key) {
            $results[$key] = $this->get($key, $group);
        }

        return $results;
    }

    /**
     * 批量设置
     */
    public function set_multiple($items, $expiration = 3600, $group = 'xiaowu_ai')
    {
        $results = array();

        foreach ($items as $key => $value) {
            $results[$key] = $this->set($key, $value, $expiration, $group);
        }

        return $results;
    }

    /**
     * 批量删除
     */
    public function delete_multiple($keys, $group = 'xiaowu_ai')
    {
        $results = array();

        foreach ($keys as $key) {
            $results[$key] = $this->delete($key, $group);
        }

        return $results;
    }

    /**
     * 检查键是否存在
     */
    public function exists($key, $group = 'xiaowu_ai')
    {
        if ($this->cache_type === 'redis' && $this->redis) {
            try {
                $redis_key = $this->build_redis_key($key, $group);
                return $this->redis->exists($redis_key) > 0;
            } catch (Exception $e) {
                return false;
            }
        }

        return $this->get($key, $group) !== false;
    }

    /**
     * 获取剩余过期时间
     */
    public function ttl($key, $group = 'xiaowu_ai')
    {
        if ($this->cache_type === 'redis' && $this->redis) {
            try {
                $redis_key = $this->build_redis_key($key, $group);
                return $this->redis->ttl($redis_key);
            } catch (Exception $e) {
                return -1;
            }
        }

        return -1;
    }

    /**
     * 更新过期时间
     */
    public function touch($key, $expiration, $group = 'xiaowu_ai')
    {
        if ($this->cache_type === 'redis' && $this->redis) {
            try {
                $redis_key = $this->build_redis_key($key, $group);
                return $this->redis->expire($redis_key, $expiration);
            } catch (Exception $e) {
                return false;
            }
        }

        // WordPress缓存不支持更新过期时间，需要重新获取并设置
        $value = $this->get($key, $group);
        if ($value !== false) {
            return $this->set($key, $value, $expiration, $group);
        }

        return false;
    }
}
