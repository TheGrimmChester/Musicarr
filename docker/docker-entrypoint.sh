#!/bin/sh
set -e

# If not running as root, update permissions and configs
if [ "$APP_USER_ID" != "0" ] || [ "$APP_GROUP_ID" != "0" ]; then
    # Update PHP-FPM configuration
    sed -i "s/^user = .*/user = appuser/" /usr/local/etc/php-fpm.d/www.conf
    sed -i "s/^group = .*/group = appuser/" /usr/local/etc/php-fpm.d/www.conf

    # Update permissions
    chown -R "$APP_USER_ID":"$APP_GROUP_ID" /app /var/log /var/run
else
    # Set PHP-FPM to run as root
    sed -i "s/^user = .*/user = root/" /usr/local/etc/php-fpm.d/www.conf
    sed -i "s/^group = .*/group = root/" /usr/local/etc/php-fpm.d/www.conf
fi

# Ensure supervisord has correct permissions for its log and pid
mkdir -p /var/log/supervisor /var/run
chmod 755 /var/run /var/log/supervisor

# If running as non-root user, adjust permissions
if [ "$APP_USER_ID" != "0" ] || [ "$APP_GROUP_ID" != "0" ]; then
    chown -R "$APP_USER_ID":"$APP_GROUP_ID" /var/log/supervisor
fi

cd /app/; php bin/console  doctrine:database:create --if-not-exists -n
cd /app/; php bin/console doctrine:migrations:migrate -n

cd /app/; npm run build

# Start supervisord
exec "$@"
