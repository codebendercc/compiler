#!/bin/bash
set -x
set -e


PACKAGENAME=compiler

echo 127.0.0.1   dev.codebender.cc >> /etc/hosts
echo 127.0.0.1   compiler.dev.codebender.cc >> /etc/hosts

if [ -z "$APP_ENV" ] || [ -z "$APP_SECRET" ] || [ -z "$AUTH_KEY" ]
then
    echo "No env vars set"
else
    echo "Creating a .env.local file'"
    cd /opt/codebender/$PACKAGENAME
    cd Symfony
    cp .env .env.local

    if [ -z "$APP_ENV" ]
    then
        echo "No app env variable set"
    else
        echo "App env is set. Changing .env.local file"
        sed -i "/APP_ENV=/c\APP_ENV=$APP_ENV" .env.local
        cat .env.local
        echo "App env key changed"
    fi

    if [ -z "$APP_SECRET" ]
    then
        echo "No app secret variable set"
    else
        echo "App secret is set. Changing .env.local file"
        sed -i "/APP_SECRET=/c\APP_SECRET=$APP_SECRET" .env.local
        cat .env.local
        echo "App secret changed"
    fi

    if [ -z "$AUTH_KEY" ]
    then
        echo "No authentication key variable set"
    else
        echo "Authentication key is set. Changing .env.local file"
        sed -i "/AUTH_KEY=/c\AUTH_KEY=$AUTH_KEY" .env.local
        cat .env.local
        echo "Authentication key changed"
    fi

    if [[ "$APP_ENV" != "dev" ]]
    then
        echo "Turning xdebug off"
        export XDEBUG_MODE="off"

        echo "Turning error reporting off"
        sed -i "/error_reporting = /c\error_reporting = E_ALL & ~E_DEPRECATED & ~E_STRICT" /etc/php/8.1/apache2/php.ini
    else
        echo "Leaving xdebug and error reporting on"
    fi

    echo "Clearing Cache"
    rm -rf var/cache/*
    echo "Cache Cleared"

    echo "Warming Cache"
    php bin/console cache:warmup --env=dev
    php bin/console cache:warmup --env=prod
    php bin/console cache:warmup --env=test
    echo "Cache Warmed"

    echo "Setting Permissions"
    chown -R deploy:www-data var/cache
    chown -R deploy:www-data var/log
    echo "Permissions Set"
fi

sleep 5

tail -f /var/log/apache2/other_vhosts_access.log & \
tail -f /var/log/apache2/error.log & \
apache2ctl -DFOREGROUND
