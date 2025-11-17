# Hyperliquid Trading SaaS - Setup Guide

Complete setup guide for the Hyperliquid Trading Bot SaaS platform.

## Table of Contents

1. [Requirements](#requirements)
2. [Installation](#installation)
3. [Configuration](#configuration)
4. [Database Setup](#database-setup)
5. [Apache Configuration](#apache-configuration)
6. [Starting Services](#starting-services)
7. [Testing](#testing)
8. [Troubleshooting](#troubleshooting)

## Requirements

### System Requirements

- **OS**: Ubuntu 22.04 LTS (or similar Linux distribution)
- **RAM**: Minimum 2GB, Recommended 4GB+
- **Storage**: Minimum 10GB free space
- **CPU**: 2+ cores recommended

### Software Requirements

- **PHP**: 8.0 or higher
  - Extensions: mongodb, curl, json, mbstring
- **Python**: 3.8 or higher
- **MongoDB**: 5.0 or higher
- **Apache**: 2.4 or higher
- **Git**: For cloning the repository

## Installation

### 1. Clone Repository

```bash
git clone <repository-url>
cd hyperliquid-trading-saas
```

### 2. Run Installation Script

```bash
chmod +x install.sh
chmod +x startup.sh
./install.sh
```

The installation script will:
- Create Python virtual environment
- Install all Python dependencies
- Create necessary directories
- Set up MongoDB indexes
- Create default admin user
- Generate `.env` file

### 3. Install Python Dependencies

```bash
pip install -r requirements.txt
```

## Configuration

### Environment Variables

Edit `.env` file with your configuration:

```bash
# MongoDB Configuration
MONGO_URI=mongodb://localhost:27017
MONGO_DB=hyperliquid_saas

# JWT Secret (REQUIRED - Generate a secure random string!)
JWT_SECRET=<your-secure-random-string>

# Hyperliquid API
HYPERLIQUID_API_URL=https://api.hyperliquid.xyz
HYPERLIQUID_WS_URL=wss://api.hyperliquid.xyz/ws

# Crypto Wallets (REQUIRED - Your payment addresses!)
SOL_WALLET=<your-solana-wallet-address>
ETH_WALLET=<your-ethereum-wallet-address>

# RPC Endpoints
SOL_RPC=https://api.mainnet-beta.solana.com
ETH_RPC=https://eth.llamarpc.com

# Admin Credentials (CHANGE THESE!)
ADMIN_EMAIL=admin@yourdomain.com
ADMIN_PASSWORD=<strong-password>
```

### Generate JWT Secret

```bash
# Generate a secure random string
openssl rand -base64 64
```

Copy the output to `JWT_SECRET` in `.env`.

### PHP Configuration

Edit `config/config.php` if needed (defaults should work):

```php
define('MONGO_URI', getenv('MONGO_URI') ?: 'mongodb://localhost:27017');
define('MONGO_DB', 'hyperliquid_saas');
define('JWT_SECRET', getenv('JWT_SECRET') ?: 'CHANGE_THIS');
```

### Settings Configuration

Edit `config/settings.json` to customize:

- Trading parameters (max leverage, position sizes)
- Risk management settings
- Pricing tiers
- Strategy configurations
- System settings

## Database Setup

### Install MongoDB

**Ubuntu/Debian:**

```bash
wget -qO - https://www.mongodb.org/static/pgp/server-6.0.asc | sudo apt-key add -
echo "deb [ arch=amd64,arm64 ] https://repo.mongodb.org/apt/ubuntu jammy/mongodb-org/6.0 multiverse" | sudo tee /etc/apt/sources.list.d/mongodb-org-6.0.list
sudo apt-get update
sudo apt-get install -y mongodb-org
```

### Start MongoDB

```bash
sudo systemctl start mongod
sudo systemctl enable mongod
sudo systemctl status mongod
```

### Verify Connection

```bash
mongosh
> use hyperliquid_saas
> show collections
> exit
```

### Create Indexes

The `install.sh` script automatically creates indexes. To manually create:

```bash
mongosh < setup_indexes.js
```

## Apache Configuration

See [DEPLOY_APACHE.md](DEPLOY_APACHE.md) for detailed Apache setup.

Quick setup:

### 1. Install PHP MongoDB Extension

```bash
sudo apt-get install php-mongodb
sudo phpenmod mongodb
```

### 2. Enable Apache Modules

```bash
sudo a2enmod rewrite
sudo a2enmod headers
sudo systemctl restart apache2
```

### 3. Create VirtualHost

```bash
sudo nano /etc/apache2/sites-available/hyperliquid.conf
```

Paste the configuration from `DEPLOY_APACHE.md`, then:

```bash
sudo a2ensite hyperliquid.conf
sudo systemctl reload apache2
```

## Starting Services

### Start Python Workers

```bash
./startup.sh
```

This starts:
- **Bot Runner**: Executes trading bots
- **Scheduler**: Handles periodic tasks

### Check Worker Status

```bash
# View bot runner logs
tail -f logs/bot_runner.log

# View scheduler logs
tail -f logs/scheduler.log

# View application logs
tail -f logs/app_$(date +%Y-%m-%d).log
```

### Stop Workers

```bash
pkill -f 'python.*bot_runner.py'
pkill -f 'python.*scheduler.py'
```

## Testing

### 1. Access Web Interface

Open browser to: `http://localhost` or `http://your-domain.com`

### 2. Login with Admin Account

```
Email: admin@localhost
Password: admin123
```

**⚠️ IMPORTANT: Change the admin password immediately!**

### 3. Test Bot Creation

1. Go to "Create Bot"
2. Fill in bot details
3. Select "Paper Trading" mode
4. Create and start the bot
5. Monitor in "My Bots" section

### 4. Test API Endpoints

```bash
# Test auth endpoint
curl -X POST http://localhost/api/auth.php \
  -H "Content-Type: application/json" \
  -d '{"action":"login","email":"admin@localhost","password":"admin123"}'

# Test bots endpoint (use token from login)
curl -X GET http://localhost/api/bots.php \
  -H "Authorization: Bearer <your-jwt-token>"
```

### 5. Monitor Workers

```bash
# Check if workers are running
ps aux | grep python

# Check MongoDB activity
mongosh
> use hyperliquid_saas
> db.logs.find().sort({created_at: -1}).limit(10)
```

## Troubleshooting

### MongoDB Connection Failed

```bash
# Check if MongoDB is running
sudo systemctl status mongod

# Check MongoDB logs
sudo tail -f /var/log/mongodb/mongod.log

# Restart MongoDB
sudo systemctl restart mongod
```

### PHP MongoDB Extension Missing

```bash
# Install extension
sudo apt-get install php-mongodb

# Enable extension
sudo phpenmod mongodb

# Restart Apache
sudo systemctl restart apache2

# Verify
php -m | grep mongodb
```

### Workers Not Starting

```bash
# Check Python virtual environment
source venv/bin/activate
python --version

# Check dependencies
pip list

# Run manually to see errors
python python/bot_runner.py
```

### Permission Errors

```bash
# Fix directory permissions
chmod -R 755 public
chmod -R 755 api
chmod -R 777 logs

# Fix file ownership
sudo chown -R www-data:www-data /path/to/project
```

### Apache 403 Forbidden

```bash
# Check .htaccess is present
ls -la public/.htaccess

# Check Apache config allows .htaccess
sudo nano /etc/apache2/apache2.conf
# Ensure: AllowOverride All

# Restart Apache
sudo systemctl restart apache2
```

### Cannot Login / JWT Errors

```bash
# Verify JWT_SECRET is set in .env
cat .env | grep JWT_SECRET

# Regenerate JWT_SECRET
openssl rand -base64 64

# Update .env with new secret
nano .env
```

## Production Deployment

### Security Checklist

- [ ] Change all default passwords
- [ ] Generate new JWT_SECRET
- [ ] Set up SSL/TLS (HTTPS)
- [ ] Configure firewall
- [ ] Disable debug mode in `config/config.php`
- [ ] Set up backup system
- [ ] Configure monitoring
- [ ] Set up log rotation
- [ ] Restrict database access
- [ ] Enable rate limiting

### SSL/TLS Setup

```bash
# Install Certbot
sudo apt-get install certbot python3-certbot-apache

# Get SSL certificate
sudo certbot --apache -d yourdomain.com
```

### Backup Strategy

```bash
# MongoDB backup
mongodump --db hyperliquid_saas --out /backup/mongodb

# Code backup
tar -czf /backup/code_$(date +%Y%m%d).tar.gz /path/to/project
```

## Support

For issues and questions:
- Check logs: `logs/app_YYYY-MM-DD.log`
- MongoDB logs: `/var/log/mongodb/mongod.log`
- Apache logs: `/var/log/apache2/error.log`

## License

Proprietary - All rights reserved
