# SimpleForum 安装指南

> **项目说明**: 本项目基于 [SimpleForum](https://github.com/SimpleForum/SimpleForum) 修改。  
> 已修复PHP 8.x兼容性问题，增强编辑器功能，优化性能。

## 环境要求

- **PHP**: 8.0 或更高版本
- **数据库**: MySQL 5.7+ / MariaDB 10.2+
- **Web服务器**: Apache / Nginx / PHP内置服务器
- **PHP扩展**: PDO, PDO_MySQL, mbstring, openssl, curl

## 安装步骤

### 1. 克隆或下载项目

```bash
git clone https://github.com/yourusername/simpleforum.git
cd simpleforum
```

### 2. 配置数据库

复制数据库配置模板：
```bash
cp core/config/db.php.default core/config/db.php
```

编辑 `core/config/db.php`：
```php
<?php
return [
    'class' => 'yii\db\Connection',
    'enableSchemaCache' => true,
    'dsn' => 'mysql:host=YOUR_HOST;dbname=YOUR_DATABASE',
    'username' => 'YOUR_USERNAME',
    'password' => 'YOUR_PASSWORD',
    'tablePrefix' => 'simple_',
    'charset' => 'utf8mb4',
];
```

### 3. 导入数据库

创建数据库并导入初始SQL文件：
```bash
mysql -u YOUR_USERNAME -p YOUR_DATABASE < core/install_update/data/simpleforum.sql
```

### 4. 配置参数

如果没有 `core/config/params.php`：
```bash
cp core/config/params.php.default core/config/params.php
```

### 5. 设置权限

确保以下目录可写：
```bash
chmod -R 755 core/runtime
chmod -R 755 upload
chmod -R 755 avatar
chmod -R 755 assets
```

### 6. 启动服务

#### 开发环境（PHP内置服务器）
```bash
php -S 127.0.0.1:8000 router.php
```

#### 生产环境（Nginx示例）
```nginx
server {
    listen 80;
    server_name yourdomain.com;
    root /path/to/simpleforum;
    index index.php;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.0-fpm.sock;
        fastcgi_index index.php;
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
    }
}
```

## 首次访问

1. 访问安装向导（如果启用）
2. 创建管理员账户
3. 完成基本设置

## 常见问题

### 无法连接数据库
- 检查 `core/config/db.php` 配置是否正确
- 确认数据库服务运行中
- 验证用户权限

### 500错误
- 检查 `core/runtime/logs/app.log` 日志
- 确认目录权限设置正确
- 验证PHP版本 >= 8.0

### 静态资源404
- 检查Web服务器配置
- 确认assets目录存在且可读

## 安全建议

1. **生产环境禁用调试模式**
   编辑 `index.php`：
   ```php
   defined('YII_DEBUG') or define('YII_DEBUG', false);
   defined('YII_ENV') or define('YII_ENV', 'prod');
   ```

2. **使用HTTPS**
   配置SSL证书，强制HTTPS访问

3. **定期更新**
   保持框架和依赖最新版本

4. **备份数据**
   定期备份数据库和用户上传文件

5. **文件权限**
   - 配置文件：644
   - 可写目录：755
   - PHP文件：644

## 技术支持

- **项目网站**: https://610000.xyz/
- **原始项目**: https://github.com/SimpleForum/SimpleForum
- **文档**: 查看项目README.md
- **问题反馈**: 通过GitHub Issues提交

---

**注意**: 请勿将包含敏感信息的配置文件提交到版本控制系统。
