# 🚂 Railway.com Deployment Guide

Complete guide for deploying Hyperliquid Trading SaaS on Railway.com.

## 📋 Table of Contents

1. [Prerequisites](#prerequisites)
2. [Quick Deploy](#quick-deploy)
3. [Environment Variables](#environment-variables)
4. [MongoDB Setup](#mongodb-setup)
5. [Domain Configuration](#domain-configuration)
6. [Monitoring & Logs](#monitoring--logs)
7. [Scaling](#scaling)
8. [Troubleshooting](#troubleshooting)

---

## 🎯 Prerequisites

- Railway.com account (sign up at https://railway.app)
- GitHub account (for deployment from repo)
- MongoDB Atlas account OR use Railway's MongoDB plugin
- Crypto wallets for payment (SOL & ETH addresses)

---

## 🚀 Quick Deploy

### Option 1: Deploy from GitHub (Recommended)

1. **Push to GitHub**
   ```bash
   git push origin claude/hyperliquid-trading-saas-01CQTBaEvQw7dFqHbq2ggnL5
   ```

2. **Create Railway Project**
   - Go to https://railway.app/new
   - Click "Deploy from GitHub repo"
   - Select your repository
   - Select branch: `claude/hyperliquid-trading-saas-01CQTBaEvQw7dFqHbq2ggnL5`

3. **Railway Auto-Detection**
   - Railway will detect `Dockerfile` and use it automatically
   - Build will start immediately

4. **Set Environment Variables** (see below)

5. **Deploy!**
   - Railway will build and deploy
   - You'll get a URL like: `https://your-app.up.railway.app`

### Option 2: Deploy with Railway CLI

```bash
# Install Railway CLI
npm i -g @railway/cli

# Login
railway login

# Initialize project
railway init

# Link to project
railway link

# Deploy
railway up
```

---

## 🔐 Environment Variables

### Required Variables

Go to your Railway project → Variables tab and add:

#### 1. MongoDB Connection
```bash
MONGO_URI=mongodb+srv://username:password@cluster.mongodb.net/hyperliquid_saas
```

**Get this from:**
- MongoDB Atlas (see MongoDB Setup below), OR
- Railway MongoDB plugin (see MongoDB Setup below)

#### 2. JWT Secret (REQUIRED!)
```bash
JWT_SECRET=<generate-secure-random-string>
```

**Generate secure secret:**
```bash
openssl rand -base64 64
```

Copy the output and paste as `JWT_SECRET`.

#### 3. Payment Wallets
```bash
SOL_WALLET=YourSolanaWalletAddress
ETH_WALLET=YourEthereumWalletAddress
```

### Optional Variables

```bash
# Solana RPC (default provided)
SOL_RPC=https://api.mainnet-beta.solana.com

# Ethereum RPC (default provided)
ETH_RPC=https://eth.llamarpc.com

# Hyperliquid API (defaults provided)
HYPERLIQUID_API_URL=https://api.hyperliquid.xyz
HYPERLIQUID_WS_URL=wss://api.hyperliquid.xyz/ws

# Admin credentials (change after first login!)
ADMIN_EMAIL=admin@yourdomain.com
ADMIN_PASSWORD=ChangeThisPassword123
```

### Railway Auto-Set Variables

These are automatically set by Railway:
- `PORT` - The port your app should listen on
- `RAILWAY_ENVIRONMENT` - production/staging/development
- `RAILWAY_PROJECT_ID` - Your project ID
- `RAILWAY_DEPLOYMENT_ID` - Current deployment ID

---

## 💾 MongoDB Setup

### Option 1: Railway MongoDB Plugin (Easiest)

1. **Add MongoDB Plugin**
   - In your Railway project, click "+ New"
   - Select "Database" → "Add MongoDB"
   - Railway will provision a MongoDB instance

2. **Get Connection String**
   - Click on the MongoDB service
   - Go to "Variables" tab
   - Copy `MONGO_URL` value
   - Use this as your `MONGO_URI`

3. **Set Variable**
   ```bash
   MONGO_URI=${{MongoDB.MONGO_URL}}
   ```
   (Railway will auto-resolve this reference)

### Option 2: MongoDB Atlas (Recommended for Production)

1. **Create Cluster**
   - Go to https://www.mongodb.com/cloud/atlas
   - Create free cluster (M0)
   - Choose region close to your Railway deployment

2. **Configure Access**
   - Database Access: Create user with password
   - Network Access: Allow access from anywhere (0.0.0.0/0)
     - Railway uses dynamic IPs, so you need to allow all IPs

3. **Get Connection String**
   - Click "Connect" on your cluster
   - Choose "Connect your application"
   - Copy the connection string
   - Replace `<password>` with your database user password
   - Replace `<dbname>` with `hyperliquid_saas`

4. **Set in Railway**
   ```bash
   MONGO_URI=mongodb+srv://user:password@cluster.mongodb.net/hyperliquid_saas?retryWrites=true&w=majority
   ```

---

## 🌐 Domain Configuration

### Using Railway Domain

Railway provides a free domain:
- Format: `https://your-app.up.railway.app`
- SSL automatically configured
- No setup needed

### Using Custom Domain

1. **Add Domain in Railway**
   - Go to your service → Settings
   - Click "Generate Domain" or "Custom Domain"
   - Enter your domain: `app.yourdomain.com`

2. **Configure DNS**
   Add CNAME record in your DNS provider:
   ```
   Type: CNAME
   Name: app
   Value: your-app.up.railway.app
   TTL: 3600
   ```

3. **SSL Certificate**
   - Railway automatically provisions SSL via Let's Encrypt
   - May take a few minutes to activate

---

## 📊 Monitoring & Logs

### View Logs

**In Railway Dashboard:**
- Go to your service
- Click "Deployments" tab
- Click on latest deployment
- View real-time logs

**Using Railway CLI:**
```bash
railway logs
```

### Log Types

You'll see logs from:
- **Supervisor**: Service management
- **Nginx**: Web server access logs
- **PHP-FPM**: PHP errors
- **Bot Runner**: Trading bot activity
- **Scheduler**: Maintenance tasks

### Health Check

Railway will ping: `https://your-app.up.railway.app/health.php`

Expected response:
```json
{
  "status": "healthy",
  "timestamp": 1234567890,
  "checks": {
    "mongodb": "ok",
    "logs_writable": "ok",
    "php_version": "8.1.x",
    "mongodb_extension": "ok"
  },
  "environment": "production"
}
```

---

## 📈 Scaling

### Vertical Scaling (More Resources)

1. Go to project Settings
2. Click on your service
3. Under "Resources", upgrade plan:
   - **Hobby**: $5/month - 512 MB RAM, 1 vCPU
   - **Pro**: $20/month - 8 GB RAM, 8 vCPU

### Horizontal Scaling

Railway doesn't support multiple replicas in basic plan, but you can:

1. **Separate Services**
   - Create separate services for:
     - Web (PHP + Nginx)
     - Workers (Python bots)
     - Scheduler

2. **Load Balancing**
   - Use Railway's load balancer (Pro plan)
   - Or use external load balancer (Cloudflare, etc.)

---

## 🔧 Troubleshooting

### Build Fails

**Check Dockerfile:**
```bash
# Test locally
docker build -t hyperliquid-test .
docker run -p 8080:8080 hyperliquid-test
```

**Common Issues:**
- Missing dependencies in `requirements.txt`
- Incorrect file paths in Dockerfile
- Permission errors

**Solution:**
```bash
# View build logs in Railway
railway logs --deployment <deployment-id>
```

### Application Won't Start

**Check Environment Variables:**
```bash
railway variables
```

Ensure all required variables are set:
- `MONGO_URI` ✓
- `JWT_SECRET` ✓
- `SOL_WALLET` ✓
- `ETH_WALLET` ✓

**Check Health Endpoint:**
```bash
curl https://your-app.up.railway.app/health.php
```

### MongoDB Connection Failed

**Verify Connection String:**
```bash
# Test MongoDB connection
railway run python3 << 'EOF'
from pymongo import MongoClient
import os
uri = os.getenv('MONGO_URI')
client = MongoClient(uri)
client.admin.command('ping')
print("✓ Connected!")
EOF
```

**Common Issues:**
- Incorrect username/password
- Database name not specified
- IP not whitelisted (Atlas)
- Connection string format wrong

**Solution:**
- Check MongoDB Atlas Network Access
- Verify connection string format
- Ensure database user has correct permissions

### Bots Not Running

**Check Worker Logs:**
```bash
railway logs | grep bot_runner
railway logs | grep scheduler
```

**Verify MongoDB:**
```bash
# Check if bots exist in database
railway run python3 << 'EOF'
from pymongo import MongoClient
import os
client = MongoClient(os.getenv('MONGO_URI'))
db = client['hyperliquid_saas']
print(f"Bots: {db.bots.count_documents({})}")
print(f"Running: {db.bots.count_documents({'status': 'running'})}")
EOF
```

### 502 Bad Gateway

**Possible Causes:**
- PHP-FPM not running
- Nginx misconfiguration
- Port mismatch

**Solution:**
```bash
# Check supervisor status in logs
railway logs | grep supervisor

# Restart service
railway restart
```

### Memory Issues

**Monitor Usage:**
- Railway Dashboard → Metrics
- Watch for memory spikes

**Optimize:**
```python
# In Python workers, add garbage collection
import gc
gc.collect()
```

**Upgrade Plan:**
- If consistently hitting limits, upgrade to Pro plan

---

## 🔒 Security Best Practices

### 1. Change Default Credentials

After first deployment:
```bash
# Login to app
https://your-app.up.railway.app

# Login with:
Email: admin@railway.app
Password: admin123

# IMMEDIATELY change password in profile settings
```

### 2. Rotate JWT Secret

Periodically rotate JWT secret:
```bash
# Generate new secret
openssl rand -base64 64

# Update in Railway variables
# All users will need to re-login
```

### 3. Database Backups

**MongoDB Atlas:**
- Automatic backups included
- Configure backup schedule

**Railway MongoDB:**
- Use Railway's backup feature
- Or manual exports:
  ```bash
  railway run mongodump --uri $MONGO_URI
  ```

### 4. Environment Variables

- Never commit `.env` to git
- Use Railway Variables for all secrets
- Rotate credentials regularly

---

## 📱 Accessing Your App

### Default URLs

**Production:**
```
https://your-app.up.railway.app
```

**Health Check:**
```
https://your-app.up.railway.app/health.php
```

**Admin Panel:**
```
https://your-app.up.railway.app/admin/
```

### Default Credentials

```
Email: admin@railway.app
Password: admin123
```

**⚠️ CHANGE IMMEDIATELY AFTER FIRST LOGIN!**

---

## 🎯 Post-Deployment Checklist

- [ ] MongoDB connected successfully
- [ ] JWT_SECRET set to secure random value
- [ ] Wallet addresses configured
- [ ] Health check returns 200 OK
- [ ] Can login with admin credentials
- [ ] Admin password changed
- [ ] Test bot creation (paper mode)
- [ ] Monitor logs for errors
- [ ] Custom domain configured (optional)
- [ ] SSL certificate active
- [ ] Backups configured

---

## 🚀 Next Steps

1. **Create Test Bot**
   - Login to dashboard
   - Create bot in "Paper Trading" mode
   - Monitor performance

2. **Configure Payment Methods**
   - Verify SOL_WALLET and ETH_WALLET
   - Test payment flow
   - Monitor payment logs

3. **Monitor System**
   - Check logs regularly
   - Monitor bot performance
   - Review trades in dashboard

4. **Scale as Needed**
   - Monitor resource usage
   - Upgrade plan if needed
   - Optimize bot strategies

---

## 📞 Support

### Railway Issues
- Railway Docs: https://docs.railway.app
- Railway Discord: https://discord.gg/railway
- Railway Status: https://status.railway.app

### Application Issues
- Check logs: `railway logs`
- Health check: `/health.php`
- Database status: MongoDB Atlas dashboard

---

## 💡 Tips

1. **Use Staging Environment**
   - Create a separate Railway project for testing
   - Use different MongoDB database
   - Test before deploying to production

2. **Monitor Costs**
   - Railway Hobby: $5/month
   - MongoDB Atlas M0: Free
   - Total minimum: ~$5/month

3. **Optimize Performance**
   - Enable caching
   - Optimize database queries
   - Use indexes in MongoDB

4. **Backup Strategy**
   - Automated MongoDB backups
   - Export bot configurations
   - Save trading history

---

**🎉 Your Hyperliquid Trading SaaS is now live on Railway!**

Access it at: `https://your-app.up.railway.app`
