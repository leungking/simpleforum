# RSS自动采集功能 - 安装/更新指南

## 已修改的文件

本次更新涉及以下文件：

### 新增文件
1. `core/components/RssAutoCollector.php` - 自动采集核心组件
2. `core/plugins/RssCollector/README.md` - 详细使用文档

### 修改文件
1. `core/config/web.php` - 添加了RssAutoCollector到bootstrap数组
2. `core/controllers/admin/RssCollectorController.php` - 支持原始发布日期
3. `core/plugins/RssCollector/RssCollector.php` - 添加自动采集配置项
4. `core/plugins/RssCollector/views/index.php` - 更新管理界面

## 功能概览

✅ **自动采集** - 无需cron脚本，基于用户访问自动触发
✅ **独立配置** - 每个RSS源可设置不同的采集间隔
✅ **原始日期** - 使用RSS源中的原始发布日期
✅ **智能缓存** - 避免重复采集
✅ **关键词过滤** - 只采集感兴趣的内容
✅ **限流保护** - 自动采集限制数量，避免服务器过载

## 安装步骤

### 1. 检查配置文件

确保 `core/config/web.php` 中的bootstrap配置包含RssAutoCollector：

```php
'bootstrap' => [
    'log', 
    'app\components\SfBootstrap', 
    'app\components\LanguageSelector', 
    'app\components\RssAutoCollector'
],
```

### 2. 清空缓存（推荐）

```bash
# Linux/Mac
rm -rf core/runtime/cache/*

# Windows PowerShell
Remove-Item -Path "core\runtime\cache\*" -Recurse -Force
```

### 3. 配置插件

1. 登录管理后台
2. 进入 **Admin → Plugins → RSS Collector → Settings**
3. 启用自动采集：将 **Enable Auto Collection** 设置为 **Enabled**
4. 配置RSS源，格式：`URL|NodeID|UserID|Keywords|Interval`

#### 配置示例：

```
# 每60分钟采集一次，只采集包含"气候"或"变暖"的内容
https://news.un.org/feed/subscribe/zh/news/topic/climate-change/feed/rss.xml|1|1|气候,变暖|60

# 每30分钟采集一次，采集所有内容
https://example.com/rss|2|1||30
```

### 4. 测试采集

1. 进入 **Admin → RSS Collector**
2. 查看自动采集状态（应显示"ENABLED"）
3. 点击 **Collect All Feeds Manually** 测试手动采集
4. 检查是否成功发布帖子

## 配置参数说明

### RSS源配置格式

```
URL|NodeID|UserID|Keywords|Interval
```

| 参数 | 说明 | 必填 | 示例 |
|------|------|------|------|
| URL | RSS/Atom feed地址 | 是 | `https://example.com/feed` |
| NodeID | 发布到的节点ID | 是 | `1` |
| UserID | 发布者用户ID | 是 | `1` |
| Keywords | 关键词过滤（逗号分隔） | 否 | `科技,技术` 或留空 |
| Interval | 采集间隔（分钟） | 否 | `60`（默认值） |

### 采集间隔建议

- **高频更新**（新闻源）：30-60分钟
- **中频更新**（博客）：60-120分钟
- **低频更新**（周刊）：240-480分钟

## 工作原理

1. **触发机制**：用户访问网站时，系统在后台检查是否需要采集
2. **执行时机**：在HTTP响应发送后执行，不影响页面加载速度
3. **间隔控制**：每个RSS源独立计时，达到间隔时间才采集
4. **缓存记录**：使用Yii缓存记录每个源的最后采集时间（有效期7天）
5. **限量采集**：自动采集每次每个源最多5条，手动采集无限制

## 验证安装

### 检查文件是否存在

```bash
# Linux/Mac
ls -la core/components/RssAutoCollector.php

# Windows PowerShell
Test-Path "core\components\RssAutoCollector.php"
```

### 检查配置是否正确

```bash
# 查看web.php中的bootstrap配置
grep -A 5 "bootstrap" core/config/web.php
```

### 查看日志

自动采集的错误会记录在应用日志中：

```bash
# 查看最新日志
tail -f core/runtime/logs/app.log
```

## 常见问题

### Q: 自动采集多久触发一次？

A: 取决于网站访问量。每次用户访问时会检查是否达到配置的间隔时间。如果网站访问较少，可以使用手动采集补充。

### Q: 会不会影响网站性能？

A: 不会。采集在HTTP响应发送后执行，不影响用户体验。且有缓存机制防止频繁采集。

### Q: 如何暂停自动采集？

A: 在插件设置中将 **Enable Auto Collection** 改为 **Disabled** 即可。

### Q: 采集的帖子时间不对？

A: 确保RSS源提供了有效的pubDate（RSS）或published（Atom）字段。如果RSS源没有提供日期，将使用当前时间。

### Q: 如何查看采集日志？

A: 查看 `core/runtime/logs/app.log` 文件，搜索 "RSS Auto Collector" 或 "RSS Feed Collection"。

### Q: 能否采集需要认证的RSS源？

A: 目前不支持。RSS源必须是公开可访问的。

## 升级注意事项

如果从旧版本升级：

1. ✅ 备份数据库和文件
2. ✅ 上传新文件
3. ✅ 修改 `core/config/web.php`
4. ✅ 清空缓存
5. ✅ 更新RSS源配置格式（添加间隔参数）
6. ✅ 测试采集功能

## 技术支持

如遇问题：

1. 检查 `core/runtime/logs/app.log` 日志文件
2. 确认PHP版本 >= 7.0
3. 确认PHP的 `allow_url_fopen` 已启用
4. 确认服务器可以访问外部网络
5. 查看 `core/plugins/RssCollector/README.md` 获取详细文档

## 卸载

如需禁用自动采集功能：

1. 在插件设置中禁用自动采集
2. 从 `core/config/web.php` 的bootstrap数组中移除 `app\components\RssAutoCollector`
3. （可选）删除 `core/components/RssAutoCollector.php`

注意：删除文件后需要清空缓存。
