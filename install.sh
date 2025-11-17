#!/bin/bash

# Hyperliquid Trading SaaS - Installation Script
# Installs dependencies and sets up the environment

set -e

echo "=========================================="
echo "Hyperliquid Trading SaaS - Installation"
echo "=========================================="
echo ""

# Check if Python 3 is installed
if ! command -v python3 &> /dev/null; then
    echo "❌ Python 3 is not installed. Please install Python 3.8+ first."
    exit 1
fi

echo "✓ Python found: $(python3 --version)"

# Check if pip is installed
if ! command -v pip3 &> /dev/null; then
    echo "❌ pip3 is not installed. Please install pip3 first."
    exit 1
fi

echo "✓ pip3 found: $(pip3 --version)"

# Create virtual environment
echo ""
echo "Creating Python virtual environment..."
if [ ! -d "venv" ]; then
    python3 -m venv venv
    echo "✓ Virtual environment created"
else
    echo "✓ Virtual environment already exists"
fi

# Activate virtual environment
echo ""
echo "Activating virtual environment..."
source venv/bin/activate
echo "✓ Virtual environment activated"

# Upgrade pip
echo ""
echo "Upgrading pip..."
pip install --upgrade pip
echo "✓ pip upgraded"

# Install Python dependencies
echo ""
echo "Installing Python dependencies..."
pip install -r requirements.txt
echo "✓ Python dependencies installed"

# Create necessary directories
echo ""
echo "Creating directories..."
mkdir -p logs
mkdir -p public/assets/css
mkdir -p public/assets/js
mkdir -p public/assets/img
mkdir -p public/admin
mkdir -p api
mkdir -p config
mkdir -p python
mkdir -p docs

echo "✓ Directories created"

# Set permissions
echo ""
echo "Setting permissions..."
chmod -R 755 public
chmod -R 755 api
chmod -R 755 python
chmod 644 config/config.php
chmod 644 config/settings.json
echo "✓ Permissions set"

# MongoDB setup check
echo ""
echo "Checking MongoDB..."
if command -v mongod &> /dev/null; then
    echo "✓ MongoDB found: $(mongod --version | head -n 1)"
else
    echo "⚠️  MongoDB not found. Please install MongoDB and start the service."
    echo "   Ubuntu/Debian: sudo apt-get install mongodb"
    echo "   Or use MongoDB Atlas (cloud): https://www.mongodb.com/atlas"
fi

# Create MongoDB indexes
echo ""
echo "Creating MongoDB indexes..."
cat > /tmp/create_indexes.js << 'EOF'
use hyperliquid_saas;

// Users collection
db.users.createIndex({ "email": 1 }, { unique: true });
db.users.createIndex({ "username": 1 }, { unique: true });
db.users.createIndex({ "api_key": 1 });

// Bots collection
db.bots.createIndex({ "user_id": 1 });
db.bots.createIndex({ "status": 1 });
db.bots.createIndex({ "active": 1 });

// Trades collection
db.trades.createIndex({ "user_id": 1 });
db.trades.createIndex({ "bot_id": 1 });
db.trades.createIndex({ "created_at": -1 });

// Payments collection
db.payments.createIndex({ "user_id": 1 });
db.payments.createIndex({ "tx_hash": 1 }, { unique: true });
db.payments.createIndex({ "status": 1 });

// Logs collection
db.logs.createIndex({ "created_at": -1 });
db.logs.createIndex({ "type": 1 });
db.logs.createIndex({ "user_id": 1 });

print("Indexes created successfully!");
EOF

if command -v mongosh &> /dev/null; then
    mongosh < /tmp/create_indexes.js 2>/dev/null || echo "⚠️  Could not create indexes (MongoDB may not be running)"
elif command -v mongo &> /dev/null; then
    mongo < /tmp/create_indexes.js 2>/dev/null || echo "⚠️  Could not create indexes (MongoDB may not be running)"
else
    echo "⚠️  MongoDB CLI not found. Skipping index creation."
fi

rm -f /tmp/create_indexes.js

# Create default admin user
echo ""
echo "Creating default admin user..."
cat > /tmp/create_admin.js << 'EOF'
use hyperliquid_saas;

// Check if admin exists
var adminExists = db.users.findOne({ "email": "admin@localhost" });

if (!adminExists) {
    db.users.insertOne({
        "email": "admin@localhost",
        "username": "admin",
        "password": "$2y$12$LQv3c1yqBWVHxkd0LHAkCOYz6TtxMQJqhN8/LewY5ztJ6/gu/GQOS", // admin123
        "is_admin": true,
        "tier": "enterprise",
        "bots_count": 0,
        "total_pnl": 0,
        "active": true,
        "created_at": new Date(),
        "updated_at": new Date()
    });
    print("Admin user created! Email: admin@localhost, Password: admin123");
} else {
    print("Admin user already exists");
}
EOF

if command -v mongosh &> /dev/null; then
    mongosh < /tmp/create_admin.js 2>/dev/null || true
elif command -v mongo &> /dev/null; then
    mongo < /tmp/create_admin.js 2>/dev/null || true
fi

rm -f /tmp/create_admin.js

# Environment setup
echo ""
echo "Setting up environment..."

if [ ! -f ".env" ]; then
    cat > .env << 'EOF'
# MongoDB Configuration
MONGO_URI=mongodb://localhost:27017
MONGO_DB=hyperliquid_saas

# JWT Secret (CHANGE THIS IN PRODUCTION!)
JWT_SECRET=

# Hyperliquid API
HYPERLIQUID_API_URL=https://api.hyperliquid.xyz
HYPERLIQUID_WS_URL=wss://api.hyperliquid.xyz/ws

# Crypto Wallets (CHANGE THESE!)
SOL_WALLET=YOUR_SOL_WALLET_ADDRESS
ETH_WALLET=YOUR_ETH_WALLET_ADDRESS

# RPC Endpoints
SOL_RPC=https://api.mainnet-beta.solana.com
ETH_RPC=https://eth.llamarpc.com

# Admin Credentials (CHANGE THESE!)
ADMIN_EMAIL=admin@localhost
ADMIN_PASSWORD=admin123
EOF

    echo "✓ .env file created"
    echo ""
    echo "⚠️  IMPORTANT: Edit .env file and update the configuration!"
    echo "   - Generate a secure JWT_SECRET"
    echo "   - Set your crypto wallet addresses"
    echo "   - Update admin credentials"
else
    echo "✓ .env file already exists"
fi

# Installation complete
echo ""
echo "=========================================="
echo "✓ Installation Complete!"
echo "=========================================="
echo ""
echo "Next steps:"
echo "1. Edit .env file with your configuration"
echo "2. Start MongoDB: sudo systemctl start mongod"
echo "3. Configure Apache (see docs/DEPLOY_APACHE.md)"
echo "4. Run: ./startup.sh to start Python workers"
echo ""
echo "Default admin credentials:"
echo "  Email: admin@localhost"
echo "  Password: admin123"
echo "  ⚠️  CHANGE THESE IMMEDIATELY!"
echo ""
echo "For more information, see docs/SETUP.md"
echo ""
