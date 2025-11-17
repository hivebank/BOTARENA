# Multi-stage Dockerfile for Hyperliquid Trading SaaS on Railway
FROM ubuntu:22.04

# Prevent interactive prompts
ENV DEBIAN_FRONTEND=noninteractive
ENV PORT=8080

# Install system dependencies
RUN apt-get update && apt-get install -y \
    # PHP and extensions
    php8.1 \
    php8.1-fpm \
    php8.1-cli \
    php8.1-mongodb \
    php8.1-curl \
    php8.1-json \
    php8.1-mbstring \
    php8.1-xml \
    # Python
    python3 \
    python3-pip \
    python3-venv \
    # Nginx
    nginx \
    # Process manager
    supervisor \
    # Utilities
    curl \
    wget \
    git \
    vim \
    && rm -rf /var/lib/apt/lists/*

# Create application directory
WORKDIR /app

# Copy application files
COPY . /app/

# Install Python dependencies
RUN pip3 install --no-cache-dir -r requirements.txt

# Configure PHP-FPM
RUN sed -i 's/listen = .*/listen = 127.0.0.1:9000/' /etc/php/8.1/fpm/pool.d/www.conf && \
    sed -i 's/;clear_env = no/clear_env = no/' /etc/php/8.1/fpm/pool.d/www.conf

# Create nginx configuration
RUN rm -f /etc/nginx/sites-enabled/default

# Copy nginx config
COPY railway-nginx.conf /etc/nginx/sites-available/hyperliquid
RUN ln -s /etc/nginx/sites-available/hyperliquid /etc/nginx/sites-enabled/

# Copy supervisor config
COPY railway-supervisor.conf /etc/supervisor/conf.d/hyperliquid.conf

# Create necessary directories
RUN mkdir -p /app/logs /run/php && \
    chmod -R 755 /app && \
    chmod -R 777 /app/logs && \
    chmod +x /app/railway-start.sh

# Expose port
EXPOSE 8080

# Health check
HEALTHCHECK --interval=30s --timeout=10s --start-period=40s --retries=3 \
    CMD curl -f http://localhost:8080/health.php || exit 1

# Start command
CMD ["/app/railway-start.sh"]
