<?php
/**
 * 安全助手类
 * 提供额外的安全功能
 */

namespace app\components;

use Yii;

class SecurityHelper
{
    /**
     * 生成强密码哈希
     * 使用 Argon2id 或 bcrypt
     * @param string $password
     * @return string
     */
    public static function hashPassword($password)
    {
        // 优先使用 Argon2id（PHP 7.3+）
        if (defined('PASSWORD_ARGON2ID')) {
            return password_hash($password, PASSWORD_ARGON2ID, [
                'memory_cost' => 65536,  // 64MB
                'time_cost' => 4,
                'threads' => 3
            ]);
        }
        
        // 回退到 bcrypt
        return password_hash($password, PASSWORD_BCRYPT, [
            'cost' => 12
        ]);
    }
    
    /**
     * 验证密码
     * @param string $password
     * @param string $hash
     * @return bool
     */
    public static function verifyPassword($password, $hash)
    {
        return password_verify($password, $hash);
    }
    
    /**
     * 检查密码强度
     * @param string $password
     * @return array ['score' => 0-100, 'feedback' => string]
     */
    public static function checkPasswordStrength($password)
    {
        $score = 0;
        $feedback = [];
        
        // 长度检查
        $length = strlen($password);
        if ($length >= 8) $score += 20;
        if ($length >= 12) $score += 10;
        if ($length >= 16) $score += 10;
        
        // 复杂度检查
        if (preg_match('/[a-z]/', $password)) $score += 15;
        if (preg_match('/[A-Z]/', $password)) $score += 15;
        if (preg_match('/[0-9]/', $password)) $score += 15;
        if (preg_match('/[^a-zA-Z0-9]/', $password)) $score += 15;
        
        // 反馈
        if ($length < 8) {
            $feedback[] = Yii::t('app', 'Password should be at least 8 characters long');
        }
        if (!preg_match('/[a-z]/', $password)) {
            $feedback[] = Yii::t('app', 'Add lowercase letters');
        }
        if (!preg_match('/[A-Z]/', $password)) {
            $feedback[] = Yii::t('app', 'Add uppercase letters');
        }
        if (!preg_match('/[0-9]/', $password)) {
            $feedback[] = Yii::t('app', 'Add numbers');
        }
        if (!preg_match('/[^a-zA-Z0-9]/', $password)) {
            $feedback[] = Yii::t('app', 'Add special characters');
        }
        
        return [
            'score' => min(100, $score),
            'feedback' => implode(', ', $feedback)
        ];
    }
    
    /**
     * 生成安全的随机令牌
     * @param int $length
     * @return string
     */
    public static function generateSecureToken($length = 32)
    {
        return bin2hex(random_bytes($length / 2));
    }
    
    /**
     * 清理和验证文件上传
     * @param \yii\web\UploadedFile $file
     * @param array $allowedTypes
     * @param int $maxSize
     * @return array ['success' => bool, 'error' => string]
     */
    public static function validateUploadedFile($file, $allowedTypes = [], $maxSize = 2097152)
    {
        if (empty($file)) {
            return ['success' => false, 'error' => 'No file uploaded'];
        }
        
        // 检查文件大小
        if ($file->size > $maxSize) {
            return ['success' => false, 'error' => 'File size exceeds limit'];
        }
        
        // 检查文件类型
        if (!empty($allowedTypes) && !in_array($file->type, $allowedTypes)) {
            return ['success' => false, 'error' => 'Invalid file type'];
        }
        
        // 检查文件扩展名
        $ext = strtolower($file->extension);
        $dangerousExts = ['php', 'phtml', 'php3', 'php4', 'php5', 'exe', 'sh', 'bat'];
        if (in_array($ext, $dangerousExts)) {
            return ['success' => false, 'error' => 'Dangerous file extension'];
        }
        
        return ['success' => true, 'error' => ''];
    }
    
    /**
     * 检测XSS攻击
     * @param string $input
     * @return bool
     */
    public static function detectXSS($input)
    {
        $dangerous_patterns = [
            '/<script[^>]*>.*?<\/script>/is',
            '/javascript:/i',
            '/on\w+\s*=/i',
            '/<iframe/i',
            '/<object/i',
            '/<embed/i'
        ];
        
        foreach ($dangerous_patterns as $pattern) {
            if (preg_match($pattern, $input)) {
                return true;
            }
        }
        
        return false;
    }
}
