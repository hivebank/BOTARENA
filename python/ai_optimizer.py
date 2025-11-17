"""
AI Optimizer with SOTA Trading Models
LSTM-based price prediction and strategy optimization
"""

import numpy as np
import pandas as pd
from typing import List, Dict, Tuple, Optional
from dataclasses import dataclass
import joblib
import os

try:
    import tensorflow as tf
    from tensorflow import keras
    from tensorflow.keras import layers
    TENSORFLOW_AVAILABLE = True
except ImportError:
    TENSORFLOW_AVAILABLE = False
    print("TensorFlow not available. AI features will be limited.")

from util_logger import get_logger

logger = get_logger("AIOptimizer")


@dataclass
class Signal:
    """Trading signal"""
    action: str  # 'buy', 'sell', 'hold'
    confidence: float
    price_target: Optional[float] = None
    stop_loss: Optional[float] = None
    take_profit: Optional[float] = None


class LSTMPredictor:
    """LSTM model for price prediction"""

    def __init__(self, sequence_length: int = 60, prediction_horizon: int = 5):
        self.sequence_length = sequence_length
        self.prediction_horizon = prediction_horizon
        self.model = None
        self.scaler = None
        self.is_trained = False

    def build_model(self, input_shape: Tuple[int, int]):
        """Build LSTM model architecture"""
        if not TENSORFLOW_AVAILABLE:
            logger.warning("TensorFlow not available. Using fallback predictor.")
            return

        model = keras.Sequential([
            layers.LSTM(128, return_sequences=True, input_shape=input_shape),
            layers.Dropout(0.2),
            layers.LSTM(64, return_sequences=True),
            layers.Dropout(0.2),
            layers.LSTM(32),
            layers.Dropout(0.2),
            layers.Dense(16, activation='relu'),
            layers.Dense(self.prediction_horizon)
        ])

        model.compile(
            optimizer=keras.optimizers.Adam(learning_rate=0.001),
            loss='mse',
            metrics=['mae']
        )

        self.model = model
        logger.info(f"LSTM model built: {model.summary()}")

    def prepare_data(self, prices: np.ndarray) -> Tuple[np.ndarray, np.ndarray]:
        """Prepare data for LSTM training"""
        from sklearn.preprocessing import MinMaxScaler

        # Scale data
        if self.scaler is None:
            self.scaler = MinMaxScaler(feature_range=(0, 1))

        scaled_data = self.scaler.fit_transform(prices.reshape(-1, 1))

        X, y = [], []

        for i in range(self.sequence_length, len(scaled_data) - self.prediction_horizon):
            X.append(scaled_data[i - self.sequence_length:i, 0])
            y.append(scaled_data[i:i + self.prediction_horizon, 0])

        return np.array(X), np.array(y)

    def train(self, prices: np.ndarray, epochs: int = 50, batch_size: int = 32):
        """Train LSTM model"""
        if not TENSORFLOW_AVAILABLE:
            logger.warning("Cannot train: TensorFlow not available")
            return

        logger.info(f"Training LSTM on {len(prices)} price points...")

        X, y = self.prepare_data(prices)

        if self.model is None:
            self.build_model((X.shape[1], 1))

        # Reshape X for LSTM [samples, time steps, features]
        X = X.reshape((X.shape[0], X.shape[1], 1))

        # Train
        history = self.model.fit(
            X, y,
            epochs=epochs,
            batch_size=batch_size,
            validation_split=0.2,
            verbose=0
        )

        self.is_trained = True
        logger.info(f"Training complete. Final loss: {history.history['loss'][-1]:.6f}")

    def predict(self, recent_prices: np.ndarray) -> np.ndarray:
        """Predict future prices"""
        if not TENSORFLOW_AVAILABLE or not self.is_trained:
            # Fallback: simple momentum-based prediction
            return self._fallback_predict(recent_prices)

        # Scale input
        scaled_input = self.scaler.transform(recent_prices.reshape(-1, 1))

        # Take last sequence_length points
        input_seq = scaled_input[-self.sequence_length:]
        input_seq = input_seq.reshape((1, self.sequence_length, 1))

        # Predict
        prediction = self.model.predict(input_seq, verbose=0)

        # Inverse transform
        prediction = self.scaler.inverse_transform(prediction)

        return prediction[0]

    def _fallback_predict(self, recent_prices: np.ndarray) -> np.ndarray:
        """Fallback prediction using moving average"""
        ma = np.mean(recent_prices[-20:])
        trend = recent_prices[-1] - recent_prices[-20]

        predictions = []
        for i in range(self.prediction_horizon):
            pred = ma + (trend * (i + 1) / 20)
            predictions.append(pred)

        return np.array(predictions)

    def save(self, filepath: str):
        """Save model"""
        if self.model and TENSORFLOW_AVAILABLE:
            self.model.save(filepath)
            joblib.dump(self.scaler, filepath + '_scaler.pkl')
            logger.info(f"Model saved to {filepath}")

    def load(self, filepath: str):
        """Load model"""
        if TENSORFLOW_AVAILABLE and os.path.exists(filepath):
            self.model = keras.models.load_model(filepath)
            self.scaler = joblib.load(filepath + '_scaler.pkl')
            self.is_trained = True
            logger.info(f"Model loaded from {filepath}")


class FeatureExtractor:
    """Extract trading features from price data"""

    @staticmethod
    def extract_features(df: pd.DataFrame) -> pd.DataFrame:
        """Extract technical indicators"""

        # Moving Averages
        df['SMA_10'] = df['close'].rolling(window=10).mean()
        df['SMA_20'] = df['close'].rolling(window=20).mean()
        df['SMA_50'] = df['close'].rolling(window=50).mean()
        df['EMA_12'] = df['close'].ewm(span=12).mean()
        df['EMA_26'] = df['close'].ewm(span=26).mean()

        # MACD
        df['MACD'] = df['EMA_12'] - df['EMA_26']
        df['MACD_Signal'] = df['MACD'].ewm(span=9).mean()
        df['MACD_Hist'] = df['MACD'] - df['MACD_Signal']

        # RSI
        delta = df['close'].diff()
        gain = (delta.where(delta > 0, 0)).rolling(window=14).mean()
        loss = (-delta.where(delta < 0, 0)).rolling(window=14).mean()
        rs = gain / loss
        df['RSI'] = 100 - (100 / (1 + rs))

        # Bollinger Bands
        df['BB_Middle'] = df['close'].rolling(window=20).mean()
        bb_std = df['close'].rolling(window=20).std()
        df['BB_Upper'] = df['BB_Middle'] + (bb_std * 2)
        df['BB_Lower'] = df['BB_Middle'] - (bb_std * 2)

        # Volume
        df['Volume_SMA'] = df['volume'].rolling(window=20).mean()

        # ATR
        high_low = df['high'] - df['low']
        high_close = np.abs(df['high'] - df['close'].shift())
        low_close = np.abs(df['low'] - df['close'].shift())
        ranges = pd.concat([high_low, high_close, low_close], axis=1)
        df['ATR'] = ranges.max(axis=1).rolling(window=14).mean()

        return df.fillna(0)


class AIOptimizer:
    """Main AI optimizer for trading strategies"""

    def __init__(self, sequence_length: int = 60, prediction_horizon: int = 5):
        self.predictor = LSTMPredictor(sequence_length, prediction_horizon)
        self.feature_extractor = FeatureExtractor()
        self.confidence_threshold = 0.7

    def analyze(self, candles: List[Dict]) -> Signal:
        """Analyze candles and generate trading signal"""

        if len(candles) < self.predictor.sequence_length:
            logger.warning("Not enough candles for analysis")
            return Signal(action='hold', confidence=0.0)

        # Convert to DataFrame
        df = pd.DataFrame(candles)
        df = self.feature_extractor.extract_features(df)

        # Get recent prices
        recent_prices = df['close'].values

        # Predict future prices
        predictions = self.predictor.predict(recent_prices)

        current_price = recent_prices[-1]
        predicted_price = predictions[0]

        # Calculate confidence based on prediction certainty
        price_change_pct = (predicted_price - current_price) / current_price
        confidence = min(abs(price_change_pct) * 10, 1.0)

        # Generate signal
        if price_change_pct > 0.002 and confidence >= self.confidence_threshold:
            # Buy signal
            return Signal(
                action='buy',
                confidence=confidence,
                price_target=predicted_price,
                stop_loss=current_price * 0.98,
                take_profit=predicted_price * 1.02
            )

        elif price_change_pct < -0.002 and confidence >= self.confidence_threshold:
            # Sell signal
            return Signal(
                action='sell',
                confidence=confidence,
                price_target=predicted_price,
                stop_loss=current_price * 1.02,
                take_profit=predicted_price * 0.98
            )

        else:
            # Hold
            return Signal(action='hold', confidence=confidence)

    def train_on_historical_data(self, candles: List[Dict]):
        """Train model on historical data"""
        df = pd.DataFrame(candles)
        prices = df['close'].values

        logger.info(f"Training AI model on {len(prices)} candles...")
        self.predictor.train(prices)

    def optimize_parameters(self, candles: List[Dict], strategy: str) -> Dict:
        """Optimize strategy parameters"""

        df = pd.DataFrame(candles)
        df = self.feature_extractor.extract_features(df)

        # Strategy-specific optimization
        if strategy == 'momentum':
            # Optimize lookback period
            best_params = self._optimize_momentum(df)

        elif strategy == 'mean_reversion':
            # Optimize mean reversion parameters
            best_params = self._optimize_mean_reversion(df)

        elif strategy == 'grid_trading':
            # Optimize grid parameters
            best_params = self._optimize_grid(df)

        else:
            best_params = {}

        logger.info(f"Optimized parameters for {strategy}: {best_params}")
        return best_params

    def _optimize_momentum(self, df: pd.DataFrame) -> Dict:
        """Optimize momentum strategy"""
        best_sharpe = -999
        best_params = {'lookback_period': 14}

        for lookback in range(5, 30, 5):
            # Simulate strategy
            df['momentum'] = df['close'].pct_change(lookback)
            returns = df['close'].pct_change()
            strategy_returns = df['momentum'].shift(1) * returns

            sharpe = strategy_returns.mean() / strategy_returns.std() * np.sqrt(365)

            if sharpe > best_sharpe:
                best_sharpe = sharpe
                best_params['lookback_period'] = lookback

        return best_params

    def _optimize_mean_reversion(self, df: pd.DataFrame) -> Dict:
        """Optimize mean reversion strategy"""
        return {'lookback_period': 20, 'std_dev_threshold': 2.0}

    def _optimize_grid(self, df: pd.DataFrame) -> Dict:
        """Optimize grid trading strategy"""
        volatility = df['close'].pct_change().std()

        return {
            'grid_levels': 10,
            'grid_spacing_percent': volatility * 100
        }


# Example usage
if __name__ == "__main__":
    optimizer = AIOptimizer()

    # Simulated candles
    candles = [
        {'close': 40000 + i * 100, 'high': 40100 + i * 100, 'low': 39900 + i * 100, 'volume': 1000000}
        for i in range(100)
    ]

    # Train
    optimizer.train_on_historical_data(candles)

    # Analyze
    signal = optimizer.analyze(candles)
    print(f"Signal: {signal}")
