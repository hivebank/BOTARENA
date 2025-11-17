# Apache Deployment Guide

Complete Apache configuration guide for Hyperliquid Trading SaaS.

## Prerequisites

- Ubuntu 22.04 LTS (or similar)
- Apache 2.4+
- PHP 8.0+
- MongoDB 5.0+

## Installation

### 1. Install Apache and PHP

```bash
sudo apt update
sudo apt install -y apache2 php8.1 php8.1-cli php8.1-fpm php8.1-common
```

### 2. Install PHP Extensions

```bash
sudo apt install -y \
    php8.1-mongodb \
    php8.1-curl \
    php8.1-json \
    php8.1-mbstring \
    php8.1-xml \
    php8.1-zip
```

### 3. Enable PHP Extensions

```bash
sudo phpenmod mongodb
sudo phpenmod curl
sudo phpenmod mbstring
```

### 4. Enable Apache Modules

```bash
sudo a2enmod rewrite
sudo a2enmod headers
sudo a2enmod ssl
sudo a2enmod proxy
sudo a2enmod proxy_http
```

### 5. Restart Apache

```bash
sudo systemctl restart apache2
```

## Apache Configuration

### Virtual Host Configuration

Create Apache virtual host:

```bash
sudo nano /etc/apache2/sites-available/hyperliquid.conf
```

Paste the following configuration:

```apache
<VirtualHost *:80>
    ServerName yourdomain.com
    ServerAlias www.yourdomain.com
    ServerAdmin admin@yourdomain.com

    DocumentRoot /var/www/hyperliquid/public

    <Directory /var/www/hyperliquid/public>
        Options -Indexes +FollowSymLinks
        AllowOverride All
        Require all granted

        # Security headers
        Header set X-Content-Type-Options "nosniff"
        Header set X-Frame-Options "SAMEORIGIN"
        Header set X-XSS-Protection "1; mode=block"
        Header set Referrer-Policy "strict-origin-when-cross-origin"
    </Directory>

    # API directory
    <Directory /var/www/hyperliquid/api>
        Options -Indexes +FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>

    # Deny access to config directory
    <Directory /var/www/hyperliquid/config>
        Require all denied
    </Directory>

    # Deny access to python directory
    <Directory /var/www/hyperliquid/python>
        Require all denied
    </Directory>

    # Deny access to sensitive files
    <FilesMatch "^\.">
        Require all denied
    </FilesMatch>

    <FilesMatch "\.(env|json|md|sh)$">
        Require all denied
    </FilesMatch>

    # Logs
    ErrorLog ${APACHE_LOG_DIR}/hyperliquid_error.log
    CustomLog ${APACHE_LOG_DIR}/hyperliquid_access.log combined

    # PHP Configuration
    php_value upload_max_filesize 20M
    php_value post_max_size 20M
    php_value max_execution_time 300
    php_value max_input_time 300
    php_value memory_limit 256M
</VirtualHost>
```

### SSL/HTTPS Configuration (Production)

For production with SSL:

```bash
sudo nano /etc/apache2/sites-available/hyperliquid-ssl.conf
```

```apache
<VirtualHost *:443>
    ServerName yourdomain.com
    ServerAlias www.yourdomain.com
    ServerAdmin admin@yourdomain.com

    DocumentRoot /var/www/hyperliquid/public

    # SSL Configuration
    SSLEngine on
    SSLCertificateFile /etc/ssl/certs/yourdomain.crt
    SSLCertificateKeyFile /etc/ssl/private/yourdomain.key
    SSLCertificateChainFile /etc/ssl/certs/chain.crt

    # Security headers
    Header always set Strict-Transport-Security "max-age=63072000; includeSubDomains; preload"
    Header set X-Content-Type-Options "nosniff"
    Header set X-Frame-Options "SAMEORIGIN"
    Header set X-XSS-Protection "1; mode=block"
    Header set Content-Security-Policy "default-src 'self'; script-src 'self' 'unsafe-inline' 'unsafe-eval'; style-src 'self' 'unsafe-inline';"

    <Directory /var/www/hyperliquid/public>
        Options -Indexes +FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>

    <Directory /var/www/hyperliquid/api>
        Options -Indexes +FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>

    # Deny access to sensitive directories
    <Directory /var/www/hyperliquid/config>
        Require all denied
    </Directory>

    <Directory /var/www/hyperliquid/python>
        Require all denied
    </Directory>

    # Deny access to sensitive files
    <FilesMatch "^\.">
        Require all denied
    </FilesMatch>

    <FilesMatch "\.(env|json|md|sh)$">
        Require all denied
    </FilesMatch>

    ErrorLog ${APACHE_LOG_DIR}/hyperliquid_ssl_error.log
    CustomLog ${APACHE_LOG_DIR}/hyperliquid_ssl_access.log combined
</VirtualHost>

# Redirect HTTP to HTTPS
<VirtualHost *:80>
    ServerName yourdomain.com
    ServerAlias www.yourdomain.com

    Redirect permanent / https://yourdomain.com/
</VirtualHost>
```

### Enable Sites

```bash
# Enable site
sudo a2ensite hyperliquid.conf

# For SSL
sudo a2ensite hyperliquid-ssl.conf

# Disable default site
sudo a2dissite 000-default.conf

# Test configuration
sudo apache2ctl configtest

# Reload Apache
sudo systemctl reload apache2
```

## .htaccess Configuration

Create `.htaccess` in `public/` directory:

```bash
nano public/.htaccess
```

```apache
# Enable rewrite engine
RewriteEngine On

# Force HTTPS (production only)
# RewriteCond %{HTTPS} off
# RewriteRule ^(.*)$ https://%{HTTP_HOST}%{REQUEST_URI} [L,R=301]

# Redirect www to non-www
RewriteCond %{HTTP_HOST} ^www\.(.+)$ [NC]
RewriteRule ^(.*)$ http://%1/$1 [R=301,L]

# Route API requests
RewriteCond %{REQUEST_URI} ^/api/
RewriteRule ^api/(.*)$ ../api/$1 [L]

# Front controller pattern
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule ^(.*)$ index.php [L,QSA]

# Security
<FilesMatch "\.(env|json|md|sh|log)$">
    Require all denied
</FilesMatch>

# Prevent directory browsing
Options -Indexes

# Disable server signature
ServerSignature Off

# Set default charset
AddDefaultCharset UTF-8

# Compress text files
<IfModule mod_deflate.c>
    AddOutputFilterByType DEFLATE text/html text/plain text/xml text/css text/javascript application/javascript application/json
</IfModule>

# Cache static resources
<IfModule mod_expires.c>
    ExpiresActive On
    ExpiresByType image/jpg "access plus 1 year"
    ExpiresByType image/jpeg "access plus 1 year"
    ExpiresByType image/gif "access plus 1 year"
    ExpiresByType image/png "access plus 1 year"
    ExpiresByType image/svg+xml "access plus 1 year"
    ExpiresByType text/css "access plus 1 month"
    ExpiresByType application/javascript "access plus 1 month"
    ExpiresByType text/javascript "access plus 1 month"
</IfModule>
```

## File Permissions

Set correct permissions:

```bash
# Set ownership
sudo chown -R www-data:www-data /var/www/hyperliquid

# Set directory permissions
sudo find /var/www/hyperliquid -type d -exec chmod 755 {} \;

# Set file permissions
sudo find /var/www/hyperliquid -type f -exec chmod 644 {} \;

# Make logs writable
sudo chmod -R 777 /var/www/hyperliquid/logs

# Make scripts executable
sudo chmod +x /var/www/hyperliquid/install.sh
sudo chmod +x /var/www/hyperliquid/startup.sh
```

## Firewall Configuration

```bash
# Enable firewall
sudo ufw enable

# Allow SSH
sudo ufw allow ssh

# Allow HTTP
sudo ufw allow 80/tcp

# Allow HTTPS
sudo ufw allow 443/tcp

# Check status
sudo ufw status
```

## SSL Certificate Setup (Let's Encrypt)

### Install Certbot

```bash
sudo apt install -y certbot python3-certbot-apache
```

### Obtain Certificate

```bash
sudo certbot --apache -d yourdomain.com -d www.yourdomain.com
```

### Auto-renewal

Certbot automatically sets up auto-renewal. Test it:

```bash
sudo certbot renew --dry-run
```

## Performance Tuning

### Apache MPM Configuration

```bash
sudo nano /etc/apache2/mods-available/mpm_prefork.conf
```

```apache
<IfModule mpm_prefork_module>
    StartServers             5
    MinSpareServers          5
    MaxSpareServers          10
    MaxRequestWorkers        150
    MaxConnectionsPerChild   1000
</IfModule>
```

### PHP-FPM Configuration

```bash
sudo nano /etc/php/8.1/fpm/pool.d/www.conf
```

```ini
pm = dynamic
pm.max_children = 50
pm.start_servers = 5
pm.min_spare_servers = 5
pm.max_spare_servers = 10
pm.max_requests = 500
```

Restart services:

```bash
sudo systemctl restart apache2
sudo systemctl restart php8.1-fpm
```

## Monitoring

### Enable Apache Status

```bash
sudo a2enmod status
```

Add to Apache config:

```apache
<Location "/server-status">
    SetHandler server-status
    Require ip 127.0.0.1
</Location>
```

Access: `http://localhost/server-status`

### Log Rotation

```bash
sudo nano /etc/logrotate.d/hyperliquid
```

```
/var/www/hyperliquid/logs/*.log {
    daily
    rotate 14
    compress
    delaycompress
    notifempty
    create 0640 www-data www-data
    sharedscripts
}
```

## Troubleshooting

### Apache Won't Start

```bash
# Check syntax
sudo apache2ctl configtest

# Check logs
sudo tail -f /var/log/apache2/error.log

# Check service status
sudo systemctl status apache2
```

### PHP Not Working

```bash
# Check PHP is installed
php -v

# Check Apache PHP module
sudo a2query -m php8.1

# Enable if not enabled
sudo a2enmod php8.1
sudo systemctl restart apache2
```

### Permission Denied Errors

```bash
# Reset permissions
sudo chown -R www-data:www-data /var/www/hyperliquid
sudo find /var/www/hyperliquid -type d -exec chmod 755 {} \;
sudo find /var/www/hyperliquid -type f -exec chmod 644 {} \;
```

### MongoDB Extension Not Found

```bash
# Install extension
sudo apt install php8.1-mongodb

# Enable extension
sudo phpenmod mongodb

# Verify
php -m | grep mongodb

# Restart Apache
sudo systemctl restart apache2
```

## Security Hardening

### 1. Hide PHP Version

```bash
sudo nano /etc/php/8.1/apache2/php.ini
```

Set:
```ini
expose_php = Off
```

### 2. Disable Directory Listing

Already configured in .htaccess:
```apache
Options -Indexes
```

### 3. Limit Request Size

In Apache config:
```apache
LimitRequestBody 10485760  # 10MB
```

### 4. Enable ModSecurity (Optional)

```bash
sudo apt install libapache2-mod-security2
sudo a2enmod security2
sudo systemctl restart apache2
```

### 5. Fail2Ban for Rate Limiting

```bash
sudo apt install fail2ban
sudo systemctl enable fail2ban
sudo systemctl start fail2ban
```

## Production Checklist

- [ ] SSL/TLS certificate installed
- [ ] HTTPS redirect enabled
- [ ] Firewall configured
- [ ] File permissions set correctly
- [ ] PHP production settings enabled
- [ ] Error logging configured
- [ ] Backup system in place
- [ ] Monitoring enabled
- [ ] Security headers configured
- [ ] Rate limiting enabled

## Support

For Apache-specific issues:
- Apache error logs: `/var/log/apache2/error.log`
- Apache access logs: `/var/log/apache2/access.log`
- Application logs: `/var/www/hyperliquid/logs/`
