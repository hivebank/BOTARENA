"""
Bot Runner Engine
Main trading bot execution engine
Polls MongoDB for active bots and executes trades
"""

import asyncio
from pymongo import MongoClient
from bson import ObjectId
from datetime import datetime
import signal
import sys
from typing import Dict, Optional

from hyperliquid_client import HyperliquidClient
from ai_optimizer import AIOptimizer
from risk_engine import RiskEngine, RiskLimits
from config_loader import get_config
from util_logger import get_logger

logger = get_logger("BotRunner")


class TradingBot:
    """Individual trading bot instance"""

    def __init__(self, bot_data: Dict, client: HyperliquidClient):
        self.bot_id = str(bot_data['_id'])
        self.user_id = bot_data['user_id']
        self.name = bot_data['name']
        self.symbol = bot_data['symbol']
        self.strategy = bot_data['strategy']
        self.capital = bot_data['capital']
        self.leverage = bot_data.get('leverage', 1)
        self.mode = bot_data.get('mode', 'paper')
        self.settings = bot_data.get('settings', {})

        self.client = client
        self.ai_optimizer = AIOptimizer()

        # Risk management
        risk_limits = RiskLimits(
            max_position_size_usd=self.capital * self.leverage,
            max_leverage=self.leverage,
            max_daily_loss_percent=bot_data.get('max_daily_loss_percent', 5),
            max_drawdown_percent=bot_data.get('max_drawdown_percent', 20),
            stop_loss_percent=bot_data.get('stop_loss_percent', 2),
            take_profit_percent=bot_data.get('take_profit_percent', 5),
            max_open_positions=1
        )
        self.risk_engine = RiskEngine(risk_limits, self.capital)

        self.current_position = None
        self.total_pnl = bot_data.get('total_pnl', 0)
        self.trade_count = bot_data.get('trade_count', 0)

        logger.info(f"Bot initialized: {self.name} ({self.bot_id})")

    async def run_iteration(self, db):
        """Run one iteration of the bot"""

        try:
            # Check if trading is allowed
            should_stop, reason = self.risk_engine.should_stop_trading()
            if should_stop:
                logger.warning(f"Bot {self.name}: Trading stopped - {reason}")
                await self._update_status(db, 'stopped')
                return

            # Get market data
            candles = await self.client.get_candles(self.symbol, interval='1m', limit=100)

            if not candles:
                logger.warning(f"No candles received for {self.symbol}")
                return

            # Get trading signal based on strategy
            signal = await self._get_trading_signal(candles)

            logger.debug(f"Bot {self.name}: Signal - {signal.action} (confidence: {signal.confidence:.2f})")

            # Execute signal
            if signal.action != 'hold':
                await self._execute_signal(signal, candles[-1]['close'], db)

            # Check existing position for stop loss / take profit
            if self.current_position:
                await self._check_exit_conditions(candles[-1]['close'], db)

        except Exception as e:
            logger.error(f"Bot {self.name} iteration failed: {e}", bot_id=self.bot_id)

    async def _get_trading_signal(self, candles):
        """Get trading signal based on strategy"""

        if self.strategy == 'ai_optimizer':
            return self.ai_optimizer.analyze(candles)

        elif self.strategy == 'momentum':
            return self._momentum_strategy(candles)

        elif self.strategy == 'mean_reversion':
            return self._mean_reversion_strategy(candles)

        elif self.strategy == 'grid_trading':
            return self._grid_trading_strategy(candles)

        elif self.strategy == 'breakout':
            return self._breakout_strategy(candles)

        else:
            logger.warning(f"Unknown strategy: {self.strategy}")
            from ai_optimizer import Signal
            return Signal(action='hold', confidence=0.0)

    def _momentum_strategy(self, candles):
        """Momentum strategy"""
        from ai_optimizer import Signal

        if len(candles) < 14:
            return Signal(action='hold', confidence=0.0)

        # Simple momentum based on price change
        prices = [c['close'] for c in candles]
        momentum = (prices[-1] - prices[-14]) / prices[-14]

        if momentum > 0.02:  # 2% upward momentum
            return Signal(action='buy', confidence=0.8)
        elif momentum < -0.02:  # 2% downward momentum
            return Signal(action='sell', confidence=0.8)

        return Signal(action='hold', confidence=0.0)

    def _mean_reversion_strategy(self, candles):
        """Mean reversion strategy"""
        from ai_optimizer import Signal
        import numpy as np

        if len(candles) < 20:
            return Signal(action='hold', confidence=0.0)

        prices = np.array([c['close'] for c in candles])
        ma = np.mean(prices[-20:])
        std = np.std(prices[-20:])

        current_price = prices[-1]
        z_score = (current_price - ma) / std if std > 0 else 0

        if z_score < -2:  # Oversold
            return Signal(action='buy', confidence=0.7)
        elif z_score > 2:  # Overbought
            return Signal(action='sell', confidence=0.7)

        return Signal(action='hold', confidence=0.0)

    def _grid_trading_strategy(self, candles):
        """Grid trading strategy"""
        from ai_optimizer import Signal

        # Grid trading requires position tracking
        # Simplified version: buy low, sell high
        if len(candles) < 10:
            return Signal(action='hold', confidence=0.0)

        prices = [c['close'] for c in candles[-10:]]
        current = prices[-1]
        avg = sum(prices) / len(prices)

        if current < avg * 0.995:  # 0.5% below average
            return Signal(action='buy', confidence=0.6)
        elif current > avg * 1.005:  # 0.5% above average
            return Signal(action='sell', confidence=0.6)

        return Signal(action='hold', confidence=0.0)

    def _breakout_strategy(self, candles):
        """Breakout strategy"""
        from ai_optimizer import Signal

        if len(candles) < 20:
            return Signal(action='hold', confidence=0.0)

        # Find recent high/low
        recent_prices = [c['high'] for c in candles[-20:]]
        recent_lows = [c['low'] for c in candles[-20:]]

        resistance = max(recent_prices[:-1])
        support = min(recent_lows[:-1])

        current_price = candles[-1]['close']

        if current_price > resistance:  # Breakout above resistance
            return Signal(action='buy', confidence=0.8)
        elif current_price < support:  # Breakdown below support
            return Signal(action='sell', confidence=0.8)

        return Signal(action='hold', confidence=0.0)

    async def _execute_signal(self, signal, current_price, db):
        """Execute trading signal"""

        # Check if we already have a position
        if self.current_position and signal.action in ['buy', 'sell']:
            position_side = self.current_position['side']

            # If signal matches current position, hold
            if (position_side == 'long' and signal.action == 'buy') or \
               (position_side == 'short' and signal.action == 'sell'):
                return

            # Close current position first
            await self._close_position(current_price, db)

        # Don't open new position if signal is to close
        if self.current_position is None and signal.action in ['buy', 'sell']:
            await self._open_position(signal, current_price, db)

    async def _open_position(self, signal, price, db):
        """Open new position"""

        side = 'long' if signal.action == 'buy' else 'short'

        # Calculate position size
        stop_loss_price = self.risk_engine.calculate_stop_loss(price, signal.action)
        position_size = self.risk_engine.calculate_position_size(
            self.symbol, price, stop_loss_price, risk_percent=1.0
        )

        # Validate position
        validation = self.risk_engine.validate_position(
            self.symbol, position_size, price, self.leverage, 1 if self.current_position else 0
        )

        if not validation.is_acceptable:
            logger.warning(f"Position rejected: {validation.reason}")
            return

        # Execute order (paper or live)
        if self.mode == 'live':
            try:
                order = await self.client.place_market_order(self.symbol, signal.action, position_size)
                actual_price = order.price
            except Exception as e:
                logger.error(f"Failed to execute live order: {e}")
                return
        else:
            # Paper trading
            actual_price = price

        # Record position
        self.current_position = {
            'side': side,
            'entry_price': actual_price,
            'size': position_size,
            'stop_loss': stop_loss_price,
            'take_profit': self.risk_engine.calculate_take_profit(actual_price, signal.action),
            'entry_time': datetime.utcnow()
        }

        logger.info(f"Position opened: {side} {position_size:.4f} {self.symbol} @ {actual_price:.2f}")

        # Update database
        db.bots.update_one(
            {'_id': ObjectId(self.bot_id)},
            {'$set': {'current_position': self.current_position, 'updated_at': datetime.utcnow()}}
        )

    async def _close_position(self, price, db):
        """Close current position"""

        if not self.current_position:
            return

        side = self.current_position['side']
        entry_price = self.current_position['entry_price']
        size = self.current_position['size']

        # Calculate P&L
        if side == 'long':
            pnl = (price - entry_price) * size
        else:
            pnl = (entry_price - price) * size

        # Execute closing order
        if self.mode == 'live':
            try:
                close_side = 'sell' if side == 'long' else 'buy'
                await self.client.place_market_order(self.symbol, close_side, size, reduce_only=True)
            except Exception as e:
                logger.error(f"Failed to execute live close order: {e}")

        # Update stats
        self.total_pnl += pnl
        self.trade_count += 1
        self.risk_engine.update_balance(self.capital + self.total_pnl)
        self.risk_engine.update_daily_pnl(pnl)

        logger.info(f"Position closed: {side} {size:.4f} {self.symbol} @ {price:.2f}, P&L: ${pnl:.2f}")

        # Record trade
        db.trades.insert_one({
            'bot_id': self.bot_id,
            'user_id': self.user_id,
            'symbol': self.symbol,
            'side': side,
            'entry_price': entry_price,
            'exit_price': price,
            'size': size,
            'pnl': pnl,
            'created_at': datetime.utcnow()
        })

        # Update bot
        db.bots.update_one(
            {'_id': ObjectId(self.bot_id)},
            {
                '$set': {
                    'current_position': None,
                    'total_pnl': self.total_pnl,
                    'trade_count': self.trade_count,
                    'updated_at': datetime.utcnow()
                },
                '$inc': {
                    'win_count' if pnl > 0 else 'loss_count': 1
                }
            }
        )

        self.current_position = None

    async def _check_exit_conditions(self, current_price, db):
        """Check stop loss and take profit"""

        if not self.current_position:
            return

        side = self.current_position['side']
        stop_loss = self.current_position['stop_loss']
        take_profit = self.current_position['take_profit']

        should_close = False

        if side == 'long':
            if current_price <= stop_loss:
                logger.info(f"Stop loss hit: {current_price} <= {stop_loss}")
                should_close = True
            elif current_price >= take_profit:
                logger.info(f"Take profit hit: {current_price} >= {take_profit}")
                should_close = True

        else:  # short
            if current_price >= stop_loss:
                logger.info(f"Stop loss hit: {current_price} >= {stop_loss}")
                should_close = True
            elif current_price <= take_profit:
                logger.info(f"Take profit hit: {current_price} <= {take_profit}")
                should_close = True

        if should_close:
            await self._close_position(current_price, db)

    async def _update_status(self, db, status: str):
        """Update bot status"""
        db.bots.update_one(
            {'_id': ObjectId(self.bot_id)},
            {'$set': {'status': status, 'updated_at': datetime.utcnow()}}
        )


class BotRunnerService:
    """Main bot runner service"""

    def __init__(self):
        self.config = get_config()
        self.running = True
        self.bots = {}

        # MongoDB connection
        self.mongo_client = MongoClient(self.config['mongo_uri'])
        self.db = self.mongo_client[self.config['mongo_db']]

        # Hyperliquid client
        self.hl_client = HyperliquidClient(testnet=True)

        # Setup signal handlers
        signal.signal(signal.SIGINT, self._signal_handler)
        signal.signal(signal.SIGTERM, self._signal_handler)

        logger.info("Bot Runner Service initialized")

    def _signal_handler(self, signum, frame):
        """Handle shutdown signals"""
        logger.info(f"Received signal {signum}, shutting down...")
        self.running = False

    async def run(self):
        """Main execution loop"""

        logger.info("Bot Runner Service starting...")

        while self.running:
            try:
                # Get active bots from database
                active_bots = self.db.bots.find({
                    'status': 'running',
                    'active': True
                })

                # Run each bot
                tasks = []
                for bot_data in active_bots:
                    bot_id = str(bot_data['_id'])

                    # Create or get bot instance
                    if bot_id not in self.bots:
                        self.bots[bot_id] = TradingBot(bot_data, self.hl_client)

                    bot = self.bots[bot_id]

                    # Run bot iteration
                    tasks.append(bot.run_iteration(self.db))

                # Execute all bots concurrently
                if tasks:
                    await asyncio.gather(*tasks, return_exceptions=True)

                # Sleep before next iteration
                await asyncio.sleep(10)  # Run every 10 seconds

            except Exception as e:
                logger.error(f"Bot runner error: {e}")
                await asyncio.sleep(5)

        logger.info("Bot Runner Service stopped")

    async def close(self):
        """Cleanup"""
        await self.hl_client.close()
        self.mongo_client.close()


# Main entry point
async def main():
    runner = BotRunnerService()

    try:
        await runner.run()
    finally:
        await runner.close()


if __name__ == "__main__":
    asyncio.run(main())
