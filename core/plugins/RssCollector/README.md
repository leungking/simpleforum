# RSS Auto Collector Plugin

## 功能特性

这个增强版的RSS采集插件现在支持自动采集功能，无需依赖cron或其他外部脚本。

### 主要功能

1. **自动采集**：基于用户访问触发的自动采集机制（类似WordPress的wp-cron）
2. **每个源独立配置**：可以为每个RSS源设置不同的采集间隔
3. **智能采集**：使用缓存机制避免频繁采集
4. **保留原始日期**：采集的帖子会使用原内容的发布日期
5. **关键词过滤**：可以为每个源设置关键词过滤
6. **限流保护**：每次自动采集限制条目数量，避免服务器过载

## 配置说明

### 启用自动采集

1. 进入 **Admin → Plugins → RSS Collector → Settings**
2. 将 **Enable Auto Collection** 设置为 **Enabled**
3. 保存设置

### 配置RSS源

在 **RSS Feeds Configuration** 中，每行配置一个RSS源，格式如下：

```
URL|NodeID|UserID|Keywords|Interval
```

#### 参数说明

- **URL**：RSS/Atom feed的完整URL（必填）
- **NodeID**：发布到的节点ID（必填）
- **UserID**：发布者的用户ID（必填）
- **Keywords**：关键词过滤，多个关键词用英文逗号分隔（可选）
  - 留空表示采集所有内容
  - 设置关键词后只采集标题或内容中包含这些关键词的条目
- **Interval**：采集间隔，单位：分钟（可选，默认60分钟）

#### 配置示例

```
# 每60分钟采集一次，只采集包含"气候"或"变暖"关键词的内容
https://news.un.org/feed/subscribe/zh/news/topic/climate-change/feed/rss.xml|1|1|气候,变暖|60

# 每30分钟采集一次，采集所有内容
https://example.com/rss|2|1||30

# 每120分钟采集一次，只采集包含"科技"关键词的内容
https://tech.example.com/feed|3|1|科技|120

# 使用默认间隔（60分钟），采集所有内容
https://blog.example.com/feed|4|1
```

## 工作原理

### 自动采集机制

1. **触发时机**：当用户访问网站的任何页面时，系统会在后台检查是否需要采集
2. **间隔控制**：每个RSS源根据配置的间隔时间独立采集
3. **缓存机制**：使用Yii缓存存储每个源的最后采集时间
4. **异步执行**：采集在HTTP响应发送后执行，不影响页面加载速度
5. **数量限制**：每次自动采集每个源最多采集5条新内容

### 手动采集

即使启用了自动采集，您仍然可以在 **Admin → RSS Collector** 页面手动触发采集：

- 点击 **Collect All Feeds Manually** 按钮
- 手动采集会采集所有可用的新内容（无数量限制）

## 技术细节

### 文件结构

```
core/
├── components/
│   └── RssAutoCollector.php     # 自动采集核心组件
├── controllers/admin/
│   └── RssCollectorController.php  # 采集控制器
├── plugins/RssCollector/
│   ├── RssCollector.php          # 插件配置
│   ├── views/
│   │   └── index.php             # 管理界面
│   └── README.md                 # 本文档
└── config/
    └── web.php                    # Bootstrap配置
```

### Bootstrap集成

自动采集组件通过Yii的Bootstrap机制集成到应用中：

```php
'bootstrap' => [
    'log', 
    'app\components\SfBootstrap', 
    'app\components\LanguageSelector', 
    'app\components\RssAutoCollector'  // 自动采集组件
],
```

### 缓存Key格式

每个RSS源的采集时间缓存Key格式：

```
rss_auto_collect_{md5(feed_url)}
```

缓存有效期：7天

## 性能优化建议

1. **合理设置采集间隔**
   - 高频更新的源：30-60分钟
   - 普通更新的源：60-120分钟
   - 低频更新的源：120-240分钟

2. **使用关键词过滤**
   - 减少无关内容的采集
   - 提高采集质量

3. **限制RSS源数量**
   - 建议不超过10个RSS源
   - 过多RSS源会影响性能

4. **监控日志**
   - 检查 `runtime/logs/app.log` 查看采集错误
   - 及时处理失败的采集

## 故障排查

### 自动采集不工作

1. 检查插件是否启用自动采集
2. 检查网站是否有用户访问（自动采集需要用户访问触发）
3. 检查缓存是否正常工作
4. 查看日志文件是否有错误信息

### 采集重复内容

- 系统会检查标题和节点ID的组合，避免重复采集
- 如果仍有重复，检查RSS源是否修改了标题

### 采集失败

1. 检查RSS URL是否可访问
2. 检查服务器是否能访问外部网络
3. 检查PHP的`allow_url_fopen`是否启用
4. 查看日志获取详细错误信息

## 升级说明

如果您是从旧版本升级：

1. 确保 `core/components/RssAutoCollector.php` 文件存在
2. 确保 `core/config/web.php` 中添加了 `RssAutoCollector` 到bootstrap数组
3. 更新插件配置格式（添加采集间隔参数）
4. 清空缓存：`rm -rf core/runtime/cache/*`

## 安全建议

1. 只添加可信的RSS源
2. 定期检查采集的内容
3. 使用合适的用户ID作为发布者
4. 设置合理的节点权限

## 许可证

与SimpleForum主程序相同的许可证。
