# RSS采集插件 - 故障排查指南

## 关键词配置说明

### ✅ 支持关键词留空

配置格式中的关键词字段**完全支持留空**，有以下几种方式：

```
# 方式1：不填写关键词，直接跳到间隔
https://rss.aishort.top/?type=cneb|1|1||5

# 方式2：不填写关键词和间隔（使用默认60分钟）
https://quanwenrss.com/scmp|2|1

# 方式3：填写关键词
https://news.un.org/feed.xml|1|1|科技,AI|30
```

**说明：**
- 关键词留空表示采集**所有内容**，不进行过滤
- 连续的 `||` 表示关键词字段为空
- 代码中使用 `array_filter()` 自动处理空值

## 常见错误分析

### 1. XML解析错误

**错误日志示例：**
```
XML Parse Error for https://example.com/feed: Opening and ending tag mismatch
```

**可能原因：**
- RSS源返回的不是有效的XML
- 返回了HTML错误页面（404、503等）
- 编码问题（BOM、非UTF-8）
- 内容被截断

**解决方法：**
```bash
# 测试RSS源
curl -I "https://example.com/feed"
curl "https://example.com/feed" | head -20

# 检查返回内容
php -r "echo file_get_contents('https://example.com/feed');" | head -20
```

**代码已处理：**
- 自动移除BOM
- 自动转换编码为UTF-8
- 处理Gzip压缩

### 2. 网络连接超时

**错误日志示例：**
```
Failed to fetch content from https://example.com/feed (Content is empty)
```

**可能原因：**
- 服务器无法访问外网
- RSS源响应过慢
- 防火墙拦截
- DNS解析失败

**解决方法：**
```bash
# 测试网络连接
curl -v "https://example.com/feed"

# 测试PHP能否访问
php -r "var_dump(file_get_contents('https://example.com/feed'));"

# 检查allow_url_fopen
php -i | grep allow_url_fopen
```

**配置检查：**
```ini
# php.ini
allow_url_fopen = On
default_socket_timeout = 60
```

### 3. 验证失败

**错误日志示例：**
```
Validation failed for: 标题示例 - Errors: {"title":["标题不能为空"]}
```

**可能原因：**
- 标题为空或太长
- 节点ID不存在
- 用户ID不存在
- 标签格式错误

**检查方法：**
```sql
-- 检查节点是否存在
SELECT * FROM sf_node WHERE id = 1;

-- 检查用户是否存在
SELECT * FROM sf_user WHERE id = 1;

-- 检查是否有重复标题
SELECT * FROM sf_topic WHERE title = '标题' AND node_id = 1;
```

### 4. 内存或超时问题

**错误日志示例：**
```
PHP Fatal error: Maximum execution time exceeded
PHP Fatal error: Allowed memory size exhausted
```

**解决方法：**

在代码中已设置：
```php
set_time_limit(30);  // 自动采集30秒
set_time_limit(60);  // 手动采集60秒
```

如需调整PHP配置：
```ini
# php.ini
max_execution_time = 300
memory_limit = 256M
```

### 5. 权限问题

**错误日志示例：**
```
failed to open stream: Permission denied
Unable to create directory
```

**解决方法：**
```bash
# Linux/Mac
chmod -R 755 core/runtime/cache
chmod -R 755 core/runtime/logs
chown -R www-data:www-data core/runtime

# Windows（以管理员运行）
icacls core\runtime /grant IIS_IUSRS:F /T
```

## 调试步骤

### 第一步：检查配置

```bash
# 运行测试脚本
php core/plugins/RssCollector/test_auto_collector.php
```

检查输出：
- ✓ 所有项都正常
- ⚠ 有警告需要处理
- ✗ 有错误必须修复

### 第二步：查看日志

```bash
# 实时查看日志（Linux/Mac）
tail -f core/runtime/logs/app.log | grep -i rss

# Windows PowerShell
Get-Content core\runtime\logs\app.log -Wait | Select-String -Pattern "rss" -CaseSensitive:$false
```

关键日志标识：
- `RSS Auto Collector Error` - 自动采集总错误
- `RSS Feed Collection Error` - 单个源采集错误
- `Successfully auto-collected` - 成功采集（info级别）
- `Validation failed` - 验证失败（warning级别）

### 第三步：手动测试

1. 进入管理后台：`Admin → RSS Collector`
2. 点击 `Collect All Feeds Manually`
3. 查看返回的错误信息
4. 对比日志文件中的详细错误

### 第四步：单独测试RSS源

```php
<?php
// 在项目根目录创建 test_single_rss.php
$url = 'https://rss.aishort.top/?type=cneb';

$opts = [
    "http" => [
        "method" => "GET",
        "timeout" => 15,
        "header" => "User-Agent: Mozilla/5.0\r\n"
    ]
];
$context = stream_context_create($opts);
$content = file_get_contents($url, false, $context);

if ($content) {
    echo "成功获取内容，长度: " . strlen($content) . "\n";
    
    // 检查是否是XML
    if (strpos($content, '<?xml') === 0 || strpos($content, '<rss') !== false) {
        echo "内容是有效的XML\n";
        
        libxml_use_internal_errors(true);
        $xml = simplexml_load_string($content);
        if ($xml) {
            echo "XML解析成功\n";
            
            // RSS 2.0
            if (isset($xml->channel->item)) {
                echo "RSS 2.0格式，条目数: " . count($xml->channel->item) . "\n";
                foreach (array_slice(iterator_to_array($xml->channel->item), 0, 3) as $i => $item) {
                    echo "  [{$i}] " . $item->title . "\n";
                }
            }
            // Atom
            else if (isset($xml->entry)) {
                echo "Atom格式，条目数: " . count($xml->entry) . "\n";
                foreach (array_slice(iterator_to_array($xml->entry), 0, 3) as $i => $entry) {
                    echo "  [{$i}] " . $entry->title . "\n";
                }
            }
        } else {
            echo "XML解析失败:\n";
            foreach (libxml_get_errors() as $error) {
                echo "  - " . $error->message . "\n";
            }
        }
    } else {
        echo "内容不是XML格式，前100字符:\n";
        echo substr($content, 0, 100) . "\n";
    }
} else {
    echo "无法获取内容\n";
}
?>
```

运行：
```bash
php test_single_rss.php
```

## 日志级别说明

### Info（信息）
正常操作记录，表示功能正常工作：
```
Successfully auto-collected: 文章标题 from https://example.com/feed
Successfully collected: 文章标题
```

### Warning（警告）
不影响主流程但需要关注的问题：
```
Validation failed for: 标题 - Errors: {...}
```

### Error（错误）
影响功能的错误，需要立即处理：
```
RSS Feed Collection Error for https://example.com/feed: ...
XML Parse Error for https://example.com/feed: ...
```

## 性能优化

### 减少日志大小

如果日志文件过大，修改日志级别：

```php
// core/config/web.php
'components' => [
    'log' => [
        'targets' => [
            [
                'class' => 'yii\log\FileTarget',
                'levels' => ['error', 'warning'],  // 只记录错误和警告
                // 'levels' => ['error'],  // 只记录错误
            ],
        ],
    ],
],
```

### 日志轮转

```bash
# Linux - 使用logrotate
cat > /etc/logrotate.d/simpleforum << 'EOF'
/path/to/simpleforum/core/runtime/logs/*.log {
    daily
    missingok
    rotate 7
    compress
    notifempty
    create 0644 www-data www-data
}
EOF

# 手动轮转
cd core/runtime/logs
mv app.log app.log.$(date +%Y%m%d)
gzip app.log.*
```

## 常见问题FAQ

### Q: 关键词留空后是否会采集所有内容？
A: 是的，关键词留空（`||`）会采集RSS源中的所有条目。

### Q: 为什么有些文章没有被采集？
A: 可能原因：
1. 文章标题已存在（检查：同标题+同节点）
2. 关键词过滤（检查配置）
3. 验证失败（查看日志）
4. 间隔时间未到

### Q: 如何强制重新采集？
A: 清空缓存后手动采集：
```bash
rm -rf core/runtime/cache/rss_auto_collect_*
# 然后访问 Admin → RSS Collector → Collect All Feeds Manually
```

### Q: 自动采集多久触发一次？
A: 取决于：
1. 网站访问量（每次访问检查一次）
2. 配置的间隔时间（如5分钟、60分钟）
3. 上次采集时间

### Q: 如何查看某个RSS源的采集状态？
A: 查询缓存：
```php
<?php
require 'core/vendor/autoload.php';
require 'core/vendor/yiisoft/yii2/Yii.php';
$config = require 'core/config/web.php';
new yii\web\Application($config);

$url = 'https://rss.aishort.top/?type=cneb';
$cacheKey = 'rss_auto_collect_' . md5($url);
$lastTime = Yii::$app->cache->get($cacheKey);

if ($lastTime) {
    echo "最后采集: " . date('Y-m-d H:i:s', $lastTime) . "\n";
    echo "距现在: " . floor((time() - $lastTime) / 60) . " 分钟前\n";
} else {
    echo "尚未采集过\n";
}
?>
```

### Q: 采集的内容格式不对？
A: 检查编辑器设置：
- SmdEditor 或 Vditor：使用Markdown格式
- 其他编辑器：使用原始HTML

系统会自动根据编辑器转换格式。

### Q: 如何临时禁用某个RSS源？
A: 在配置中注释掉该行：
```
# https://example.com/feed|1|1||60
```
或删除该行。

## 获取帮助

1. **查看文档**
   - [README.md](README.md) - 功能说明
   - [INSTALL.md](INSTALL.md) - 安装指南
   - [QUICKSTART.md](QUICKSTART.md) - 快速参考

2. **运行测试**
   ```bash
   php core/plugins/RssCollector/test_auto_collector.php
   ```

3. **查看日志**
   ```bash
   tail -100 core/runtime/logs/app.log
   ```

4. **调试模式**
   ```php
   // core/config/web.php
   defined('YII_DEBUG') or define('YII_DEBUG', true);
   defined('YII_ENV') or define('YII_ENV', 'dev');
   ```

---

**更新日期：** 2025-12-27  
**版本：** 2.0
