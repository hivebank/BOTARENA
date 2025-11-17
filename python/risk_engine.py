"""
Risk Management Engine
Handles position sizing, stop losses, and account protection
"""

from typing import Dict, Optional, List
from dataclasses import dataclass
from util_logger import get_logger

logger = get_logger("RiskEngine")


@dataclass
class RiskLimits:
    """Risk limits for trading"""
    max_position_size_usd: float
    max_leverage: int
    max_daily_loss_percent: float
    max_drawdown_percent: float
    stop_loss_percent: float
    take_profit_percent: float
    max_open_positions: int


@dataclass
class PositionRisk:
    """Position risk assessment"""
    symbol: str
    position_size: float
    leverage: int
    risk_amount: float
    risk_percent: float
    is_acceptable: bool
    reason: Optional[str] = None


class RiskEngine:
    """Risk management and position sizing"""

    def __init__(self, limits: RiskLimits, account_balance: float):
        self.limits = limits
        self.account_balance = account_balance
        self.daily_pnl = 0.0
        self.peak_balance = account_balance
        self.daily_trades = 0
        self.daily_losses = 0

    def update_balance(self, new_balance: float):
        """Update account balance"""
        self.account_balance = new_balance
        if new_balance > self.peak_balance:
            self.peak_balance = new_balance

    def update_daily_pnl(self, pnl: float):
        """Update daily P&L"""
        self.daily_pnl += pnl

        if pnl < 0:
            self.daily_losses += 1

    def reset_daily_stats(self):
        """Reset daily statistics"""
        self.daily_pnl = 0.0
        self.daily_trades = 0
        self.daily_losses = 0
        logger.info("Daily stats reset")

    def check_daily_loss_limit(self) -> bool:
        """Check if daily loss limit exceeded"""
        daily_loss_limit = self.account_balance * (self.limits.max_daily_loss_percent / 100)

        if self.daily_pnl < -daily_loss_limit:
            logger.warning(f"Daily loss limit exceeded: {self.daily_pnl:.2f} < {-daily_loss_limit:.2f}")
            return False

        return True

    def check_drawdown_limit(self) -> bool:
        """Check if max drawdown exceeded"""
        current_drawdown = (self.peak_balance - self.account_balance) / self.peak_balance * 100

        if current_drawdown > self.limits.max_drawdown_percent:
            logger.warning(f"Max drawdown exceeded: {current_drawdown:.2f}% > {self.limits.max_drawdown_percent}%")
            return False

        return True

    def calculate_position_size(self, symbol: str, entry_price: float,
                                stop_loss_price: float, risk_percent: float = 1.0) -> float:
        """Calculate position size based on risk"""

        # Risk amount
        risk_amount = self.account_balance * (risk_percent / 100)

        # Price risk per unit
        price_risk = abs(entry_price - stop_loss_price)

        if price_risk == 0:
            logger.error("Stop loss price equals entry price")
            return 0.0

        # Position size
        position_size = risk_amount / price_risk

        # Apply max position size limit
        max_position_value = self.limits.max_position_size_usd
        max_position_size = max_position_value / entry_price

        position_size = min(position_size, max_position_size)

        logger.info(f"Calculated position size for {symbol}: {position_size:.4f} (risk: ${risk_amount:.2f})")

        return position_size

    def validate_position(self, symbol: str, size: float, price: float,
                         leverage: int, open_positions: int) -> PositionRisk:
        """Validate if position meets risk requirements"""

        position_value = size * price * leverage
        risk_percent = (position_value / self.account_balance) * 100

        # Check max position size
        if position_value > self.limits.max_position_size_usd:
            return PositionRisk(
                symbol=symbol,
                position_size=size,
                leverage=leverage,
                risk_amount=position_value,
                risk_percent=risk_percent,
                is_acceptable=False,
                reason=f"Position size ${position_value:.2f} exceeds limit ${self.limits.max_position_size_usd}"
            )

        # Check max leverage
        if leverage > self.limits.max_leverage:
            return PositionRisk(
                symbol=symbol,
                position_size=size,
                leverage=leverage,
                risk_amount=position_value,
                risk_percent=risk_percent,
                is_acceptable=False,
                reason=f"Leverage {leverage}x exceeds limit {self.limits.max_leverage}x"
            )

        # Check max open positions
        if open_positions >= self.limits.max_open_positions:
            return PositionRisk(
                symbol=symbol,
                position_size=size,
                leverage=leverage,
                risk_amount=position_value,
                risk_percent=risk_percent,
                is_acceptable=False,
                reason=f"Max open positions ({self.limits.max_open_positions}) reached"
            )

        # Check daily loss limit
        if not self.check_daily_loss_limit():
            return PositionRisk(
                symbol=symbol,
                position_size=size,
                leverage=leverage,
                risk_amount=position_value,
                risk_percent=risk_percent,
                is_acceptable=False,
                reason="Daily loss limit exceeded"
            )

        # Check drawdown limit
        if not self.check_drawdown_limit():
            return PositionRisk(
                symbol=symbol,
                position_size=size,
                leverage=leverage,
                risk_amount=position_value,
                risk_percent=risk_percent,
                is_acceptable=False,
                reason="Max drawdown limit exceeded"
            )

        # Position is acceptable
        return PositionRisk(
            symbol=symbol,
            position_size=size,
            leverage=leverage,
            risk_amount=position_value,
            risk_percent=risk_percent,
            is_acceptable=True
        )

    def calculate_stop_loss(self, entry_price: float, side: str) -> float:
        """Calculate stop loss price"""
        if side.lower() == "buy":
            return entry_price * (1 - self.limits.stop_loss_percent / 100)
        else:
            return entry_price * (1 + self.limits.stop_loss_percent / 100)

    def calculate_take_profit(self, entry_price: float, side: str) -> float:
        """Calculate take profit price"""
        if side.lower() == "buy":
            return entry_price * (1 + self.limits.take_profit_percent / 100)
        else:
            return entry_price * (1 - self.limits.take_profit_percent / 100)

    def should_stop_trading(self) -> tuple[bool, str]:
        """Determine if trading should be stopped"""

        # Daily loss limit
        if not self.check_daily_loss_limit():
            return True, "Daily loss limit exceeded"

        # Drawdown limit
        if not self.check_drawdown_limit():
            return True, "Max drawdown limit exceeded"

        # Consecutive losses
        if self.daily_losses >= 5:
            return True, "Too many consecutive losses"

        return False, ""

    def get_risk_report(self) -> Dict:
        """Generate risk report"""
        current_drawdown = (self.peak_balance - self.account_balance) / self.peak_balance * 100

        return {
            'account_balance': self.account_balance,
            'peak_balance': self.peak_balance,
            'daily_pnl': self.daily_pnl,
            'daily_trades': self.daily_trades,
            'daily_losses': self.daily_losses,
            'current_drawdown_percent': current_drawdown,
            'daily_loss_limit': self.account_balance * (self.limits.max_daily_loss_percent / 100),
            'max_drawdown_limit_percent': self.limits.max_drawdown_percent,
            'trading_allowed': not self.should_stop_trading()[0]
        }


# Example usage
if __name__ == "__main__":
    limits = RiskLimits(
        max_position_size_usd=10000,
        max_leverage=10,
        max_daily_loss_percent=5,
        max_drawdown_percent=20,
        stop_loss_percent=2,
        take_profit_percent=5,
        max_open_positions=5
    )

    risk_engine = RiskEngine(limits, account_balance=50000)

    # Calculate position size
    position_size = risk_engine.calculate_position_size(
        symbol="BTC",
        entry_price=40000,
        stop_loss_price=39200,
        risk_percent=1.0
    )

    print(f"Position size: {position_size}")

    # Validate position
    validation = risk_engine.validate_position("BTC", position_size, 40000, 2, 0)
    print(f"Validation: {validation}")

    # Get report
    report = risk_engine.get_risk_report()
    print(f"Risk report: {report}")
