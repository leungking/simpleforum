<?php
/**
 * 查询缓存助手
 * 提供简化的查询缓存接口
 */

namespace app\components;

use Yii;
use yii\db\Query;

class QueryCache
{
    /**
     * 缓存查询结果
     * @param string $key 缓存键
     * @param callable $callback 查询回调函数
     * @param int $duration 缓存持续时间（秒）
     * @param \yii\caching\Dependency $dependency 缓存依赖
     * @return mixed
     */
    public static function get($key, $callback, $duration = 3600, $dependency = null)
    {
        $cache = Yii::$app->cache;
        
        // 尝试从缓存获取
        $result = $cache->get($key);
        
        if ($result === false) {
            // 缓存未命中，执行查询
            $result = call_user_func($callback);
            
            // 存入缓存
            $cache->set($key, $result, $duration, $dependency);
        }
        
        return $result;
    }
    
    /**
     * 缓存数据库查询
     * @param Query $query
     * @param string $method 查询方法 (all, one, scalar, column)
     * @param int $duration
     * @return mixed
     */
    public static function query($query, $method = 'all', $duration = 3600)
    {
        $key = self::generateQueryKey($query, $method);
        
        return self::get($key, function() use ($query, $method) {
            return $query->$method();
        }, $duration);
    }
    
    /**
     * 为查询生成唯一键
     * @param Query $query
     * @param string $method
     * @return string
     */
    private static function generateQueryKey($query, $method)
    {
        $sql = $query->createCommand()->getRawSql();
        return 'query_' . md5($sql . '_' . $method);
    }
    
    /**
     * 清除匹配模式的缓存
     * @param string $pattern 缓存键模式（使用*作为通配符）
     */
    public static function flush($pattern = null)
    {
        $cache = Yii::$app->cache;
        
        if ($pattern === null) {
            $cache->flush();
        }
        // 注意: 大多数缓存后端不支持模式匹配删除
        // 如需此功能，请使用支持的缓存后端（如Redis）并实现自定义逻辑
    }
    
    /**
     * 热点数据缓存（更长的过期时间）
     * @param string $key
     * @param callable $callback
     * @param int $duration 默认24小时
     * @return mixed
     */
    public static function hot($key, $callback, $duration = 86400)
    {
        return self::get($key, $callback, $duration);
    }
}
