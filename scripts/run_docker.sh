#!/bin/bash
set -x
set -e


PACKAGENAME=compiler

echo 127.0.0.1   dev.codebender.cc >> /etc/hosts
echo 127.0.0.1   compiler.dev.codebender.cc >> /etc/hosts

if [ -z "$AUTH_KEY" ]
then
    echo "No authentication key variable set"
else
    echo "Authentication key is set. Changing parameters'"
    cd /opt/codebender/$PACKAGENAME
    cd Symfony
    sed -i "/    authorizationKey:/c\    authorizationKey: $AUTH_KEY" app/config/parameters.yml
    cat app/config/parameters.yml
    echo "Authentication key changed"

    echo "Clearing Cache"
    rm -rf app/cache/*
    echo "Cache Cleared"

    echo "Warming Cache"
    php app/console cache:warmup --env=dev
    php app/console cache:warmup --env=prod
    php app/console cache:warmup --env=test
    echo "Cache Warmed"

    echo "Setting Permissions"
    chown -R deploy:www-data app/cache
    chown -R deploy:www-data app/logs
    echo "Permissions Set"
fi

sleep 5

apache2ctl -DFOREGROUND
