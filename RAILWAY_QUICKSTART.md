# 🚂 Railway.com Quick Start

## Deploy in 5 Minutes! ⚡

### Step 1: Prepare MongoDB

**Option A: Railway MongoDB Plugin** (Easiest)
```
1. Go to Railway project
2. Click "+ New"
3. Select "Database" → "MongoDB"
4. Copy MONGO_URL from variables
```

**Option B: MongoDB Atlas** (Free tier available)
```
1. Go to https://www.mongodb.com/cloud/atlas
2. Create free M0 cluster
3. Create database user
4. Network Access: Allow 0.0.0.0/0
5. Get connection string
```

### Step 2: Deploy to Railway

1. **Push to GitHub**
   ```bash
   git push origin claude/hyperliquid-trading-saas-01CQTBaEvQw7dFqHbq2ggnL5
   ```

2. **Create Railway Project**
   - Go to https://railway.app/new
   - Click "Deploy from GitHub repo"
   - Select your repository
   - Select branch

3. **Set Environment Variables**
   ```
   MONGO_URI=mongodb+srv://user:pass@cluster.mongodb.net/hyperliquid_saas
   JWT_SECRET=<run: openssl rand -base64 64>
   SOL_WALLET=<your-solana-wallet>
   ETH_WALLET=<your-ethereum-wallet>
   ```

4. **Deploy!**
   - Railway builds and deploys automatically
   - Wait 2-3 minutes for build
   - Access at: `https://your-app.up.railway.app`

### Step 3: First Login

```
URL: https://your-app.up.railway.app
Email: admin@railway.app
Password: admin123
```

**⚠️ CHANGE PASSWORD IMMEDIATELY!**

---

## Required Environment Variables

| Variable | Description | Example |
|----------|-------------|---------|
| `MONGO_URI` | MongoDB connection string | `mongodb+srv://...` |
| `JWT_SECRET` | Random secure string | Generate: `openssl rand -base64 64` |
| `SOL_WALLET` | Solana wallet address | Your SOL address |
| `ETH_WALLET` | Ethereum wallet address | Your ETH address |

## Optional Variables

| Variable | Default | Description |
|----------|---------|-------------|
| `SOL_RPC` | Solana mainnet | Custom Solana RPC |
| `ETH_RPC` | Public endpoint | Custom Ethereum RPC |
| `ADMIN_EMAIL` | admin@railway.app | Admin email |
| `ADMIN_PASSWORD` | admin123 | Admin password |

---

## Generate JWT Secret

```bash
openssl rand -base64 64
```

Copy output and paste into Railway's `JWT_SECRET` variable.

---

## Troubleshooting

### Build Failed
- Check Dockerfile syntax
- View logs in Railway dashboard
- Ensure all files committed

### MongoDB Connection Failed
- Verify MONGO_URI is correct
- Check MongoDB Atlas IP whitelist (allow 0.0.0.0/0)
- Test connection string locally

### 502 Bad Gateway
- Wait 2-3 minutes for services to start
- Check health endpoint: `/health.php`
- View logs for errors

### Can't Login
- Ensure JWT_SECRET is set
- Check MongoDB has admin user
- Clear browser cache

---

## Next Steps

1. ✅ Login and change password
2. ✅ Create test bot (paper mode)
3. ✅ Monitor logs in Railway
4. ✅ Configure custom domain (optional)
5. ✅ Set up backups

---

## Full Documentation

📖 [Complete Railway Deployment Guide](docs/RAILWAY_DEPLOYMENT.md)

---

## Cost Estimate

- **Railway Hobby**: $5/month (512MB RAM)
- **MongoDB Atlas M0**: FREE (512MB storage)
- **Total**: ~$5/month

---

## Health Check

Your app exposes a health endpoint:

```bash
curl https://your-app.up.railway.app/health.php
```

Expected response:
```json
{
  "status": "healthy",
  "checks": {
    "mongodb": "ok",
    "php_version": "8.1.x",
    "mongodb_extension": "ok"
  }
}
```

---

**🎉 That's it! Your trading platform is live!**

Access: `https://your-app.up.railway.app`
