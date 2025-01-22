FROM php:7.4-fpm-alpine

# Install system dependencies all at once to reduce layers
RUN apk update && apk add --no-cache \
    git \
    curl \
    libpng-dev \
    libxml2-dev \
    libssh-dev \
    libjpeg-turbo-dev \
    libxpm-dev \
    zip \
    unzip \
    bash \
    rabbitmq-c-dev \
    shadow \
    dcron \
    libcap \
    oniguruma-dev \
    supervisor \
    autoconf \
    gcc \
    g++ \
    make \
    mariadb-client \
    freetype-dev \
    icu-dev \
    libzip-dev \
    && setcap 'cap_setgid=+ep' /bin/busybox

# Configure and install PHP extensions in a single layer
RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-configure intl \
    && docker-php-ext-install -j$(nproc) \
        pdo_mysql \
        mbstring \
        exif \
        pcntl \
        bcmath \
        gd \
        sockets \
        opcache \
        zip \
        intl \
    && pecl channel-update pecl.php.net \
    && pecl install amqp \
    && docker-php-ext-enable amqp

# Set up permissions and capabilities
# RUN setcap 'cap_setgid=+ep' /bin/busybox

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer
ENV COMPOSER_ALLOW_SUPERUSER=1 \
    COMPOSER_HOME=/composer \
    PATH=/composer/vendor/bin:$PATH

# Add these lines to your Dockerfile
RUN mkdir -p /var/run \
    && chmod 755 /var/run \
    && mkdir -p /var/log \
    && chmod 755 /var/log

# Create necessary directories and files
RUN mkdir -p /var/log /etc/supervisor /var/www/html/var \
    && mkdir -p /var/run/supervisor \
    # && touch /var/log/cron.log \
    && touch /var/log/supervisor.log \
    # && chmod 0777 /var/log/cron.log \
    && chmod 0777 /var/log/supervisor.log
    # && mkdir -p /etc/cron.d \
    # && touch /var/log/news_parser.log \
    # && chmod 0777 /var/log/news_parser.log

# Copy configuration files
COPY supervisor.conf /etc/supervisor/supervisord.conf
# COPY crontab /etc/cron.d/symfony-cron
COPY docker-entrypoint.sh /usr/local/bin/

# Set up permissions
# RUN chmod 0644 /etc/cron.d/symfony-cron \
#     && chmod +x /usr/local/bin/docker-entrypoint.sh \
#     && crontab /etc/cron.d/symfony-cron
RUN chmod +x /usr/local/bin/docker-entrypoint.sh

# Set working directory
WORKDIR /var/www/html

# Clean up
RUN apk del autoconf gcc g++ make \
    && rm -rf /tmp/* /var/cache/apk/*

ENTRYPOINT ["/usr/local/bin/docker-entrypoint.sh"]
# CMD ["sh", "-c", "crond -f -l 8 & /usr/bin/supervisord -c /etc/supervisor.d/supervisord.conf & php-fpm"]