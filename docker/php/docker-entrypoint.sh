#!/bin/sh
set -e

log_message() {
    echo "[$(date +'%Y-%m-%d %H:%M:%S')] $1"
}

error_exit() {
    log_message "ERROR: $1"
    exit 1
}

# Validate required environment variables
check_required_vars() {
    log_message "Checking required environment variables..."
    for var in MYSQL_HOST DATABASE_USER DATABASE_PASSWORD DATABASE_NAME RABBITMQ_HOST APP_ENV; do
        if [ -z "$(eval echo \$$var)" ]; then
            error_exit "Required environment variable $var is not set"
        fi
    done
}

wait_for_services() {
    # Wait for MySQL
    log_message "Waiting for MySQL..."
    while ! mysqladmin ping -h"$MYSQL_HOST" -u"$DATABASE_USER" -p"$DATABASE_PASSWORD" --silent; do
        sleep 2
    done
    log_message "MySQL is ready"

    # Wait for Redis
    redis_host=$(echo "$REDIS_URL" | sed -e 's,redis://,,g' -e 's,:.*,,g')
    redis_port=$(echo "$REDIS_URL" | sed -e 's,.*:,,')

    log_message "Waiting for Redis..."
    until timeout 2 bash -c "echo > /dev/tcp/$redis_host/$redis_port" 2>/dev/null; do
        log_message "Redis is not available - waiting"
        sleep 2
    done
    log_message "Redis is ready"

    # Wait for RabbitMQ
    log_message "Waiting for RabbitMQ..."
    until timeout 2 bash -c "echo > /dev/tcp/$RABBITMQ_HOST/5672" 2>/dev/null; do
        log_message "RabbitMQ is not available - waiting"
        sleep 2
    done
    log_message "RabbitMQ is ready"

    # Setup RabbitMQ exchanges and queues --env=prod
    log_message "Setting up RabbitMQ queues..."
    php /var/www/html/bin/console messenger:setup-transports -vv --env="$APP_ENV" || error_exit "Failed to setup RabbitMQ transports"
}

cleanup_supervisor() {
    log_message "Cleaning up supervisor files..."
    supervisor_files="/var/run/supervisord.sock /var/run/supervisord.pid /var/log/supervisord.log"
    
    for file in $supervisor_files; do
        rm -f "$file"
    done
    
    touch /var/log/supervisord.log
    chmod 777 /var/log/supervisord.log || error_exit "Failed to set permissions on supervisord.log"
}

setup_database() {
    log_message "Checking if database exists..."
    if ! mysql -h"$MYSQL_HOST" -u"$DATABASE_USER" -p"$DATABASE_PASSWORD" -e "USE $DATABASE_NAME" 2>/dev/null; then
        log_message "Database does not exist. Creating database..."
        if mysql -h"$MYSQL_HOST" -u"$DATABASE_USER" -p"$DATABASE_PASSWORD" -e "CREATE DATABASE IF NOT EXISTS $DATABASE_NAME CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"; then
            log_message "Database created successfully!"
        else
            error_exit "Failed to create database"
        fi
    else
        log_message "Database already exists"
    fi
}

# Retry mechanism for RabbitMQ setup
setup_rabbitmq_with_retry() {
    local max_attempts=5
    local attempt=1

    while [ $attempt -le $max_attempts ]; do
        log_message "Setting up RabbitMQ queues (Attempt $attempt)..."
        if php /var/www/html/bin/console messenger:setup-transports -vv --env="$APP_ENV"; then
            log_message "RabbitMQ queues setup successful"
            return 0
        fi
        
        log_message "RabbitMQ setup failed. Retrying in 5 seconds..."
        sleep 5
        attempt=$((attempt + 1))
    done

    error_exit "Failed to setup RabbitMQ transports after $max_attempts attempts"
}

# Robust cache clearing function
robust_cache_clear() {
    local env="$1"
    
    log_message "Checking cache for $env environment..."
    
    # Check if cache directory exists and is not empty
    if [ -d "/var/www/html/var/cache/$env" ] && [ "$(ls -A /var/www/html/var/cache/$env)" ]; then
        log_message "Clearing cache for $env environment..."
        php /var/www/html/bin/console cache:clear --no-debug --env="$env" || {
            log_message "Cache clear failed. Attempting forced removal..."
            rm -rf /var/www/html/var/cache/"$env"/*
        }
    else
        log_message "Cache directory for $env is empty or does not exist. Skipping."
    fi
}

# Modify setup_app function
setup_app() {
    # Robust cache clearing based on environment
    case "$APP_ENV" in
        "prod")
            log_message "Production environment detected. Running robust cache operations..."
            robust_cache_clear prod
            php /var/www/html/bin/console cache:warmup --no-debug --env=prod || error_exit "Failed to warm up cache"
            ;;
        "dev")
            log_message "Development environment detected. Running robust cache operations..."
            robust_cache_clear dev
            php /var/www/html/bin/console cache:warmup --no-debug --env=dev || error_exit "Failed to warm up cache"
            ;;
        *)
            log_message "Unknown environment '$APP_ENV'. Proceeding with caution..."
            ;;
    esac

    # Rest of the setup_app function remains the same...
    log_message "Checking current schema version..."
    current_version=$(php /var/www/html/bin/console doctrine:migrations:current --no-interaction --env="$APP_ENV" 2>/dev/null || echo "0")

    log_message "Running database migrations..."
    if ! php /var/www/html/bin/console doctrine:migrations:migrate --no-interaction --env="$APP_ENV" --allow-no-migration; then
        error_exit "Database migrations failed. Exiting..."
    fi

    log_message "Setting directory permissions..."
    if ! chown -R www-data:www-data var/; then
        error_exit "Failed to set directory ownership"
    fi
    if ! chmod -R 755 var/; then
        error_exit "Failed to set directory permissions"
    fi
}

setup_logs() {
    log_message "Setting up log files..."
    log_files="/var/log/news_parser.log /var/log/messenger_consumer.log"
    
    for file in $log_files; do
        if ! touch "$file"; then
            error_exit "Failed to create log file: $file"
        fi
        if ! chmod 666 "$file"; then
            error_exit "Failed to set permissions on log file: $file"
        fi
    done
}

main() {
    log_message "Starting container initialization..."
    
    cleanup_supervisor
    wait_for_services
    setup_database
    setup_app
    setup_rabbitmq_with_retry  # Use the new retry mechanism for RabbitMQ setup
    setup_logs

    log_message "Container initialization completed"

    # Start supervisor
    exec /usr/bin/supervisord -n -c /etc/supervisor/supervisord.conf
}

main "$@"