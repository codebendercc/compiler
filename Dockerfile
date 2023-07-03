#
# Dockerfile for Codebender Compiler
#

FROM --platform=linux/x86_64 ubuntu:22.04

# ARGS (can be passed via CLI)
ARG apache_ini_location=/etc/apache2/apache2.conf

# Below is for apache2 (requiring timezone)
ENV TZ=Universal

RUN ln -snf "/usr/share/zoneinfo/$TZ" /etc/localtime
RUN echo "$TZ" > /etc/timezone

WORKDIR /opt/codebender

# Quality of life tools
RUN apt update && apt install -y \
    acl \
    curl \
    git \
    htop \
    tmux \
    joe \
    vim \
    wget \
    unzip \
    locales

# Apache 2
RUN apt update && apt install -y apache2

# PHP 8.1
RUN apt update && apt install -y \
    php8.1 \
    php8.1-cli \
    php8.1-common \
    php8.1-curl \
    php8.1-gd \
    php8.1-mysql \
    php8.1-xdebug \
    php8.1-intl \
    php-pear \
    libapache2-mod-php8.1

# Legacy: ensure MPM prefork enabled in Apache
RUN a2dismod mpm_event
RUN a2enmod mpm_prefork

# Apache2 modules
RUN a2enmod alias
RUN a2enmod rewrite
RUN a2enmod ssl
RUN a2enmod php8.1

# set bash history to add time stamps
RUN echo 'HISTTIMEFORMAT="%d/%m/%y %T"' >> ~/.bashrc

# From install.sh:
# Ubuntu Server (on AWS?) lacks UTF-8 for some reason. Give it that
RUN locale-gen en_US.UTF-8

RUN sed -i '/date.timezone =/c\date.timezone = UTC' /etc/php/8.1/cli/php.ini
RUN sed -i '/date.timezone =/c\date.timezone = UTC' /etc/php/8.1/apache2/php.ini
# From install.sh:
#### Set Max nesting lvl to something Symfony is happy with
# RUN echo 'xdebug.max_nesting_level=256' | tee $(php -i | grep -F --color=never 'Scan this dir for additional .ini files' | awk '{ print $9}')/symfony2.ini

RUN echo 'xdebug.remote_enable = 1' >> /etc/php/8.1/mods-available/xdebug.ini
RUN echo 'xdebug.remote_autostart = 1' >> /etc/php/8.1/mods-available/xdebug.ini
RUN echo 'xdebug.renite_enable = 1' >> /etc/php/8.1/mods-available/xdebug.ini
RUN echo 'xdebug.max_nesting_level = 1000' >> /etc/php/8.1/mods-available/xdebug.ini
RUN echo 'xdebug.remote_port=9000' >> /etc/php/8.1/mods-available/xdebug.ini
RUN echo 'xdebug.profiler_enable_trigger = 1' >> /etc/php/8.1/mods-available/xdebug.ini
RUN echo 'xdebug.profiler_output_dir = '/var/log'' >> /etc/php/8.1/mods-available/xdebug.ini

# php.ini changes (/etc/php/8.1/apache2/php.ini)
# change php memory limit to unlimited
RUN sed -i 's/^memory_limit.*$/memory_limit = -1/g' /etc/php/8.1/apache2/php.ini
# change error reporting 
RUN sed -i 's/^error_reporting.*$/error_reporting = E_ALL \& ~E_NOTICE \& ~E_STRICT \& ~E_DEPRECATED \& ~E_WARNING/g' /etc/php/8.1/apache2/php.ini
# turn off php exposition
RUN sed -i 's/^expose_php.*$/expose_php = Off/g' /etc/php/8.1/apache2/php.ini

RUN echo $apache_ini_location
# apache2.ini changes (/etc/apache2/apache2.ini)
# Keepalive settings
RUN sed -i 's/^Timeout .*$/Timeout 300/g' $apache_ini_location
RUN sed -i 's/^KeepAlive .*$/KeepAlive On/g' $apache_ini_location
RUN sed -i 's/^MaxKeepAliveRequests .*$/MaxKeepAliveRequests 5000/g' $apache_ini_location
RUN sed -i 's/^KeepAliveTimeout .*$/KeepAliveTimeout 10/g' $apache_ini_location


# We shouldn't run composer as root, so we're creating a new user and running as that one
#RUN useradd -rm -d /home/ubuntu -s /bin/bash -g root -G sudo -u 1001 ubuntu
RUN useradd -rm -d /home/deploy -s /bin/bash -g root -G sudo -u 1001 deploy
#RUN useradd -ms /bin/bash deploy

# copy in apache Vhost configs
COPY ./apache-configs /etc/apache2/sites-available

# TODO: Create proper wildcard ssl cert and add it here.
#COPY ./ssl /etc/apache2/ssl

# enable the sites in apache
RUN a2ensite compiler.dev.codebender.cc

######### TODO #######
# The compiler depended on a tmpfs memory-based filesystem to store the intermediate object files and compile things fast in memory
# That's not currently possible, but it's a TODO for as soon as we can get around to it, and before we actually deploy the compiler
# The following commands don't run inside a docker container. Instead, docker has a tmpfs config https://docs.docker.com/storage/tmpfs/
# The bash commands are:
# echo "tmpfs /mnt/tmp tmpfs rw,nodev,nosuid,size=2G 0 0" | tee -a /etc/fstab
# mount -o
RUN mkdir /mnt/tmp
RUN chown deploy:www-data /mnt/tmp
RUN chmod g+w /mnt/tmp

# From install_dependencies.sh:
WORKDIR /root
RUN wget https://github.com/codebendercc/arduino-core-files/archive/master.zip
RUN unzip -q master.zip
RUN mv arduino-core-files-master /opt/codebender/codebender-arduino-core-files
RUN rm master.zip
RUN wget https://github.com/codebendercc/external_cores/archive/master.zip
RUN unzip -q master.zip
RUN mv external_cores-master /opt/codebender/external-core-files
RUN rm master.zip


# Create the compiler Symfony config
COPY . /opt/codebender/compiler
WORKDIR /opt/codebender/compiler

RUN ./scripts/composer-install.sh
RUN mv composer.phar /usr/local/bin/composer

# TODO: evaluate parameter passing through container build
COPY symfony-configs/compiler-parameters.yml Symfony/config/parameters.yml

RUN chown -R deploy:www-data /opt/codebender/compiler
RUN chmod -R g+w /opt/codebender/compiler

# RUN mkdir -p Symfony/var/cache/
# RUN mkdir -p Symfony/var/log/
# RUN rm -rf Symfony/var/cache/*
# RUN rm -rf Symfony/var/log/*

USER deploy
WORKDIR /opt/codebender/compiler/Symfony
RUN composer install

# set ACL rules to give proper permissions to cache and logs
RUN setfacl -R -m u:www-data:rwX -m u:deploy:rwX var/cache/ var/log/
RUN setfacl -dR -m u:www-data:rwx -m u:deploy:rwx var/cache/ var/log/

# warmup symfony cache
RUN php bin/console cache:warmup --env=prod --no-debug
RUN php bin/console cache:warmup --env=dev
RUN php bin/console cache:warmup --env=test

USER root

# add a record in /etc/hosts for our domain
# finally, start apache in the foreground to keep the container running
#CMD ["apache2ctl", "-DFOREGROUND"]
ENTRYPOINT ["/bin/sh", "-c" , "echo 127.0.0.1   compiler.dev.codebender.cc >> /etc/hosts && apache2ctl -DFOREGROUND" ]
