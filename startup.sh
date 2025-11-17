#!/bin/bash

# Hyperliquid Trading SaaS - Startup Script
# Starts Python worker processes

set -e

echo "=========================================="
echo "Hyperliquid Trading SaaS - Starting Workers"
echo "=========================================="
echo ""

# Check if virtual environment exists
if [ ! -d "venv" ]; then
    echo "❌ Virtual environment not found. Run ./install.sh first."
    exit 1
fi

# Activate virtual environment
source venv/bin/activate
echo "✓ Virtual environment activated"

# Check MongoDB connection
echo ""
echo "Checking MongoDB connection..."
python3 << EOF
from pymongo import MongoClient
import sys

try:
    client = MongoClient('mongodb://localhost:27017', serverSelectionTimeoutMS=2000)
    client.admin.command('ping')
    print("✓ MongoDB is running")
except Exception as e:
    print(f"❌ MongoDB connection failed: {e}")
    print("   Please start MongoDB: sudo systemctl start mongod")
    sys.exit(1)
EOF

if [ $? -ne 0 ]; then
    exit 1
fi

# Create PID directory
mkdir -p /tmp/hyperliquid_saas

# Kill existing workers
echo ""
echo "Stopping existing workers..."
pkill -f "python.*bot_runner.py" 2>/dev/null || true
pkill -f "python.*scheduler.py" 2>/dev/null || true
sleep 2
echo "✓ Existing workers stopped"

# Start bot runner
echo ""
echo "Starting Bot Runner..."
nohup python3 python/bot_runner.py > logs/bot_runner.log 2>&1 &
BOT_RUNNER_PID=$!
echo $BOT_RUNNER_PID > /tmp/hyperliquid_saas/bot_runner.pid
echo "✓ Bot Runner started (PID: $BOT_RUNNER_PID)"

# Start scheduler
echo ""
echo "Starting Scheduler..."
nohup python3 python/scheduler.py > logs/scheduler.log 2>&1 &
SCHEDULER_PID=$!
echo $SCHEDULER_PID > /tmp/hyperliquid_saas/scheduler.pid
echo "✓ Scheduler started (PID: $SCHEDULER_PID)"

# Wait a moment and check if processes are running
sleep 2

if ps -p $BOT_RUNNER_PID > /dev/null; then
    echo "✓ Bot Runner is running"
else
    echo "❌ Bot Runner failed to start. Check logs/bot_runner.log"
fi

if ps -p $SCHEDULER_PID > /dev/null; then
    echo "✓ Scheduler is running"
else
    echo "❌ Scheduler failed to start. Check logs/scheduler.log"
fi

# Service status
echo ""
echo "=========================================="
echo "✓ Workers Started Successfully!"
echo "=========================================="
echo ""
echo "Active workers:"
echo "  - Bot Runner (PID: $BOT_RUNNER_PID)"
echo "  - Scheduler (PID: $SCHEDULER_PID)"
echo ""
echo "Logs:"
echo "  - tail -f logs/bot_runner.log"
echo "  - tail -f logs/scheduler.log"
echo "  - tail -f logs/app_$(date +%Y-%m-%d).log"
echo ""
echo "To stop workers:"
echo "  kill $BOT_RUNNER_PID $SCHEDULER_PID"
echo "  or: pkill -f 'python.*bot_runner.py'"
echo ""
echo "System is ready! Access at http://localhost/"
echo ""
