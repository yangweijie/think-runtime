# FrankenPHP Runtime 部署指南

本指南提供了在不同环境中部署 FrankenPHP Runtime 的详细步骤和最佳实践。

## 🚀 快速部署

### 开发环境

```bash
# 1. 安装依赖
composer require yangweijie/think-runtime

# 2. 复制配置
cp vendor/yangweijie/think-runtime/config/runtime.php config/

# 3. 启动开发服务器
php think runtime:start frankenphp --listen=:8080 --debug=true

# 4. 验证部署
curl http://localhost:8080/
```

### 生产环境

```bash
# 1. 安装 FrankenPHP
curl -fsSL https://frankenphp.dev/install.sh | bash

# 2. 配置生产环境
php think runtime:start frankenphp \
  --listen=:80 \
  --worker_num=4 \
  --max_requests=1000 \
  --debug=false

# 3. 使用进程管理器（推荐）
# 见下方 Systemd 配置
```

## 🔧 环境配置

### 系统要求

- **PHP**: 8.1+ (推荐 8.3+)
- **FrankenPHP**: 1.7.0+
- **内存**: 最少 512MB (推荐 2GB+)
- **CPU**: 最少 1 核心 (推荐 2+ 核心)

### PHP 配置优化

```ini
; php.ini 优化配置
memory_limit = 256M
max_execution_time = 30
max_input_time = 60
post_max_size = 32M
upload_max_filesize = 32M

; OPcache 配置
opcache.enable = 1
opcache.memory_consumption = 128
opcache.interned_strings_buffer = 8
opcache.max_accelerated_files = 4000
opcache.revalidate_freq = 2
opcache.fast_shutdown = 1
```

## 🐳 Docker 部署

### Dockerfile

```dockerfile
FROM dunglas/frankenphp:latest

# 安装 PHP 扩展
RUN install-php-extensions \
    pdo_mysql \
    redis \
    zip \
    gd \
    intl

# 复制应用代码
COPY . /app
WORKDIR /app

# 安装 Composer 依赖
RUN composer install --no-dev --optimize-autoloader

# 设置权限
RUN chown -R www-data:www-data /app/runtime

# 暴露端口
EXPOSE 80

# 启动命令
CMD ["php", "think", "runtime:start", "frankenphp", "--listen=:80"]
```

### docker-compose.yml

```yaml
version: '3.8'

services:
  app:
    build: .
    ports:
      - "80:80"
    environment:
      - APP_ENV=production
      - APP_DEBUG=false
    volumes:
      - ./runtime:/app/runtime
    restart: unless-stopped
    
  mysql:
    image: mysql:8.0
    environment:
      MYSQL_ROOT_PASSWORD: password
      MYSQL_DATABASE: thinkphp
    volumes:
      - mysql_data:/var/lib/mysql
    restart: unless-stopped
    
  redis:
    image: redis:alpine
    restart: unless-stopped

volumes:
  mysql_data:
```

## ⚙️ 进程管理

### Systemd 服务配置

```ini
# /etc/systemd/system/thinkphp-frankenphp.service
[Unit]
Description=ThinkPHP FrankenPHP Runtime
After=network.target

[Service]
Type=simple
User=www-data
Group=www-data
WorkingDirectory=/var/www/html
ExecStart=/usr/local/bin/php think runtime:start frankenphp --listen=:80 --worker_num=4
ExecReload=/bin/kill -USR1 $MAINPID
Restart=always
RestartSec=5
StandardOutput=journal
StandardError=journal

[Install]
WantedBy=multi-user.target
```

启动服务：
```bash
sudo systemctl daemon-reload
sudo systemctl enable thinkphp-frankenphp
sudo systemctl start thinkphp-frankenphp
sudo systemctl status thinkphp-frankenphp
```

### Supervisor 配置

```ini
# /etc/supervisor/conf.d/thinkphp-frankenphp.conf
[program:thinkphp-frankenphp]
command=/usr/local/bin/php think runtime:start frankenphp --listen=:8080 --worker_num=4
directory=/var/www/html
autostart=true
autorestart=true
user=www-data
numprocs=1
redirect_stderr=true
stdout_logfile=/var/log/supervisor/thinkphp-frankenphp.log
```

## 🔒 安全配置

### 防火墙设置

```bash
# UFW 配置
sudo ufw allow 80/tcp
sudo ufw allow 443/tcp
sudo ufw enable
```

### Nginx 反向代理（可选）

```nginx
server {
    listen 80;
    server_name example.com;
    
    location / {
        proxy_pass http://127.0.0.1:8080;
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
    }
}
```

## 📊 监控配置

### 健康检查端点

```php
// 添加到路由配置
Route::get('health', function() {
    $adapter = new \yangweijie\thinkRuntime\adapter\FrankenphpAdapter(app());
    
    if ($adapter->healthCheck()) {
        return json(['status' => 'healthy', 'timestamp' => time()]);
    } else {
        return json(['status' => 'unhealthy', 'timestamp' => time()], 503);
    }
});
```

### 监控脚本

```bash
#!/bin/bash
# monitor.sh - 简单的监控脚本

check_health() {
    response=$(curl -s -o /dev/null -w "%{http_code}" http://localhost:8080/health)
    if [ "$response" = "200" ]; then
        echo "$(date): Service is healthy"
        return 0
    else
        echo "$(date): Service is unhealthy (HTTP $response)"
        return 1
    fi
}

# 检查服务健康状态
if ! check_health; then
    echo "$(date): Restarting service..."
    sudo systemctl restart thinkphp-frankenphp
fi
```

## 🔧 性能调优

### FrankenPHP 配置优化

```php
// config/runtime.php
return [
    'frankenphp' => [
        'listen' => ':80',
        'worker_num' => 4,  // CPU 核心数
        'max_requests' => 1000,  // 防止内存泄漏
        'debug' => false,
        'auto_https' => true,  // 生产环境启用
        'enable_gzip' => true,
        'hosts' => ['example.com', 'www.example.com'],
    ],
];
```

### 系统级优化

```bash
# 增加文件描述符限制
echo "* soft nofile 65535" >> /etc/security/limits.conf
echo "* hard nofile 65535" >> /etc/security/limits.conf

# 优化内核参数
echo "net.core.somaxconn = 65535" >> /etc/sysctl.conf
echo "net.ipv4.tcp_max_syn_backlog = 65535" >> /etc/sysctl.conf
sysctl -p
```

## 📈 扩展部署

### 负载均衡配置

```nginx
upstream frankenphp_backend {
    server 127.0.0.1:8080;
    server 127.0.0.1:8081;
    server 127.0.0.1:8082;
    server 127.0.0.1:8083;
}

server {
    listen 80;
    server_name example.com;
    
    location / {
        proxy_pass http://frankenphp_backend;
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
    }
}
```

### 多实例部署

```bash
# 启动多个实例
php think runtime:start frankenphp --listen=:8080 --worker_num=2 &
php think runtime:start frankenphp --listen=:8081 --worker_num=2 &
php think runtime:start frankenphp --listen=:8082 --worker_num=2 &
php think runtime:start frankenphp --listen=:8083 --worker_num=2 &
```

## 🔍 故障排除

### 常见问题

1. **端口被占用**
   ```bash
   # 查找占用端口的进程
   lsof -i :8080
   # 杀死进程
   kill -9 <PID>
   ```

2. **权限问题**
   ```bash
   # 设置正确的权限
   chown -R www-data:www-data /var/www/html
   chmod -R 755 /var/www/html
   chmod -R 777 /var/www/html/runtime
   ```

3. **内存不足**
   ```bash
   # 检查内存使用
   free -h
   # 检查 PHP 内存限制
   php -i | grep memory_limit
   ```

### 日志分析

```bash
# 查看 FrankenPHP 日志
tail -f /var/log/supervisor/thinkphp-frankenphp.log

# 查看系统日志
journalctl -u thinkphp-frankenphp -f

# 查看应用错误日志
tail -f runtime/log/frankenphp_error.log
```

## ✅ 部署检查清单

### 部署前检查
- [ ] PHP 版本兼容性
- [ ] FrankenPHP 安装完成
- [ ] 依赖包安装完成
- [ ] 配置文件正确设置
- [ ] 权限设置正确

### 部署后验证
- [ ] 服务正常启动
- [ ] 健康检查通过
- [ ] 路由功能正常
- [ ] 静态文件访问正常
- [ ] 错误处理正常
- [ ] 性能指标正常

### 生产环境检查
- [ ] HTTPS 配置
- [ ] 防火墙设置
- [ ] 监控配置
- [ ] 备份策略
- [ ] 日志轮转
- [ ] 自动重启配置

---

**🎯 按照本指南部署，您将获得一个高性能、稳定可靠的 FrankenPHP Runtime 生产环境！**
