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
    for var in MYSQL_HOST MYSQL_USER MYSQL_PASSWORD MYSQL_DATABASE RABBITMQ_HOST APP_ENV; do
        if [ -z "$(eval echo \$$var)" ]; then
            error_exit "Required environment variable $var is not set"
        fi
    done
}

wait_for_services() {
    # Wait for MySQL
    log_message "Waiting for MySQL..."
    while ! mysqladmin ping -h"$MYSQL_HOST" -u"$MYSQL_USER" -p"$MYSQL_PASSWORD" --silent; do
        sleep 2
    done
    log_message "MySQL is ready"

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
    if ! mysql -h"$MYSQL_HOST" -u"$MYSQL_USER" -p"$MYSQL_PASSWORD" -e "USE $MYSQL_DATABASE" 2>/dev/null; then
        log_message "Database does not exist. Creating database..."
        if mysql -h"$MYSQL_HOST" -u"$MYSQL_USER" -p"$MYSQL_PASSWORD" -e "CREATE DATABASE IF NOT EXISTS $MYSQL_DATABASE CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"; then
            log_message "Database created successfully!"
        else
            error_exit "Failed to create database"
        fi
    else
        log_message "Database already exists"
    fi
}

setup_app() {
    # Cache operations based on environment
    case "$APP_ENV" in
        "prod")
            log_message "Production environment detected. Running cache operations..."
            php /var/www/html/bin/console cache:clear --no-debug --env=prod || error_exit "Failed to clear cache"
            php /var/www/html/bin/console cache:warmup --no-debug --env=prod || error_exit "Failed to warm up cache"
            ;;
        "dev")
            # log_message "Development environment detected. Skipping cache operations..."
            log_message "Development environment detected. Running cache operations..."
            # php /var/www/html/bin/console cache:clear --no-debug --env=dev || error_exit "Failed to clear cache"
            # php /var/www/html/bin/console cache:warmup --no-debug --env=dev || error_exit "Failed to warm up cache"
            ;;
        *)
            log_message "Unknown environment '$APP_ENV'. Proceeding with caution..."
            ;;
    esac

    # Check current schema version
    log_message "Checking current schema version..."
    current_version=$(php /var/www/html/bin/console doctrine:migrations:current --no-interaction --env="$APP_ENV" 2>/dev/null || echo "0")

    # Update schema
    # php /var/www/html/bin/console doctrine:schema:update -f --no-interaction

    # Run migrations with --allow-no-migration flag
    log_message "Running database migrations..."
    if ! php /var/www/html/bin/console doctrine:migrations:migrate --no-interaction --env="$APP_ENV"; then
        log_message "Migration failed, attempting to continue..."
    fi

    # Set permissions
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

    setup_logs

    log_message "Container initialization completed"

    # Start supervisor
    exec /usr/bin/supervisord -n -c /etc/supervisor/supervisord.conf
}

main "$@"