#!/bin/bash

# Hyperliquid Trading SaaS - Railway Startup Script
set -e

echo "=========================================="
echo "Starting Hyperliquid Trading SaaS"
echo "=========================================="

# Create necessary directories
mkdir -p /app/logs /run/php
chmod -R 777 /app/logs

# Check MongoDB connection
echo "Checking MongoDB connection..."
python3 << 'EOF'
from pymongo import MongoClient
import os
import sys

mongo_uri = os.getenv('MONGO_URI', 'mongodb://localhost:27017')

try:
    client = MongoClient(mongo_uri, serverSelectionTimeoutMS=5000)
    client.admin.command('ping')
    print("✓ MongoDB connected")

    # Create indexes
    db = client['hyperliquid_saas']
    db.users.create_index([("email", 1)], unique=True)
    db.users.create_index([("username", 1)], unique=True)
    db.bots.create_index([("user_id", 1)])
    db.bots.create_index([("status", 1)])
    db.trades.create_index([("bot_id", 1)])
    db.trades.create_index([("created_at", -1)])
    db.payments.create_index([("user_id", 1)])
    db.payments.create_index([("tx_hash", 1)], unique=True)
    db.logs.create_index([("created_at", -1)])
    print("✓ Indexes created")

    # Create admin user
    if db.users.count_documents({"email": "admin@railway.app"}) == 0:
        db.users.insert_one({
            "email": "admin@railway.app",
            "username": "admin",
            "password": "$2y$12$LQv3c1yqBWVHxkd0LHAkCOYz6TtxMQJqhN8/LewY5ztJ6/gu/GQOS",
            "is_admin": True,
            "tier": "enterprise",
            "bots_count": 0,
            "total_pnl": 0,
            "active": True
        })
        print("✓ Admin user created (admin@railway.app / admin123)")
except Exception as e:
    print(f"❌ MongoDB error: {e}")
    sys.exit(1)
EOF

# Generate JWT_SECRET if not set
if [ -z "$JWT_SECRET" ]; then
    export JWT_SECRET=$(openssl rand -base64 64 | tr -d '\n')
    echo "⚠️  Generated JWT_SECRET (save this!): $JWT_SECRET"
fi

# Set default port
export PORT=${PORT:-8080}

echo "✓ PORT: $PORT"
echo ""

# Update nginx port
sed -i "s/listen 8080;/listen $PORT;/" /etc/nginx/sites-available/hyperliquid 2>/dev/null || true

# Start services with supervisor
echo "Starting services..."
exec /usr/bin/supervisord -c /etc/supervisor/conf.d/hyperliquid.conf
