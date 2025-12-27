# RSS自动采集插件 - 快速参考

## 🚀 快速启用

1. **启用自动采集**
   ```
   Admin → Plugins → RSS Collector → Settings
   Enable Auto Collection: Enabled
   ```

2. **配置RSS源**（每行一个）
   ```
   URL|NodeID|UserID|Keywords|Interval
   ```

3. **保存并测试**
   ```
   Admin → RSS Collector → Collect All Feeds Manually
   ```

## 📋 配置示例

```
# 每60分钟，只采集包含"气候"或"变暖"的内容
https://news.un.org/feed.xml|1|1|气候,变暖|60

# 每30分钟，采集所有内容
https://example.com/rss|2|1||30

# 使用默认60分钟间隔
https://blog.example.com/feed|3|1
```

## 📊 参数说明

| 参数 | 必填 | 说明 | 示例 |
|------|------|------|------|
| URL | ✅ | RSS/Atom地址 | https://example.com/feed |
| NodeID | ✅ | 节点ID | 1 |
| UserID | ✅ | 用户ID | 1 |
| Keywords | ❌ | 关键词（逗号分隔） | 科技,AI |
| Interval | ❌ | 间隔（分钟，默认60） | 30 |

## ⏱️ 推荐间隔

| 类型 | 间隔 | 说明 |
|------|------|------|
| 新闻源 | 30-60分钟 | 高频更新 |
| 博客 | 60-120分钟 | 中频更新 |
| 周刊 | 240-480分钟 | 低频更新 |

## 🔍 检查状态

### 查看管理界面
```
Admin → RSS Collector
```
显示：
- 自动采集状态（ENABLED/DISABLED）
- 已配置的RSS源列表
- 手动采集按钮

### 查看日志
```bash
# Linux/Mac
tail -f core/runtime/logs/app.log | grep "RSS"

# Windows PowerShell
Get-Content core\runtime\logs\app.log -Wait | Select-String "RSS"
```

### 运行测试脚本
```bash
php core/plugins/RssCollector/test_auto_collector.php
```

## 🛠️ 常用命令

### 清空缓存
```bash
# Linux/Mac
rm -rf core/runtime/cache/*

# Windows PowerShell
Remove-Item core\runtime\cache\* -Recurse -Force
```

### 查看最近日志
```bash
# Linux/Mac
tail -100 core/runtime/logs/app.log

# Windows PowerShell
Get-Content core\runtime\logs\app.log -Tail 100
```

### 测试RSS连接
```bash
# Linux/Mac
curl -I "https://example.com/feed"

# Windows PowerShell
Invoke-WebRequest -Uri "https://example.com/feed" -Method Head
```

## 📁 重要文件

```
core/
├── components/
│   └── RssAutoCollector.php        # 核心组件
├── controllers/admin/
│   └── RssCollectorController.php  # 控制器
├── plugins/RssCollector/
│   ├── RssCollector.php            # 插件配置
│   ├── README.md                   # 详细文档
│   ├── INSTALL.md                  # 安装指南
│   ├── CHANGELOG.md                # 修改日志
│   └── test_auto_collector.php     # 测试脚本
└── config/
    └── web.php                      # Bootstrap配置
```

## ⚠️ 故障排查

### 问题：自动采集未工作

**检查项：**
1. ✅ 插件设置中已启用？
2. ✅ web.php中添加了RssAutoCollector？
3. ✅ 网站有访问量？
4. ✅ 缓存系统正常？

**解决方法：**
```bash
# 1. 运行测试脚本
php core/plugins/RssCollector/test_auto_collector.php

# 2. 查看日志
tail -f core/runtime/logs/app.log

# 3. 清空缓存重试
rm -rf core/runtime/cache/*
```

### 问题：RSS源无法访问

**检查项：**
1. ✅ URL格式正确？
2. ✅ 服务器可访问外网？
3. ✅ allow_url_fopen已启用？

**测试方法：**
```bash
# 测试PHP配置
php -i | grep allow_url_fopen

# 测试网络连接
curl -I "https://example.com/feed"
```

### 问题：采集了重复内容

**可能原因：**
- RSS源修改了标题
- 配置了多个相同的源

**解决方法：**
- 检查RSS源配置
- 查看数据库中的重复记录

## 📈 性能优化

### 优化建议

1. **限制RSS源数量** ≤ 10个
2. **合理设置间隔**
   - 高频源：30-60分钟
   - 低频源：120-240分钟
3. **使用关键词过滤** 减少无关内容
4. **定期清理日志** 避免文件过大

### 监控指标

- 采集成功率
- 平均采集时间
- 发布帖子数量
- 错误日志数量

## 🔐 安全建议

1. ✅ 只添加可信的RSS源
2. ✅ 使用专门的用户ID发布
3. ✅ 设置合理的节点权限
4. ✅ 定期检查采集内容
5. ✅ 监控异常采集行为

## 📞 获取帮助

1. **文档**
   - [README.md](README.md) - 详细使用文档
   - [INSTALL.md](INSTALL.md) - 安装指南
   - [CHANGELOG.md](CHANGELOG.md) - 修改日志

2. **测试**
   ```bash
   php core/plugins/RssCollector/test_auto_collector.php
   ```

3. **日志**
   ```bash
   core/runtime/logs/app.log
   ```

## 🎯 最佳实践

### DO ✅

- 在低流量时段首次启用
- 先配置1-2个源测试
- 设置合理的采集间隔
- 使用关键词精准过滤
- 定期检查采集质量
- 监控日志文件

### DON'T ❌

- 不要设置过短的间隔（<15分钟）
- 不要配置过多RSS源（>15个）
- 不要忽略错误日志
- 不要采集未授权的内容
- 不要在高峰期大量采集

## 🔄 更新流程

1. 备份数据库和文件
2. 禁用自动采集
3. 更新文件
4. 清空缓存
5. 运行测试脚本
6. 启用自动采集
7. 监控运行状态

---

**版本：** 2.0  
**更新：** 2025-12-27  
**文档：** [README.md](README.md) | [INSTALL.md](INSTALL.md)
