"""
Utility Logger for Hyperliquid Trading SaaS
Handles all logging operations with MongoDB integration
"""

import logging
import sys
from datetime import datetime
from typing import Dict, Any, Optional
from pymongo import MongoClient
from config_loader import get_config

class BotLogger:
    """Centralized logging system with MongoDB integration"""

    def __init__(self, name: str = "HyperTrade"):
        self.name = name
        self.config = get_config()

        # Setup console logger
        self.logger = logging.getLogger(name)
        self.logger.setLevel(logging.DEBUG)

        # Console handler
        console_handler = logging.StreamHandler(sys.stdout)
        console_handler.setLevel(logging.INFO)

        # Formatter
        formatter = logging.Formatter(
            '%(asctime)s - %(name)s - %(levelname)s - %(message)s',
            datefmt='%Y-%m-%d %H:%M:%S'
        )
        console_handler.setFormatter(formatter)

        self.logger.addHandler(console_handler)

        # MongoDB connection
        try:
            self.mongo_client = MongoClient(self.config['mongo_uri'])
            self.db = self.mongo_client[self.config['mongo_db']]
            self.logs_collection = self.db['logs']
        except Exception as e:
            self.logger.error(f"Failed to connect to MongoDB: {e}")
            self.logs_collection = None

    def _log_to_mongo(self, level: str, message: str, context: Optional[Dict[str, Any]] = None):
        """Log to MongoDB"""
        if self.logs_collection is None:
            return

        try:
            log_entry = {
                'type': 'python_log',
                'level': level,
                'message': message,
                'logger': self.name,
                'created_at': datetime.utcnow(),
                **(context or {})
            }
            self.logs_collection.insert_one(log_entry)
        except Exception as e:
            self.logger.error(f"Failed to log to MongoDB: {e}")

    def debug(self, message: str, **context):
        """Log debug message"""
        self.logger.debug(message)
        self._log_to_mongo('DEBUG', message, context)

    def info(self, message: str, **context):
        """Log info message"""
        self.logger.info(message)
        self._log_to_mongo('INFO', message, context)

    def warning(self, message: str, **context):
        """Log warning message"""
        self.logger.warning(message)
        self._log_to_mongo('WARNING', message, context)

    def error(self, message: str, **context):
        """Log error message"""
        self.logger.error(message)
        self._log_to_mongo('ERROR', message, context)

    def critical(self, message: str, **context):
        """Log critical message"""
        self.logger.critical(message)
        self._log_to_mongo('CRITICAL', message, context)

    def trade(self, bot_id: str, symbol: str, side: str, price: float, size: float, pnl: float = 0):
        """Log trade execution"""
        message = f"Trade executed: {side} {size} {symbol} @ {price}"
        self.info(message, bot_id=bot_id, symbol=symbol, side=side, price=price, size=size, pnl=pnl)

        # Insert into trades collection
        if self.logs_collection:
            try:
                self.db['trades'].insert_one({
                    'bot_id': bot_id,
                    'symbol': symbol,
                    'side': side,
                    'price': price,
                    'size': size,
                    'pnl': pnl,
                    'created_at': datetime.utcnow()
                })
            except Exception as e:
                self.logger.error(f"Failed to log trade to MongoDB: {e}")

    def bot_event(self, bot_id: str, event: str, details: Optional[Dict] = None):
        """Log bot-specific events"""
        message = f"Bot {bot_id}: {event}"
        self.info(message, bot_id=bot_id, event=event, details=details or {})

    def close(self):
        """Close logger connections"""
        if self.mongo_client:
            self.mongo_client.close()


# Global logger instance
logger = BotLogger()


def get_logger(name: str = "HyperTrade") -> BotLogger:
    """Get or create a logger instance"""
    return BotLogger(name)
