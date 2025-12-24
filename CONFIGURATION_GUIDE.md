# 配置更新指南

## 启用新组件

### 1. 更新 core/config/web.php

在 `components` 数组中添加以下配置：

```php
'components' => [
    // ... 现有配置 ...
    
    // 性能监控
    'performanceMonitor' => [
        'class' => 'app\components\PerformanceMonitor',
        'enabled' => YII_DEBUG, // 仅在调试模式启用
        'slowQueryThreshold' => 1.0, // 1秒
    ],
    
    // 增强错误处理
    'errorHandler' => [
        'class' => 'app\components\ErrorHandler',
        'errorAction' => 'site/error',
    ],
],
```

### 2. 启用Redis缓存（可选）

如果已安装 `yiisoft/yii2-redis`：

```php
'components' => [
    // ... 现有配置 ...
    
    // 替换现有的cache配置
    'cache' => require(__DIR__ . '/cache.php'),
    
    // Redis会话（可选）
    'session' => [
        'class' => 'yii\redis\Session',
        'redis' => 'cache',
    ],
],
```

### 3. 启用查询缓存

在 `db` 组件配置中：

```php
'db' => [
    'class' => 'yii\db\Connection',
    // ... 其他配置 ...
    'enableQueryCache' => true,
    'queryCacheDuration' => 3600, // 1小时
],
```

## 使用示例

### 在控制器中使用查询缓存

```php
use app\components\QueryCache;

// 在 TopicController 中
public function actionIndex()
{
    $hotTopics = QueryCache::hot('hot_topics_home', function() {
        return Topic::find()
            ->where(['alltop' => 1])
            ->orderBy(['replied_at' => SORT_DESC])
            ->limit(10)
            ->all();
    }, 3600); // 缓存1小时
    
    return $this->render('index', [
        'topics' => $hotTopics,
    ]);
}
```

### 在模型中应用速率限制

速率限制已自动应用到 `LoginForm`，无需额外配置。

如需在其他地方使用：

```php
use app\components\RateLimiter;

// 在评论提交时
public function actionCreate()
{
    $ip = Yii::$app->request->userIP;
    
    try {
        // 限制：每分钟最多3条评论
        RateLimiter::checkLimit("comment_{$ip}", 3, 60);
        
        // 处理评论逻辑...
        
    } catch (\yii\web\TooManyRequestsHttpException $e) {
        Yii::$app->session->setFlash('error', $e->getMessage());
        return $this->redirect(['topic/view', 'id' => $topicId]);
    }
}
```

## 环境变量配置

创建 `.env` 文件（可选）：

```env
# Redis配置
REDIS_HOST=localhost
REDIS_PORT=6379
REDIS_DB=0
REDIS_PASSWORD=

# 应用配置
YII_DEBUG=false
YII_ENV=prod

# 数据库
DB_HOST=localhost
DB_NAME=simpleforum
DB_USER=root
DB_PASSWORD=
```

然后在 `web.php` 开头加载：

```php
<?php
// 加载环境变量（需要安装 vlucas/phpdotenv）
if (file_exists(__DIR__ . '/../../.env')) {
    $dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../..');
    $dotenv->load();
}
```

## 日志配置

更新日志配置以捕获性能和安全日志：

```php
'log' => [
    'traceLevel' => YII_DEBUG ? 3 : 0,
    'targets' => [
        // 应用日志
        [
            'class' => 'yii\log\FileTarget',
            'levels' => ['error', 'warning'],
            'logFile' => '@runtime/logs/app.log',
        ],
        // 性能日志
        [
            'class' => 'yii\log\FileTarget',
            'categories' => ['performance'],
            'levels' => ['warning'],
            'logFile' => '@runtime/logs/performance.log',
            'logVars' => [],
        ],
        // 安全日志
        [
            'class' => 'yii\log\FileTarget',
            'categories' => ['security'],
            'levels' => ['warning', 'error'],
            'logFile' => '@runtime/logs/security.log',
        ],
    ],
],
```

## Nginx配置优化

在 Nginx 配置中添加：

```nginx
server {
    listen 80;
    server_name simpleforum.local;
    root /path/to/simpleforum/web;
    index index.php;

    # Gzip压缩
    gzip on;
    gzip_types text/plain text/css application/json application/javascript text/xml application/xml;
    gzip_min_length 1000;

    # 浏览器缓存
    location ~* \.(jpg|jpeg|png|gif|ico|css|js|svg|woff|woff2|ttf)$ {
        expires 1y;
        add_header Cache-Control "public, immutable";
    }

    # 安全头部
    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header X-Content-Type-Options "nosniff" always;
    add_header X-XSS-Protection "1; mode=block" always;

    # PHP处理
    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php7.4-fpm.sock;
        fastcgi_index index.php;
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        
        # 性能优化
        fastcgi_buffer_size 128k;
        fastcgi_buffers 256 16k;
        fastcgi_busy_buffers_size 256k;
    }

    # 隐藏敏感文件
    location ~ /\. {
        deny all;
    }
}
```

## PHP-FPM优化

编辑 `/etc/php/7.4/fpm/pool.d/www.conf`：

```ini
pm = dynamic
pm.max_children = 50
pm.start_servers = 10
pm.min_spare_servers = 5
pm.max_spare_servers = 20
pm.max_requests = 500

; 性能监控
pm.status_path = /status
ping.path = /ping
```

## 验证配置

运行以下命令验证配置：

```bash
# 检查PHP配置
php -i | grep -i opcache

# 检查数据库连接
php yii db/test

# 检查缓存
php yii cache/flush-all

# 检查权限
ls -la core/runtime
ls -la web/assets
```

## 故障排除

### 缓存不工作
- 检查 `core/runtime/cache` 目录权限
- 验证 Redis 连接（如使用）
- 查看日志：`tail -f core/runtime/logs/app.log`

### 速率限制太严格
修改 `LoginForm.php` 中的参数：
```php
RateLimiter::checkLimit("login_{$ip}", 10, 600); // 10次/10分钟
```

### 性能监控不显示
确保在 `web.php` 中启用了 `performanceMonitor` 组件并设置 `enabled => true`

## 需要帮助？

查看详细文档：
- [IMPROVEMENTS.md](IMPROVEMENTS.md) - 完整功能文档
- [UPGRADE_SUMMARY_CN.md](UPGRADE_SUMMARY_CN.md) - 升级总结
- [Yii2 Guide](https://www.yiiframework.com/doc/guide/2.0/en)
