"""
Scheduler for Bot Operations
Handles periodic tasks like daily resets, cleanup, backups
"""

import asyncio
from pymongo import MongoClient
from datetime import datetime, timedelta
import signal
import sys

from config_loader import get_config
from util_logger import get_logger

logger = get_logger("Scheduler")


class SchedulerService:
    """Scheduler for periodic tasks"""

    def __init__(self):
        self.config = get_config()
        self.running = True

        # MongoDB connection
        self.mongo_client = MongoClient(self.config['mongo_uri'])
        self.db = self.mongo_client[self.config['mongo_db']]

        # Setup signal handlers
        signal.signal(signal.SIGINT, self._signal_handler)
        signal.signal(signal.SIGTERM, self._signal_handler)

        self.last_daily_reset = datetime.utcnow().date()
        self.last_cleanup = datetime.utcnow()
        self.last_backup = datetime.utcnow()

        logger.info("Scheduler Service initialized")

    def _signal_handler(self, signum, frame):
        """Handle shutdown signals"""
        logger.info(f"Received signal {signum}, shutting down...")
        self.running = False

    async def run(self):
        """Main scheduler loop"""

        logger.info("Scheduler Service starting...")

        while self.running:
            try:
                current_time = datetime.utcnow()

                # Daily reset (at midnight UTC)
                if current_time.date() > self.last_daily_reset:
                    await self._daily_reset()
                    self.last_daily_reset = current_time.date()

                # Cleanup old logs (every hour)
                if (current_time - self.last_cleanup).seconds > 3600:
                    await self._cleanup_old_logs()
                    self.last_cleanup = current_time

                # Backup (every 24 hours)
                if (current_time - self.last_backup).seconds > 86400:
                    await self._backup_data()
                    self.last_backup = current_time

                # Health check
                await self._health_check()

                # Sleep for 60 seconds
                await asyncio.sleep(60)

            except Exception as e:
                logger.error(f"Scheduler error: {e}")
                await asyncio.sleep(10)

        logger.info("Scheduler Service stopped")

    async def _daily_reset(self):
        """Daily reset tasks"""
        logger.info("Running daily reset...")

        try:
            # Reset daily P&L for all users
            self.db.users.update_many(
                {},
                {'$set': {'daily_pnl': 0, 'daily_trades': 0}}
            )

            # Log event
            self.db.logs.insert_one({
                'type': 'daily_reset',
                'message': 'Daily reset completed',
                'created_at': datetime.utcnow()
            })

            logger.info("Daily reset completed")

        except Exception as e:
            logger.error(f"Daily reset failed: {e}")

    async def _cleanup_old_logs(self):
        """Clean up old log entries"""
        logger.info("Cleaning up old logs...")

        try:
            # Get cleanup days from settings
            settings = self.config['settings']
            cleanup_days = settings['system']['cleanup_old_logs_days']

            # Delete logs older than X days
            cutoff_date = datetime.utcnow() - timedelta(days=cleanup_days)

            result = self.db.logs.delete_many({
                'created_at': {'$lt': cutoff_date}
            })

            logger.info(f"Cleaned up {result.deleted_count} old log entries")

        except Exception as e:
            logger.error(f"Log cleanup failed: {e}")

    async def _backup_data(self):
        """Backup critical data"""
        logger.info("Running backup...")

        try:
            # Count documents
            user_count = self.db.users.count_documents({})
            bot_count = self.db.bots.count_documents({})
            trade_count = self.db.trades.count_documents({})

            # Log backup
            self.db.logs.insert_one({
                'type': 'backup',
                'message': 'Backup completed',
                'stats': {
                    'users': user_count,
                    'bots': bot_count,
                    'trades': trade_count
                },
                'created_at': datetime.utcnow()
            })

            logger.info(f"Backup completed: {user_count} users, {bot_count} bots, {trade_count} trades")

        except Exception as e:
            logger.error(f"Backup failed: {e}")

    async def _health_check(self):
        """System health check"""

        try:
            # Check MongoDB connection
            self.mongo_client.admin.command('ping')

            # Count running bots
            running_bots = self.db.bots.count_documents({'status': 'running'})

            # Check for stuck bots (not updated in 10 minutes)
            stuck_cutoff = datetime.utcnow() - timedelta(minutes=10)
            stuck_bots = self.db.bots.count_documents({
                'status': 'running',
                'updated_at': {'$lt': stuck_cutoff}
            })

            if stuck_bots > 0:
                logger.warning(f"Found {stuck_bots} stuck bots")

                # Auto-stop stuck bots
                self.db.bots.update_many(
                    {
                        'status': 'running',
                        'updated_at': {'$lt': stuck_cutoff}
                    },
                    {'$set': {'status': 'stopped'}}
                )

                logger.info(f"Stopped {stuck_bots} stuck bots")

        except Exception as e:
            logger.error(f"Health check failed: {e}")

    def close(self):
        """Cleanup"""
        self.mongo_client.close()


# Main entry point
async def main():
    scheduler = SchedulerService()

    try:
        await scheduler.run()
    finally:
        scheduler.close()


if __name__ == "__main__":
    asyncio.run(main())
