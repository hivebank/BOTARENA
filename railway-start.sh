#!/bin/bash

# Railway.com Startup Script for Hyperliquid Trading SaaS
set -e

echo "=========================================="
echo "Starting Hyperliquid Trading SaaS on Railway"
echo "=========================================="
echo ""

# Create necessary directories
mkdir -p /app/logs /run/php

# Set permissions
chmod -R 777 /app/logs

# Check MongoDB connection
echo "Checking MongoDB connection..."
if [ -z "$MONGO_URI" ]; then
    echo "⚠️  WARNING: MONGO_URI not set!"
    echo "   Using default: mongodb://localhost:27017"
    export MONGO_URI="mongodb://localhost:27017"
fi

# Test MongoDB connection
python3 << 'EOF'
from pymongo import MongoClient
import os
import sys

mongo_uri = os.getenv('MONGO_URI', 'mongodb://localhost:27017')

try:
    client = MongoClient(mongo_uri, serverSelectionTimeoutMS=5000)
    client.admin.command('ping')
    print("✓ MongoDB connection successful")

    # Create indexes
    db = client['hyperliquid_saas']

    # Users
    db.users.create_index([("email", 1)], unique=True)
    db.users.create_index([("username", 1)], unique=True)

    # Bots
    db.bots.create_index([("user_id", 1)])
    db.bots.create_index([("status", 1)])

    # Trades
    db.trades.create_index([("bot_id", 1)])
    db.trades.create_index([("created_at", -1)])

    # Payments
    db.payments.create_index([("user_id", 1)])
    db.payments.create_index([("tx_hash", 1)], unique=True)

    # Logs
    db.logs.create_index([("created_at", -1)])

    print("✓ MongoDB indexes created")

    # Create default admin user if not exists
    if db.users.count_documents({"email": "admin@railway.app"}) == 0:
        import bcrypt
        password_hash = bcrypt.hashpw(b"admin123", bcrypt.gensalt(12)).decode('utf-8')

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
        print("✓ Default admin user created")
        print("   Email: admin@railway.app")
        print("   Password: admin123")
        print("   ⚠️  CHANGE THIS IMMEDIATELY!")
    else:
        print("✓ Admin user already exists")

except Exception as e:
    print(f"❌ MongoDB connection failed: {e}")
    print("   Please set MONGO_URI environment variable")
    print("   Example: mongodb+srv://user:pass@cluster.mongodb.net/dbname")
    sys.exit(1)
EOF

if [ $? -ne 0 ]; then
    echo "Database initialization failed!"
    exit 1
fi

# Check environment variables
echo ""
echo "Checking environment variables..."

if [ -z "$JWT_SECRET" ]; then
    echo "⚠️  WARNING: JWT_SECRET not set!"
    echo "   Generating random JWT_SECRET..."
    export JWT_SECRET=$(openssl rand -base64 64 | tr -d '\n')
    echo "   JWT_SECRET: $JWT_SECRET"
    echo "   ⚠️  Save this secret for future deployments!"
fi

if [ -z "$PORT" ]; then
    export PORT=8080
fi

echo "✓ PORT: $PORT"
echo "✓ MONGO_URI: ${MONGO_URI:0:30}..."
echo "✓ JWT_SECRET: ${JWT_SECRET:0:20}..."

# Update nginx port if Railway provides different port
if [ ! -z "$PORT" ] && [ "$PORT" != "8080" ]; then
    echo "Updating nginx port to $PORT..."
    sed -i "s/listen 8080;/listen $PORT;/" /etc/nginx/sites-available/hyperliquid
fi

# Start supervisor (manages all services)
echo ""
echo "=========================================="
echo "Starting services with Supervisor..."
echo "=========================================="
echo ""
echo "Services:"
echo "  - Nginx (web server)"
echo "  - PHP-FPM (PHP processor)"
echo "  - Bot Runner (trading engine)"
echo "  - Scheduler (maintenance tasks)"
echo ""

exec /usr/bin/supervisord -c /etc/supervisor/conf.d/hyperliquid.conf
