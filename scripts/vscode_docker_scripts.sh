#!/bin/bash
set -x
set -e


PACKAGENAME=compiler

echo 127.0.0.1   dev.codebender.cc >> /etc/hosts
echo 127.0.0.1   compiler.dev.codebender.cc >> /etc/hosts

cd /opt/codebender/$PACKAGENAME
# cp symfony-configs/compiler-parameters.yml Symfony/app/config/parameters.yml

cd Symfony

rm -rf app/cache/*
rm -rf app/logs/*

composer install

chown -R deploy:www-data .

