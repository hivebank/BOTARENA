"""
Hyperliquid API Client Wrapper
Complete REST + WebSocket implementation for trading operations
"""

import asyncio
import aiohttp
import websockets
import json
import time
from typing import Dict, List, Optional, Any
from dataclasses import dataclass
from decimal import Decimal
from util_logger import get_logger

logger = get_logger("HyperliquidClient")


@dataclass
class OrderResult:
    """Order execution result"""
    order_id: str
    symbol: str
    side: str
    price: float
    size: float
    status: str
    timestamp: int


@dataclass
class Position:
    """Trading position"""
    symbol: str
    size: float
    entry_price: float
    current_price: float
    unrealized_pnl: float
    leverage: int


class HyperliquidClient:
    """Complete Hyperliquid API client with REST and WebSocket support"""

    def __init__(self, api_key: Optional[str] = None, secret: Optional[str] = None,
                 testnet: bool = False):
        self.api_key = api_key
        self.secret = secret
        self.testnet = testnet

        # API endpoints
        self.base_url = "https://api.hyperliquid-testnet.xyz" if testnet else "https://api.hyperliquid.xyz"
        self.ws_url = "wss://api.hyperliquid-testnet.xyz/ws" if testnet else "wss://api.hyperliquid.xyz/ws"

        # Session
        self.session: Optional[aiohttp.ClientSession] = None
        self.ws_connection: Optional[websockets.WebSocketClientProtocol] = None

        # Rate limiting
        self.rate_limit_delay = 0.1  # 100ms between requests

    async def _ensure_session(self):
        """Ensure aiohttp session exists"""
        if self.session is None or self.session.closed:
            self.session = aiohttp.ClientSession()

    async def _request(self, endpoint: str, method: str = "GET",
                       params: Optional[Dict] = None, data: Optional[Dict] = None) -> Dict:
        """Make HTTP request to Hyperliquid API"""
        await self._ensure_session()

        url = f"{self.base_url}{endpoint}"
        headers = {
            "Content-Type": "application/json"
        }

        if self.api_key:
            headers["X-API-Key"] = self.api_key

        try:
            async with self.session.request(method, url, headers=headers,
                                           params=params, json=data) as response:
                response.raise_for_status()
                result = await response.json()

                # Rate limiting
                await asyncio.sleep(self.rate_limit_delay)

                return result

        except aiohttp.ClientError as e:
            logger.error(f"HTTP request failed: {e}")
            raise

    # Market Data
    async def get_ticker(self, symbol: str) -> Dict:
        """Get ticker data for symbol"""
        try:
            result = await self._request(f"/info/ticker", params={"coin": symbol})
            logger.debug(f"Ticker for {symbol}: {result}")
            return result
        except Exception as e:
            logger.error(f"Failed to get ticker for {symbol}: {e}")
            return {}

    async def get_orderbook(self, symbol: str, depth: int = 20) -> Dict:
        """Get orderbook for symbol"""
        try:
            result = await self._request(f"/info/l2Book", params={"coin": symbol, "nSigFigs": depth})
            return result
        except Exception as e:
            logger.error(f"Failed to get orderbook for {symbol}: {e}")
            return {"bids": [], "asks": []}

    async def get_candles(self, symbol: str, interval: str = "1m",
                         start_time: Optional[int] = None,
                         end_time: Optional[int] = None, limit: int = 500) -> List[Dict]:
        """Get historical candles"""
        try:
            params = {
                "coin": symbol,
                "interval": interval,
                "startTime": start_time or int(time.time() * 1000) - (86400 * 1000),
                "endTime": end_time or int(time.time() * 1000)
            }

            result = await self._request("/info/candles", params=params)
            return result if isinstance(result, list) else []

        except Exception as e:
            logger.error(f"Failed to get candles for {symbol}: {e}")
            return []

    async def get_funding_rate(self, symbol: str) -> float:
        """Get current funding rate"""
        try:
            result = await self._request(f"/info/fundingRate", params={"coin": symbol})
            return float(result.get("fundingRate", 0))
        except Exception as e:
            logger.error(f"Failed to get funding rate for {symbol}: {e}")
            return 0.0

    # Trading Operations
    async def place_market_order(self, symbol: str, side: str, size: float,
                                 reduce_only: bool = False) -> OrderResult:
        """Place market order"""
        try:
            order_data = {
                "coin": symbol,
                "is_buy": side.lower() == "buy",
                "sz": size,
                "limit_px": 0,  # Market order
                "order_type": {"limit": {"tif": "Ioc"}},
                "reduce_only": reduce_only
            }

            result = await self._request("/exchange/order", method="POST", data=order_data)

            logger.trade(
                bot_id="system",
                symbol=symbol,
                side=side,
                price=float(result.get("avg_px", 0)),
                size=size
            )

            return OrderResult(
                order_id=result.get("oid", ""),
                symbol=symbol,
                side=side,
                price=float(result.get("avg_px", 0)),
                size=size,
                status=result.get("status", "unknown"),
                timestamp=int(time.time() * 1000)
            )

        except Exception as e:
            logger.error(f"Failed to place market order: {e}")
            raise

    async def place_limit_order(self, symbol: str, side: str, size: float, price: float,
                               post_only: bool = False, reduce_only: bool = False) -> OrderResult:
        """Place limit order"""
        try:
            order_data = {
                "coin": symbol,
                "is_buy": side.lower() == "buy",
                "sz": size,
                "limit_px": price,
                "order_type": {"limit": {"tif": "Gtc" if not post_only else "Alo"}},
                "reduce_only": reduce_only
            }

            result = await self._request("/exchange/order", method="POST", data=order_data)

            logger.info(f"Limit order placed: {side} {size} {symbol} @ {price}")

            return OrderResult(
                order_id=result.get("oid", ""),
                symbol=symbol,
                side=side,
                price=price,
                size=size,
                status=result.get("status", "unknown"),
                timestamp=int(time.time() * 1000)
            )

        except Exception as e:
            logger.error(f"Failed to place limit order: {e}")
            raise

    async def cancel_order(self, order_id: str, symbol: str) -> bool:
        """Cancel order"""
        try:
            data = {
                "coin": symbol,
                "oid": order_id
            }

            result = await self._request("/exchange/cancel", method="POST", data=data)
            logger.info(f"Order {order_id} cancelled")

            return result.get("status") == "success"

        except Exception as e:
            logger.error(f"Failed to cancel order {order_id}: {e}")
            return False

    async def cancel_all_orders(self, symbol: Optional[str] = None) -> int:
        """Cancel all orders"""
        try:
            data = {}
            if symbol:
                data["coin"] = symbol

            result = await self._request("/exchange/cancelAll", method="POST", data=data)
            cancelled_count = len(result.get("cancelled", []))

            logger.info(f"Cancelled {cancelled_count} orders")
            return cancelled_count

        except Exception as e:
            logger.error(f"Failed to cancel all orders: {e}")
            return 0

    # Position Management
    async def get_positions(self) -> List[Position]:
        """Get all open positions"""
        try:
            result = await self._request("/info/user/positions")

            positions = []
            for pos in result.get("positions", []):
                positions.append(Position(
                    symbol=pos["coin"],
                    size=float(pos["szi"]),
                    entry_price=float(pos["entryPx"]),
                    current_price=float(pos["liquidationPx"]),
                    unrealized_pnl=float(pos["unrealizedPnl"]),
                    leverage=int(pos.get("leverage", 1))
                ))

            return positions

        except Exception as e:
            logger.error(f"Failed to get positions: {e}")
            return []

    async def get_position(self, symbol: str) -> Optional[Position]:
        """Get specific position"""
        positions = await self.get_positions()

        for pos in positions:
            if pos.symbol == symbol:
                return pos

        return None

    async def close_position(self, symbol: str) -> bool:
        """Close position completely"""
        try:
            position = await self.get_position(symbol)

            if not position or position.size == 0:
                logger.warning(f"No position to close for {symbol}")
                return False

            # Close with market order
            side = "sell" if position.size > 0 else "buy"
            size = abs(position.size)

            await self.place_market_order(symbol, side, size, reduce_only=True)

            logger.info(f"Position closed: {symbol}")
            return True

        except Exception as e:
            logger.error(f"Failed to close position {symbol}: {e}")
            return False

    async def set_leverage(self, symbol: str, leverage: int) -> bool:
        """Set leverage for symbol"""
        try:
            data = {
                "coin": symbol,
                "leverage": leverage
            }

            result = await self._request("/exchange/updateLeverage", method="POST", data=data)
            logger.info(f"Leverage set to {leverage}x for {symbol}")

            return result.get("status") == "success"

        except Exception as e:
            logger.error(f"Failed to set leverage for {symbol}: {e}")
            return False

    # Account Info
    async def get_account_balance(self) -> Dict:
        """Get account balance"""
        try:
            result = await self._request("/info/user/state")

            return {
                "total_equity": float(result.get("marginSummary", {}).get("accountValue", 0)),
                "available_balance": float(result.get("marginSummary", {}).get("totalMarginAvailable", 0)),
                "total_pnl": float(result.get("marginSummary", {}).get("totalRawUsd", 0))
            }

        except Exception as e:
            logger.error(f"Failed to get account balance: {e}")
            return {"total_equity": 0, "available_balance": 0, "total_pnl": 0}

    # WebSocket Methods
    async def connect_ws(self):
        """Connect to WebSocket"""
        try:
            self.ws_connection = await websockets.connect(self.ws_url)
            logger.info("WebSocket connected")

        except Exception as e:
            logger.error(f"WebSocket connection failed: {e}")
            raise

    async def subscribe_ticker(self, symbol: str, callback):
        """Subscribe to ticker updates"""
        if not self.ws_connection:
            await self.connect_ws()

        subscribe_msg = {
            "method": "subscribe",
            "subscription": {"type": "ticker", "coin": symbol}
        }

        await self.ws_connection.send(json.dumps(subscribe_msg))

        # Listen for updates
        async for message in self.ws_connection:
            data = json.loads(message)
            await callback(data)

    async def close(self):
        """Close all connections"""
        if self.session and not self.session.closed:
            await self.session.close()

        if self.ws_connection:
            await self.ws_connection.close()

        logger.info("Hyperliquid client closed")


# Example usage
async def main():
    client = HyperliquidClient(testnet=True)

    # Get ticker
    ticker = await client.get_ticker("BTC")
    print(f"BTC Ticker: {ticker}")

    # Get candles
    candles = await client.get_candles("BTC", "1h", limit=100)
    print(f"Candles: {len(candles)}")

    await client.close()


if __name__ == "__main__":
    asyncio.run(main())
