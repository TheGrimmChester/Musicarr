FROM php:8.4-fpm

# Build arguments for user/group customization
ARG APP_USER_ID=1000
ARG APP_GROUP_ID=1000

# Install system dependencies including Node.js
RUN apt-get update \
    && apt-get install -y \
        git \
        unzip \
        libicu-dev \
        libzip-dev \
        libonig-dev \
        libxml2-dev \
        libcurl4-openssl-dev \
        libpng-dev \
        libjpeg-dev \
        libfreetype6-dev \
        mariadb-client \
        supervisor \
        cron \
        curl \
        nano \
    && docker-php-ext-install intl pdo pdo_mysql zip opcache gd \
    && pecl install redis \
    && docker-php-ext-enable redis

# Install Node.js and npm
RUN curl -fsSL https://deb.nodesource.com/setup_18.x | bash - \
    && apt-get install -y nodejs

# Install Composer
COPY --from=composer:2.7 /usr/bin/composer /usr/bin/composer

WORKDIR /app

# Copy the rest of the application
COPY . .

# Create appuser and set permissions only if not running as root
RUN if [ "$APP_USER_ID" != "0" ] && [ "$APP_GROUP_ID" != "0" ]; then \
    groupadd -g ${APP_GROUP_ID} appgroup && \
    useradd -u ${APP_USER_ID} -g appgroup -m appuser && \
    chown -R appuser:appgroup /app; \
fi

# Install dependencies as appuser if not root, else as root
RUN if [ "$APP_USER_ID" != "0" ] && [ "$APP_GROUP_ID" != "0" ]; then \
    su appuser -c "composer install --prefer-dist --no-interaction --no-progress"; \
else \
    composer install --prefer-dist --no-interaction --no-progress; \
fi

# Install npm dependencies and build assets
RUN if [ "$APP_USER_ID" != "0" ] && [ "$APP_GROUP_ID" != "0" ]; then \
    su appuser -c "npm install && npm run build"; \
else \
    npm install && npm run build; \
fi

RUN php bin/console c:c
#
## Create appuser and set permissions only if not running as root
#RUN if [ "$APP_USER_ID" != "0" ] && [ "$APP_GROUP_ID" != "0" ]; then \
#    chown -R appuser:appgroup /app; \
#fi

# Set up entrypoint script
COPY docker/docker-entrypoint.sh /usr/local/bin/
RUN chmod +x /usr/local/bin/docker-entrypoint.sh

# Copy supervisord configuration
COPY docker/supervisord.conf /etc/supervisor/conf.d/supervisord.conf

# Setup cron for task processing
RUN echo "* * * * * cd /app && /usr/local/bin/php bin/console app:process-tasks --limit=10 --single >> /var/log/cron.log 2>&1" > /etc/cron.d/process-tasks \
    && chmod 0644 /etc/cron.d/process-tasks \
    && crontab /etc/cron.d/process-tasks \
    && touch /var/log/cron.log

# Keep root as default user for entrypoint script
USER root

ENTRYPOINT ["/usr/local/bin/docker-entrypoint.sh"]
CMD ["/usr/bin/supervisord", "-c", "/etc/supervisor/conf.d/supervisord.conf"]
