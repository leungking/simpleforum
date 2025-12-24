#!/bin/bash
# SimpleForum 改进部署脚本
# 使用方法: ./deploy_improvements.sh

set -e  # 遇到错误立即退出

echo "================================"
echo "SimpleForum 改进部署脚本"
echo "================================"
echo ""

# 颜色定义
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
RED='\033[0;31m'
NC='\033[0m' # No Color

# 检查PHP版本
echo "1. 检查环境..."
PHP_VERSION=$(php -r "echo PHP_VERSION;")
echo -e "${GREEN}✓${NC} PHP版本: $PHP_VERSION"

if ! command -v composer &> /dev/null; then
    echo -e "${RED}✗${NC} 未找到Composer，请先安装Composer"
    exit 1
fi
echo -e "${GREEN}✓${NC} Composer已安装"

# 备份数据库
echo ""
echo "2. 备份数据库..."
read -p "请输入数据库名称: " DB_NAME
read -p "请输入数据库用户名: " DB_USER
read -sp "请输入数据库密码: " DB_PASS
echo ""

BACKUP_FILE="backup_$(date +%Y%m%d_%H%M%S).sql"
mysqldump -u "$DB_USER" -p"$DB_PASS" "$DB_NAME" > "$BACKUP_FILE" 2>/dev/null
if [ $? -eq 0 ]; then
    echo -e "${GREEN}✓${NC} 数据库已备份至: $BACKUP_FILE"
else
    echo -e "${RED}✗${NC} 数据库备份失败"
    exit 1
fi

# 安装依赖
echo ""
echo "3. 更新Composer依赖..."
composer update --no-dev --optimize-autoloader
echo -e "${GREEN}✓${NC} 依赖已更新"

# 询问是否安装Redis
echo ""
read -p "是否安装Redis支持? (y/n): " INSTALL_REDIS
if [ "$INSTALL_REDIS" = "y" ]; then
    echo "安装yii2-redis..."
    composer require yiisoft/yii2-redis
    
    if [ -f "core/config/cache.php.example" ]; then
        cp core/config/cache.php.example core/config/cache.php
        echo -e "${GREEN}✓${NC} Redis配置文件已创建，请编辑 core/config/cache.php"
        echo -e "${YELLOW}!${NC} 别忘了在 web.php 中启用Redis缓存"
    fi
fi

# 运行数据库迁移
echo ""
echo "4. 运行数据库迁移..."
php yii migrate --interactive=0
echo -e "${GREEN}✓${NC} 数据库迁移完成"

# 清除缓存
echo ""
echo "5. 清除缓存..."
php yii cache/flush-all
rm -rf web/assets/*
echo -e "${GREEN}✓${NC} 缓存已清除"

# 设置权限
echo ""
echo "6. 设置文件权限..."
chmod -R 755 core/runtime
chmod -R 755 web/assets
chmod -R 755 upload
echo -e "${GREEN}✓${NC} 权限已设置"

# 验证配置
echo ""
echo "7. 验证配置..."

# 检查是否需要更新web.php
if ! grep -q "RateLimiter" core/config/web.php; then
    echo -e "${YELLOW}!${NC} 注意: 需要手动在 web.php 中配置新组件"
    echo "   请参考 IMPROVEMENTS.md 进行配置"
fi

# 完成
echo ""
echo "================================"
echo -e "${GREEN}✓ 部署完成！${NC}"
echo "================================"
echo ""
echo "后续步骤："
echo "1. 更新 core/config/web.php 配置新组件"
echo "2. 测试登录功能（验证速率限制）"
echo "3. 测试注册功能（验证密码强度）"
echo "4. 查看 IMPROVEMENTS.md 了解更多功能"
echo "5. 监控 runtime/logs/ 目录"
echo ""
echo "数据库备份位置: $BACKUP_FILE"
echo ""
