# 彩虹易支付系统 — 云服务器部署文档

## 目录

1. [环境要求](#1-环境要求)
2. [服务器初始化](#2-服务器初始化)
3. [安装 LNMP 环境](#3-安装-lnmp-环境)
4. [部署项目代码](#4-部署项目代码)
5. [配置数据库](#5-配置数据库)
6. [配置 Nginx](#6-配置-nginx)
7. [运行安装向导](#7-运行安装向导)
8. [配置定时任务](#8-配置定时任务)
9. [配置 HTTPS / SSL](#9-配置-https--ssl)
10. [目录权限与安全加固](#10-目录权限与安全加固)
11. [常见问题](#11-常见问题)

---

## 1. 环境要求

| 组件 | 最低版本 | 推荐版本 |
|------|---------|---------|
| 操作系统 | CentOS 7 / Ubuntu 20.04 | Ubuntu 22.04 LTS |
| Web 服务器 | Nginx 1.18 | Nginx 1.24 |
| PHP | 7.4 | 8.1 |
| MySQL / MariaDB | 5.7 | MySQL 8.0 |
| 内存 | 1 GB | 2 GB+ |
| 磁盘 | 20 GB | 40 GB+ |

**必须开放的端口：**

- `80` — HTTP
- `443` — HTTPS
- `22` — SSH（建议修改为非标准端口）

---

## 2. 服务器初始化

以 Ubuntu 22.04 为例，使用 root 登录后执行：

```bash
# 更新系统
apt update && apt upgrade -y

# 安装基础工具
apt install -y curl wget git unzip vim ufw

# 配置防火墙
ufw allow 22/tcp
ufw allow 80/tcp
ufw allow 443/tcp
ufw enable

# 设置时区为中国标准时间
timedatectl set-timezone Asia/Shanghai
```

---

## 3. 安装 LNMP 环境

### 3.1 安装 Nginx

```bash
apt install -y nginx
systemctl enable nginx
systemctl start nginx
```

### 3.2 安装 PHP 8.1

```bash
apt install -y software-properties-common
add-apt-repository ppa:ondrej/php -y
apt update

apt install -y php8.1-fpm php8.1-mysql php8.1-curl php8.1-gd \
    php8.1-mbstring php8.1-xml php8.1-zip php8.1-bcmath php8.1-redis

systemctl enable php8.1-fpm
systemctl start php8.1-fpm
```

验证安装：

```bash
php -v
```

### 3.3 安装 MySQL 8.0

```bash
apt install -y mysql-server
systemctl enable mysql
systemctl start mysql

# 安全初始化（设置 root 密码、移除匿名用户等）
mysql_secure_installation
```

### 3.4 创建数据库和用户

```bash
mysql -u root -p
```

在 MySQL 命令行中执行：

```sql
CREATE DATABASE epay CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'epayuser'@'localhost' IDENTIFIED BY '你的强密码';
GRANT ALL PRIVILEGES ON epay.* TO 'epayuser'@'localhost';
FLUSH PRIVILEGES;
EXIT;
```

---

## 4. 部署项目代码

### 4.1 上传代码

**方式一：Git 克隆（推荐）**

```bash
cd /var/www
git clone <你的仓库地址> epay
```

**方式二：SFTP / SCP 上传**

在本地执行：

```bash
scp -r /path/to/Epayzg root@<服务器IP>:/var/www/epay
```

### 4.2 设置目录权限

```bash
chown -R www-data:www-data /var/www/epay
find /var/www/epay -type d -exec chmod 755 {} \;
find /var/www/epay -type f -exec chmod 644 {} \;

# 上传目录需要写权限
chmod -R 775 /var/www/epay/upload 2>/dev/null || true
chmod -R 775 /var/www/epay/plugins 2>/dev/null || true
```

---

## 5. 配置数据库

编辑项目根目录下的 `config.php`，填入第 3.4 步创建的数据库信息：

```php
<?php
$dbconfig = array(
    'host'   => 'localhost',
    'port'   => 3306,
    'user'   => 'epayuser',       // 数据库用户名
    'pwd'    => '你的强密码',      // 数据库密码
    'dbname' => 'epay',           // 数据库名
    'dbqz'   => 'pay'             // 数据表前缀，保持默认即可
);
```

---

## 6. 配置 Nginx

创建站点配置文件：

```bash
vim /etc/nginx/sites-available/epay
```

写入以下内容（将 `your-domain.com` 替换为你的实际域名）：

```nginx
server {
    listen 80;
    server_name your-domain.com www.your-domain.com;
    root /var/www/epay;
    index index.php index.html;

    charset utf-8;
    client_max_body_size 20M;

    # 伪静态规则（来自项目 nginx.txt）
    location / {
        if (!-e $request_filename) {
            rewrite ^/(.[a-zA-Z0-9\-\_]+)\.html$ /index.php?mod=$1 last;
        }
        rewrite ^/pay/(.*)$  /pay.php?s=$1  last;
        rewrite ^/api/(.*)$  /api.php?s=$1  last;
        rewrite ^/doc/(.[a-zA-Z0-9\-\_]+)\.html$ /index.php?doc=$1 last;
    }

    # 禁止直接访问敏感目录
    location ^~ /plugins  { deny all; }
    location ^~ /includes { deny all; }
    location ^~ /install  { deny all; }

    # PHP 处理
    location ~ \.php$ {
        fastcgi_pass   unix:/run/php/php8.1-fpm.sock;
        fastcgi_index  index.php;
        fastcgi_param  SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include        fastcgi_params;
    }

    # 静态资源缓存
    location ~* \.(js|css|png|jpg|jpeg|gif|ico|svg|woff2?)$ {
        expires 30d;
        add_header Cache-Control "public, no-transform";
    }

    # 日志
    access_log /var/log/nginx/epay_access.log;
    error_log  /var/log/nginx/epay_error.log;
}
```

启用站点并重载 Nginx：

```bash
ln -s /etc/nginx/sites-available/epay /etc/nginx/sites-enabled/epay
nginx -t          # 检查配置语法
systemctl reload nginx
```

---

## 7. 运行安装向导

1. 浏览器访问 `http://your-domain.com/install/`
2. 按照页面提示逐步完成安装：
   - **步骤 1**：确认环境检测全部通过
   - **步骤 2**：确认 `config.php` 已填写正确的数据库信息，点击"下一步"
   - **步骤 3**：系统自动导入 SQL，完成后跳转到后台登录页
3. 安装完成后，**立即删除或禁止访问 install 目录**：

```bash
rm -rf /var/www/epay/install
# 或者在 Nginx 配置中已有 deny all，保留目录也可
```

**默认后台账号：**

| 字段 | 默认值 |
|------|--------|
| 后台地址 | `http://your-domain.com/admin/` |
| 用户名 | `admin` |
| 密码 | `123456` |
| 支付密码 | `123456` |

> **登录后立即修改默认密码和支付密码！**

---

## 8. 配置定时任务

系统的结算、订单超时等功能依赖 `cron.php`，需要配置 Linux 定时任务。

首先在后台 **系统设置 → 监控密钥** 中设置一个随机密钥（例如 `abc123xyz`）。

然后添加 crontab：

```bash
crontab -e
```

写入以下内容（将密钥替换为你设置的值）：

```cron
# 每分钟执行一次订单监控
* * * * * curl -s "http://your-domain.com/cron.php?key=abc123xyz&do=order" > /dev/null 2>&1

# 每天凌晨 2 点执行结算
0 2 * * * curl -s "http://your-domain.com/cron.php?key=abc123xyz&do=settle" > /dev/null 2>&1
```

---

## 9. 配置 HTTPS / SSL

推荐使用 Certbot 申请免费的 Let's Encrypt 证书：

```bash
apt install -y certbot python3-certbot-nginx

# 自动申请并配置证书
certbot --nginx -d your-domain.com -d www.your-domain.com

# 测试自动续期
certbot renew --dry-run
```

Certbot 会自动修改 Nginx 配置，添加 HTTPS 监听和 HTTP → HTTPS 跳转。

---

## 10. 目录权限与安全加固

### 10.1 PHP 配置优化

编辑 `/etc/php/8.1/fpm/php.ini`：

```ini
; 关闭危险函数
disable_functions = exec,passthru,shell_exec,system,proc_open,popen

; 隐藏 PHP 版本
expose_php = Off

; 上传限制
upload_max_filesize = 10M
post_max_size = 20M

; 时区
date.timezone = Asia/Shanghai
```

重启 PHP-FPM：

```bash
systemctl restart php8.1-fpm
```

### 10.2 MySQL 安全

```bash
# 禁止 MySQL 监听外网（默认已是 127.0.0.1，确认即可）
grep bind-address /etc/mysql/mysql.conf.d/mysqld.cnf
```

### 10.3 文件权限检查

```bash
# config.php 只允许 web 用户读取，禁止其他用户
chmod 640 /var/www/epay/config.php
chown www-data:www-data /var/www/epay/config.php
```

### 10.4 定期备份数据库

```bash
# 创建备份脚本
cat > /usr/local/bin/epay-backup.sh << 'EOF'
#!/bin/bash
DATE=$(date +%Y%m%d_%H%M%S)
BACKUP_DIR=/var/backups/epay
mkdir -p $BACKUP_DIR
mysqldump -u epayuser -p'你的强密码' epay | gzip > $BACKUP_DIR/epay_$DATE.sql.gz
# 只保留最近 7 天的备份
find $BACKUP_DIR -name "*.sql.gz" -mtime +7 -delete
EOF

chmod +x /usr/local/bin/epay-backup.sh

# 每天凌晨 3 点备份
echo "0 3 * * * /usr/local/bin/epay-backup.sh" | crontab -
```

---

## 11. 常见问题

**Q: 访问页面显示 403 Forbidden**

检查目录权限和 Nginx 的 `root` 路径是否正确：

```bash
ls -la /var/www/epay/index.php
nginx -t
```

**Q: 访问页面显示 502 Bad Gateway**

PHP-FPM 未运行或 sock 路径不匹配：

```bash
systemctl status php8.1-fpm
ls /run/php/
```

确认 Nginx 配置中的 `fastcgi_pass` 路径与实际 sock 文件一致。

**Q: 安装时提示"数据库连接失败"**

- 确认 `config.php` 中的用户名、密码、数据库名填写正确
- 确认 MySQL 服务正在运行：`systemctl status mysql`
- 用命令行测试连接：`mysql -u epayuser -p epay`

**Q: 定时任务不执行**

- 确认 crontab 已保存：`crontab -l`
- 确认 cron 服务运行：`systemctl status cron`
- 手动执行 curl 命令测试返回值是否正常

**Q: 上传文件失败**

```bash
chmod -R 775 /var/www/epay/upload
chown -R www-data:www-data /var/www/epay/upload
```

---

> 部署完成后建议：修改后台默认密码 → 配置 SSL → 删除 install 目录 → 设置定时任务 → 测试支付流程。
