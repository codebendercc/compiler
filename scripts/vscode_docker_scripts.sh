#!/bin/bash
set -x
set -e


PACKAGENAME=compiler

echo 127.0.0.1   compiler.dev.codebender.cc >> /etc/hosts

cd /opt/codebender/$PACKAGENAME
cp symfony-configs/compiler-parameters.yml Symfony/app/config/parameters.yml

cd Symfony

composer install

chown -R deploy:www-data .

