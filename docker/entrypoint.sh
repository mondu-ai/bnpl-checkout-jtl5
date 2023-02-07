#!/bin/sh

if [ ! -d /var/lib/mysql/jtl_shop ] ; then

   php /var/www/html/cli shop:install --shop-url="http://${SHOP_DOMAIN}" \
      --database-host="${DB_HOST}" \
      --database-name="${DB_NAME}" \
      --database-user="${DB_USERNAME}" \
      --database-password="${DB_PASSWORD}" \
      --admin-user="${SHOP_ADMIN_USERNAME}" \
      --admin-password="${SHOP_ADMIN_PASSWORD}" \
      --sync-user="${SHOP_SYNC_USERNAME}" \
      --sync-password="${SHOP_SYNC_PASSWORD}" \
      --install-demo-data

fi

service apache2 start

php /var/www/html/activate.php

exec "$@"

tail -f /dev/null
