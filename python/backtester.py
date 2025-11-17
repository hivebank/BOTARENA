"""
Backtesting System
Test strategies on historical data with metrics
"""

import pandas as pd
import numpy as np
from typing import List, Dict, Callable
from dataclasses import dataclass
from datetime import datetime
from util_logger import get_logger

logger = get_logger("Backtester")


@dataclass
class BacktestResult:
    """Backtesting results"""
    total_trades: int
    winning_trades: int
    losing_trades: int
    win_rate: float
    total_pnl: float
    total_return_percent: float
    sharpe_ratio: float
    max_drawdown: float
    max_drawdown_percent: float
    avg_win: float
    avg_loss: float
    profit_factor: float
    trades: List[Dict]


class Backtester:
    """Backtesting engine for trading strategies"""

    def __init__(self, initial_capital: float = 10000):
        self.initial_capital = initial_capital
        self.capital = initial_capital
        self.position = None
        self.trades = []
        self.equity_curve = []

    def run(self, candles: List[Dict], strategy: Callable) -> BacktestResult:
        """Run backtest on historical candles"""

        logger.info(f"Starting backtest with {len(candles)} candles, capital: ${self.initial_capital}")

        df = pd.DataFrame(candles)
        df['timestamp'] = pd.to_datetime(df.get('timestamp', range(len(df))), unit='ms', errors='coerce')

        # Reset state
        self.capital = self.initial_capital
        self.position = None
        self.trades = []
        self.equity_curve = [self.initial_capital]

        # Run simulation
        for i in range(len(df)):
            current_candle = df.iloc[i]
            historical_data = df.iloc[:i+1]

            # Get strategy signal
            signal = strategy(historical_data)

            # Execute signal
            self._execute_signal(signal, current_candle)

            # Track equity
            equity = self._calculate_equity(current_candle['close'])
            self.equity_curve.append(equity)

        # Close any open position
        if self.position:
            self._close_position(df.iloc[-1]['close'], df.iloc[-1]['timestamp'])

        # Calculate metrics
        return self._calculate_metrics()

    def _execute_signal(self, signal: Dict, candle: pd.Series):
        """Execute trading signal"""

        action = signal.get('action', 'hold')
        price = candle['close']
        timestamp = candle['timestamp']

        if action == 'buy' and not self.position:
            # Open long position
            size = (self.capital * 0.95) / price  # Use 95% of capital
            self.position = {
                'side': 'long',
                'entry_price': price,
                'size': size,
                'entry_time': timestamp
            }
            logger.debug(f"Opened long: {size:.4f} @ {price:.2f}")

        elif action == 'sell' and self.position and self.position['side'] == 'long':
            # Close long position
            self._close_position(price, timestamp)

        elif action == 'sell' and not self.position:
            # Open short position
            size = (self.capital * 0.95) / price
            self.position = {
                'side': 'short',
                'entry_price': price,
                'size': size,
                'entry_time': timestamp
            }
            logger.debug(f"Opened short: {size:.4f} @ {price:.2f}")

        elif action == 'buy' and self.position and self.position['side'] == 'short':
            # Close short position
            self._close_position(price, timestamp)

    def _close_position(self, exit_price: float, exit_time):
        """Close current position"""

        if not self.position:
            return

        entry_price = self.position['entry_price']
        size = self.position['size']
        side = self.position['side']

        # Calculate P&L
        if side == 'long':
            pnl = (exit_price - entry_price) * size
        else:  # short
            pnl = (entry_price - exit_price) * size

        pnl_percent = (pnl / self.capital) * 100

        # Update capital
        self.capital += pnl

        # Record trade
        trade = {
            'entry_time': self.position['entry_time'],
            'exit_time': exit_time,
            'side': side,
            'entry_price': entry_price,
            'exit_price': exit_price,
            'size': size,
            'pnl': pnl,
            'pnl_percent': pnl_percent,
            'capital_after': self.capital
        }

        self.trades.append(trade)

        logger.debug(f"Closed {side}: {size:.4f} @ {exit_price:.2f}, P&L: ${pnl:.2f} ({pnl_percent:.2f}%)")

        self.position = None

    def _calculate_equity(self, current_price: float) -> float:
        """Calculate current equity"""

        if not self.position:
            return self.capital

        unrealized_pnl = 0

        if self.position['side'] == 'long':
            unrealized_pnl = (current_price - self.position['entry_price']) * self.position['size']
        else:  # short
            unrealized_pnl = (self.position['entry_price'] - current_price) * self.position['size']

        return self.capital + unrealized_pnl

    def _calculate_metrics(self) -> BacktestResult:
        """Calculate performance metrics"""

        if not self.trades:
            logger.warning("No trades executed")
            return BacktestResult(
                total_trades=0,
                winning_trades=0,
                losing_trades=0,
                win_rate=0,
                total_pnl=0,
                total_return_percent=0,
                sharpe_ratio=0,
                max_drawdown=0,
                max_drawdown_percent=0,
                avg_win=0,
                avg_loss=0,
                profit_factor=0,
                trades=[]
            )

        trades_df = pd.DataFrame(self.trades)

        # Basic metrics
        total_trades = len(self.trades)
        winning_trades = len(trades_df[trades_df['pnl'] > 0])
        losing_trades = len(trades_df[trades_df['pnl'] < 0])
        win_rate = (winning_trades / total_trades * 100) if total_trades > 0 else 0

        # P&L
        total_pnl = trades_df['pnl'].sum()
        total_return_percent = ((self.capital - self.initial_capital) / self.initial_capital) * 100

        # Win/Loss averages
        wins = trades_df[trades_df['pnl'] > 0]['pnl']
        losses = trades_df[trades_df['pnl'] < 0]['pnl']
        avg_win = wins.mean() if len(wins) > 0 else 0
        avg_loss = abs(losses.mean()) if len(losses) > 0 else 0

        # Profit factor
        total_wins = wins.sum() if len(wins) > 0 else 0
        total_losses = abs(losses.sum()) if len(losses) > 0 else 1
        profit_factor = total_wins / total_losses if total_losses > 0 else 0

        # Sharpe ratio
        returns = trades_df['pnl_percent']
        sharpe_ratio = (returns.mean() / returns.std()) * np.sqrt(252) if returns.std() > 0 else 0

        # Max drawdown
        equity_array = np.array(self.equity_curve)
        running_max = np.maximum.accumulate(equity_array)
        drawdown = running_max - equity_array
        max_drawdown = drawdown.max()
        max_drawdown_percent = (max_drawdown / running_max[drawdown.argmax()] * 100) if running_max[drawdown.argmax()] > 0 else 0

        logger.info(f"Backtest complete: {total_trades} trades, Win rate: {win_rate:.2f}%, "
                   f"Total P&L: ${total_pnl:.2f} ({total_return_percent:.2f}%)")

        return BacktestResult(
            total_trades=total_trades,
            winning_trades=winning_trades,
            losing_trades=losing_trades,
            win_rate=win_rate,
            total_pnl=total_pnl,
            total_return_percent=total_return_percent,
            sharpe_ratio=sharpe_ratio,
            max_drawdown=max_drawdown,
            max_drawdown_percent=max_drawdown_percent,
            avg_win=avg_win,
            avg_loss=avg_loss,
            profit_factor=profit_factor,
            trades=self.trades
        )

    def export_results(self) -> Dict:
        """Export results as JSON"""

        result = self._calculate_metrics()

        return {
            'summary': {
                'total_trades': result.total_trades,
                'winning_trades': result.winning_trades,
                'losing_trades': result.losing_trades,
                'win_rate': result.win_rate,
                'total_pnl': result.total_pnl,
                'total_return_percent': result.total_return_percent,
                'sharpe_ratio': result.sharpe_ratio,
                'max_drawdown': result.max_drawdown,
                'max_drawdown_percent': result.max_drawdown_percent,
                'avg_win': result.avg_win,
                'avg_loss': result.avg_loss,
                'profit_factor': result.profit_factor
            },
            'equity_curve': self.equity_curve,
            'trades': result.trades
        }


# Example strategy
def simple_sma_crossover(df: pd.DataFrame) -> Dict:
    """Simple SMA crossover strategy"""

    if len(df) < 50:
        return {'action': 'hold'}

    # Calculate SMAs
    sma_fast = df['close'].rolling(window=10).mean().iloc[-1]
    sma_slow = df['close'].rolling(window=50).mean().iloc[-1]

    sma_fast_prev = df['close'].rolling(window=10).mean().iloc[-2]
    sma_slow_prev = df['close'].rolling(window=50).mean().iloc[-2]

    # Crossover detection
    if sma_fast > sma_slow and sma_fast_prev <= sma_slow_prev:
        return {'action': 'buy'}

    elif sma_fast < sma_slow and sma_fast_prev >= sma_slow_prev:
        return {'action': 'sell'}

    return {'action': 'hold'}


# Example usage
if __name__ == "__main__":
    # Generate sample data
    candles = []
    base_price = 40000

    for i in range(1000):
        price = base_price + np.sin(i * 0.1) * 2000 + np.random.randn() * 200

        candles.append({
            'timestamp': i,
            'open': price,
            'high': price * 1.01,
            'low': price * 0.99,
            'close': price,
            'volume': 1000000
        })

    # Run backtest
    backtester = Backtester(initial_capital=10000)
    results = backtester.run(candles, simple_sma_crossover)

    print(f"Results: {results}")
