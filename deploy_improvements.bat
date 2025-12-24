@echo off
REM SimpleForum 改进部署脚本 (Windows版本)
REM 使用方法: deploy_improvements.bat

setlocal enabledelayedexpansion

echo ================================
echo SimpleForum 改进部署脚本
echo ================================
echo.

REM 检查PHP
echo 1. 检查环境...
php -v > nul 2>&1
if errorlevel 1 (
    echo [错误] 未找到PHP，请确保PHP已安装并添加到PATH
    pause
    exit /b 1
)
echo [完成] PHP已安装

REM 检查Composer
composer --version > nul 2>&1
if errorlevel 1 (
    echo [错误] 未找到Composer，请先安装Composer
    pause
    exit /b 1
)
echo [完成] Composer已安装
echo.

REM 备份提醒
echo 2. 数据库备份...
echo 请手动备份数据库！
echo 使用以下命令:
echo mysqldump -u root -p simpleforum ^> backup_%date:~0,4%%date:~5,2%%date:~8,2%.sql
echo.
pause

REM 更新依赖
echo.
echo 3. 更新Composer依赖...
call composer update --no-dev --optimize-autoloader
if errorlevel 1 (
    echo [错误] Composer更新失败
    pause
    exit /b 1
)
echo [完成] 依赖已更新
echo.

REM Redis支持
set /p INSTALL_REDIS="是否安装Redis支持? (y/n): "
if /i "%INSTALL_REDIS%"=="y" (
    echo 安装yii2-redis...
    call composer require yiisoft/yii2-redis
    
    if exist "core\config\cache.php.example" (
        copy /y "core\config\cache.php.example" "core\config\cache.php"
        echo [完成] Redis配置文件已创建
        echo [提示] 请编辑 core\config\cache.php 并在 web.php 中启用
    )
)
echo.

REM 运行迁移
echo 4. 运行数据库迁移...
php yii migrate --interactive=0
if errorlevel 1 (
    echo [警告] 数据库迁移可能有问题，请检查
) else (
    echo [完成] 数据库迁移完成
)
echo.

REM 清除缓存
echo 5. 清除缓存...
php yii cache/flush-all
if exist "web\assets" (
    del /q "web\assets\*.*" 2>nul
    echo [完成] 资产缓存已清除
)
echo [完成] 缓存已清除
echo.

REM 验证配置
echo 6. 验证配置...
findstr /C:"RateLimiter" "core\config\web.php" >nul 2>&1
if errorlevel 1 (
    echo [提示] 需要在 web.php 中配置新组件
    echo       请参考 IMPROVEMENTS.md
)
echo.

REM 完成
echo ================================
echo [完成] 部署完成！
echo ================================
echo.
echo 后续步骤:
echo 1. 更新 core\config\web.php 配置新组件
echo 2. 测试登录功能（验证速率限制）
echo 3. 测试注册功能（验证密码强度）
echo 4. 查看 IMPROVEMENTS.md 了解更多功能
echo 5. 监控 runtime\logs\ 目录
echo.
echo 配置文件位置:
echo - 改进文档: IMPROVEMENTS.md
echo - 升级总结: UPGRADE_SUMMARY_CN.md
echo - Redis配置: core\config\cache.php
echo.
pause
