FROM php:7.4-fpm-alpine

# Install system dependencies
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

# Configure and install PHP extensions
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
    && pecl install amqp redis \
    && docker-php-ext-enable amqp redis

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer
ENV COMPOSER_ALLOW_SUPERUSER=1 \
    COMPOSER_HOME=/composer \
    PATH=/composer/vendor/bin:$PATH

# Create necessary directories and files
RUN mkdir -p /var/log/php-fpm \
    && mkdir -p /var/run/supervisor \
    && mkdir -p /var/log/nginx \
    && touch /var/log/supervisor.log \
    && touch /var/log/php-fpm/error.log \
    && touch /var/log/nginx/error.log \
    && touch /var/log/nginx/access.log \
    && chmod 0777 /var/log/supervisor.log \
    && chmod 0777 /var/log/php-fpm/error.log \
    && chmod 0777 /var/log/nginx/error.log \
    && chmod 0777 /var/log/nginx/access.log

# Copy configuration files
COPY supervisor.conf /etc/supervisor/supervisord.conf
COPY docker-entrypoint.sh /usr/local/bin/
COPY php-fpm.conf /usr/local/etc/php-fpm.d/custom.conf

# Set up permissions for entrypoint script
RUN chmod +x /usr/local/bin/docker-entrypoint.sh

# Set working directory
WORKDIR /var/www/html

# Clean up unnecessary packages and temporary files
RUN apk del autoconf gcc g++ make \
    && rm -rf /tmp/* /var/cache/apk/*

ENTRYPOINT ["/usr/local/bin/docker-entrypoint.sh"]