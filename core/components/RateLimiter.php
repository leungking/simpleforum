<?php
/**
 * Rate Limiter Component
 * 用于防止暴力破解和API滥用
 */

namespace app\components;

use Yii;
use yii\base\Component;
use yii\web\TooManyRequestsHttpException;

class RateLimiter extends Component
{
    /**
     * 检查速率限制
     * @param string $key 唯一标识（如IP地址或用户ID）
     * @param int $maxAttempts 最大尝试次数
     * @param int $timeWindow 时间窗口（秒）
     * @return bool
     * @throws TooManyRequestsHttpException
     */
    public static function checkLimit($key, $maxAttempts = 5, $timeWindow = 300)
    {
        $cache = Yii::$app->cache;
        $cacheKey = "rate_limit_{$key}";
        
        $attempts = $cache->get($cacheKey);
        
        if ($attempts === false) {
            // 首次请求
            $cache->set($cacheKey, 1, $timeWindow);
            return true;
        }
        
        if ($attempts >= $maxAttempts) {
            throw new TooManyRequestsHttpException(
                Yii::t('app', 'Too many requests. Please try again later.')
            );
        }
        
        // 增加计数
        $cache->set($cacheKey, $attempts + 1, $timeWindow);
        return true;
    }
    
    /**
     * 重置速率限制
     * @param string $key
     */
    public static function resetLimit($key)
    {
        $cache = Yii::$app->cache;
        $cacheKey = "rate_limit_{$key}";
        $cache->delete($cacheKey);
    }
    
    /**
     * 获取剩余尝试次数
     * @param string $key
     * @param int $maxAttempts
     * @return int
     */
    public static function getRemainingAttempts($key, $maxAttempts = 5)
    {
        $cache = Yii::$app->cache;
        $cacheKey = "rate_limit_{$key}";
        $attempts = $cache->get($cacheKey);
        
        if ($attempts === false) {
            return $maxAttempts;
        }
        
        return max(0, $maxAttempts - $attempts);
    }
}
