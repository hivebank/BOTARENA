"""
Configuration loader for Python microservices
"""

import os
import json

def get_config():
    """Load configuration from environment and settings file"""

    # Load settings.json
    settings_path = os.path.join(os.path.dirname(__file__), '..', 'config', 'settings.json')

    with open(settings_path, 'r') as f:
        settings = json.load(f)

    config = {
        'mongo_uri': os.getenv('MONGO_URI', 'mongodb://localhost:27017'),
        'mongo_db': 'hyperliquid_saas',
        'hyperliquid_api_url': os.getenv('HYPERLIQUID_API_URL', 'https://api.hyperliquid.xyz'),
        'hyperliquid_ws_url': os.getenv('HYPERLIQUID_WS_URL', 'wss://api.hyperliquid.xyz/ws'),
        'settings': settings
    }

    return config
