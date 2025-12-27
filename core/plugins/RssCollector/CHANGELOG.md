# RSS采集插件自动化升级 - 修改总结

## 更新日期
2025-12-27

## 功能概述

本次升级为RSS采集插件添加了完全基于PHP的自动采集功能，无需依赖cron或其他外部脚本。主要特性包括：

### ✨ 新增功能

1. **自动采集机制**
   - 基于用户访问触发（类似WordPress wp-cron）
   - 在HTTP响应后执行，不影响页面性能
   - 使用Yii缓存记录采集时间

2. **每源独立配置**
   - 每个RSS源可设置不同的采集间隔
   - 支持关键词过滤
   - 独立的采集时间追踪

3. **使用原始发布日期**
   - 自动提取RSS/Atom中的发布日期
   - 保持内容的时间准确性

4. **智能限流**
   - 自动采集每次限制5条内容
   - 防止服务器过载
   - 手动采集无限制

## 文件修改清单

### 📝 新增文件（3个）

1. **core/components/RssAutoCollector.php**
   - 自动采集核心组件
   - 实现Bootstrap接口
   - 处理缓存和采集逻辑
   - 约320行代码

2. **core/plugins/RssCollector/README.md**
   - 详细的功能说明文档
   - 配置指南和示例
   - 故障排查说明

3. **core/plugins/RssCollector/INSTALL.md**
   - 安装/升级指南
   - 配置步骤说明
   - 常见问题解答

4. **core/plugins/RssCollector/test_auto_collector.php**
   - 功能测试脚本
   - 验证配置是否正确
   - 检查RSS源连接

### ✏️ 修改文件（4个）

#### 1. core/config/web.php
**修改位置：** 第16行
**修改内容：**
```php
// 修改前
'bootstrap' => ['log', 'app\components\SfBootstrap', 'app\components\LanguageSelector'],

// 修改后
'bootstrap' => ['log', 'app\components\SfBootstrap', 'app\components\LanguageSelector', 'app\components\RssAutoCollector'],
```

#### 2. core/plugins/RssCollector/RssCollector.php
**修改位置：** config数组
**修改内容：**
- 添加了"Enable Auto Collection"配置项（启用/禁用自动采集）
- 更新feeds_config格式说明，添加Interval参数
- 默认示例包含采集间隔

```php
// 新增配置项
[
    'label'=>'Enable Auto Collection',
    'key'=>'auto_collect_enabled',
    'type'=>'radio',
    'value'=>'0',
    'options'=>['0'=>'Disabled', '1'=>'Enabled'],
    'description'=>'Automatically collect feeds when users visit the site',
],

// 更新说明
'description'=>'One feed per line. Format: URL|NodeID|UserID|Keywords(optional)|Interval(minutes, optional, default 60)',
```

#### 3. core/controllers/admin/RssCollectorController.php
**修改位置：** actionCollect方法，多处
**修改内容：**

1. RSS 2.0格式添加日期提取（约144行）：
```php
// 提取发布日期
$pubDate = null;
if (isset($item->pubDate)) {
    $pubDate = strtotime((string)$item->pubDate);
} elseif (isset($item->date)) {
    $pubDate = strtotime((string)$item->date);
}

$items[] = [
    'title' => (string)$item->title,
    'link' => (string)$item->link,
    'description' => $description,
    'media' => $media,
    'pubDate' => $pubDate,  // 新增
];
```

2. Atom格式添加日期提取（约176行）：
```php
// 提取发布日期
$pubDate = null;
if (isset($entry->published)) {
    $pubDate = strtotime((string)$entry->published);
} elseif (isset($entry->updated)) {
    $pubDate = strtotime((string)$entry->updated);
}

$items[] = [
    'title' => (string)$entry->title,
    'link' => $link,
    'description' => $description,
    'media' => $media,
    'pubDate' => $pubDate,  // 新增
];
```

3. 创建Topic时保存日期到allItems（约225行）：
```php
$allItems[] = [
    'node_id' => $nodeId,
    'user_id' => $userId,
    'title' => $title,
    'description' => $description,
    'media' => $item['media'],
    'pubDate' => isset($item['pubDate']) ? $item['pubDate'] : null,  // 新增
];
```

4. 保存Topic时使用原始日期（约252行）：
```php
$topic = new Topic([
    'scenario' => Topic::SCENARIO_ADD,
    'node_id' => $item['node_id'],
    'user_id' => $item['user_id'],
    'title' => $item['title'],
]);

// 使用原始发布日期
if (!empty($item['pubDate'])) {
    $topic->created_at = $item['pubDate'];
    $topic->updated_at = $item['pubDate'];
}
```

#### 4. core/plugins/RssCollector/views/index.php
**修改内容：**

1. 添加自动采集状态显示（约14-25行）：
```php
<?php if (!empty($settings['auto_collect_enabled']) && $settings['auto_collect_enabled'] == '1'): ?>
<div class="alert alert-success">
    <strong>Auto Collection Status: ENABLED</strong><br>
    Feeds will be automatically collected when users visit the site based on the configured intervals.
</div>
<?php else: ?>
<div class="alert alert-warning">
    <strong>Auto Collection Status: DISABLED</strong><br>
    Enable auto collection in plugin settings to collect feeds automatically.
</div>
<?php endif; ?>
```

2. 表格添加"Interval (min)"列
3. 显示每个源的采集间隔
4. 更新按钮文字为"Collect All Feeds Manually"

## 配置格式变更

### 旧格式
```
URL|NodeID|UserID|Keywords
```

### 新格式（向后兼容）
```
URL|NodeID|UserID|Keywords|Interval
```

**说明：**
- Keywords和Interval都是可选的
- Interval默认值为60分钟
- 旧格式配置仍然有效

### 示例

```
# 完整配置
https://news.un.org/feed.xml|1|1|气候,变暖|60

# 无关键词，自定义间隔
https://example.com/feed|2|1||30

# 有关键词，默认间隔
https://tech.example.com/feed|3|1|科技

# 最简配置（兼容旧格式）
https://blog.example.com/feed|4|1
```

## 技术实现细节

### 自动采集流程

```
用户访问页面
    ↓
Bootstrap初始化
    ↓
RssAutoCollector::bootstrap()
    ↓
监听EVENT_AFTER_REQUEST事件
    ↓
响应发送给用户（用户感知不到延迟）
    ↓
tryAutoCollect()执行
    ↓
检查插件是否启用
    ↓
遍历每个RSS源
    ↓
检查缓存时间
    ↓
如果达到间隔时间，执行采集
    ↓
更新缓存时间
```

### 缓存策略

- **缓存Key格式：** `rss_auto_collect_{md5(feed_url)}`
- **缓存有效期：** 7天
- **存储内容：** 最后采集的Unix时间戳

### 性能优化

1. **异步执行**：使用EVENT_AFTER_REQUEST，响应发送后执行
2. **限量采集**：自动采集每次最多5条，避免长时间阻塞
3. **缓存机制**：避免频繁检查和采集
4. **超时控制**：每个源采集限制30秒
5. **错误容错**：单个源失败不影响其他源

## 使用说明

### 启用步骤

1. 进入 **Admin → Plugins → RSS Collector → Settings**
2. 设置 **Enable Auto Collection** 为 **Enabled**
3. 配置RSS源（添加采集间隔参数）
4. 保存设置
5. 访问网站任意页面触发首次采集

### 监控方法

1. **查看状态**：Admin → RSS Collector 页面
2. **查看日志**：core/runtime/logs/app.log
3. **运行测试**：`php core/plugins/RssCollector/test_auto_collector.php`

### 手动采集

即使启用自动采集，仍可手动触发：
- 进入 Admin → RSS Collector
- 点击 "Collect All Feeds Manually"
- 手动采集无数量限制

## 兼容性

- **PHP版本：** >= 7.0
- **Yii版本：** 2.0
- **SimpleForum：** 兼容现有版本
- **向后兼容：** 旧的配置格式仍然有效

## 安全性

1. 只在APPLICATION_END事件后执行，不影响主流程
2. 所有异常都被捕获并记录，不会中断应用
3. 使用缓存防止恶意触发频繁采集
4. 限制单次采集数量，防止资源耗尽

## 测试建议

### 功能测试

1. 运行测试脚本：
```bash
cd e:\WebDev\simpleforum
php core/plugins/RssCollector/test_auto_collector.php
```

2. 启用自动采集后访问网站
3. 等待配置的间隔时间后再次访问
4. 检查是否有新帖子发布
5. 查看日志确认采集执行

### 性能测试

1. 配置3-5个RSS源
2. 设置较短间隔（如10分钟）
3. 使用Apache Bench测试页面响应时间
4. 确认响应时间无明显增加

## 升级检查清单

- [ ] 备份数据库和文件
- [ ] 上传新文件（RssAutoCollector.php等）
- [ ] 修改config/web.php
- [ ] 清空缓存
- [ ] 运行测试脚本
- [ ] 启用自动采集
- [ ] 更新RSS源配置格式
- [ ] 测试手动采集
- [ ] 等待自动采集触发
- [ ] 检查日志文件

## 故障排查

### 自动采集未触发

1. 检查config/web.php中的bootstrap配置
2. 检查插件设置中是否启用
3. 检查网站是否有访问量
4. 查看日志是否有错误

### 采集失败

1. 检查RSS URL是否可访问
2. 检查PHP的allow_url_fopen设置
3. 检查服务器网络连接
4. 查看详细错误日志

## 维护建议

1. **定期检查日志**：每周查看一次采集日志
2. **监控采集质量**：检查发布的帖子是否符合预期
3. **调整间隔**：根据实际情况优化采集间隔
4. **清理缓存**：每月清理一次旧缓存
5. **更新RSS源**：及时移除失效的源

## 未来扩展

可能的功能扩展方向：

- [ ] 支持HTTPS认证的RSS源
- [ ] 添加webhook通知
- [ ] 采集统计和报表
- [ ] 内容去重算法优化
- [ ] 支持更多Feed格式
- [ ] 自动翻译功能
- [ ] AI摘要生成

## 联系方式

如有问题或建议，请查看：
- README.md：详细使用文档
- INSTALL.md：安装指南
- test_auto_collector.php：测试工具

---

**更新者：** GitHub Copilot  
**更新日期：** 2025-12-27  
**版本：** 2.0 (Auto Collection)
