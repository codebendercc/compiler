#
# Codebender Base Image for Services
# (Legacy)
# Compiler, Builder, Library Manager (Eratosthenes)
#

FROM --platform=linux/x86_64 ubuntu:14.04

# ARGS (can be passed via CLI)
ARG apache_ini_location=/etc/apache2/apache2.conf

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
    curl \
    acl \
    wget

# Apache 2
RUN apt update && apt install -y apache2

# PHP 5
RUN apt update && apt install -y \
    php5 \
    php5-cli \
    php5-common \
    php5-curl \
    php5-gd \
    php5-mcrypt \
    php5-mysql \
    php5-xdebug \
    php5-sqlite \
    php5-intl \
    php-pear \
    libapache2-mod-php5

# Legacy: ensure MPM prefork enabled in Apache
RUN a2dismod mpm_event
RUN a2enmod mpm_prefork

# Apache2 modules
RUN a2enmod alias
RUN a2enmod rewrite
RUN a2enmod ssl
RUN a2enmod php5

# set bash history to add time stamps
RUN echo 'HISTTIMEFORMAT="%d/%m/%y %T"' >> ~/.bashrc

# php.ini changes (/etc/php5/apache2/php.ini)
# change php memory limit to unlimited
RUN sed -i 's/^memory_limit.*$/memory_limit = -1/g' /etc/php5/apache2/php.ini
# change error reporting 
RUN sed -i 's/^error_reporting.*$/error_reporting = E_ALL \& ~E_NOTICE \& ~E_STRICT \& ~E_DEPRECATED \& ~E_WARNING/g' /etc/php5/apache2/php.ini
# turn off php exposition
RUN sed -i 's/^expose_php.*$/expose_php = Off/g' /etc/php5/apache2/php.ini

RUN echo $apache_ini_location
# apache2.ini changes (/etc/apache2/apache2.ini)
# Keepalive settings
RUN sed -i 's/^Timeout .*$/Timeout 300/g' $apache_ini_location
RUN sed -i 's/^KeepAlive .*$/KeepAlive On/g' $apache_ini_location
RUN sed -i 's/^MaxKeepAliveRequests .*$/MaxKeepAliveRequests 5000/g' $apache_ini_location
RUN sed -i 's/^KeepAliveTimeout .*$/KeepAliveTimeout 10/g' $apache_ini_location


# TODO:
# - user set up
# - pull in each repository to be deployed
# - point apache document root for each virtual host in configuration for each repo

# copy in apache Vhost configs
COPY ./apache-configs /etc/apache2/sites-available

# TODO: Create proper wildcard ssl cert and add it here.
#COPY ./ssl /etc/apache2/ssl

# enable the sites in apache
RUN a2ensite compiler.dev.codebender.cc

# Create the compiler Symfony config
COPY . compiler
WORKDIR /opt/codebender/compiler
RUN ./scripts/composer-install.sh
RUN mv composer.phar /usr/local/bin/composer

# We shouldn't run composer as root, so we're creating a new user and running as that one
#RUN useradd -rm -d /home/ubuntu -s /bin/bash -g root -G sudo -u 1001 ubuntu
RUN useradd -rm -d /home/deploy -s /bin/bash -g root -G sudo -u 1001 deploy
#RUN useradd -ms /bin/bash deploy

#RUN git clone https://github.com/codebendercc/compiler

COPY symfony-configs/compiler-parameters.yml Symfony/app/config/parameters.yml
RUN chown -R deploy:www-data /opt/codebender
RUN chmod -R g+w /opt/codebender

USER deploy
WORKDIR /opt/codebender/compiler/Symfony
RUN composer install

USER root
WORKDIR /opt/codebender/compiler
RUN ./scripts/install.sh
RUN mkdir /mnt/tmp

######### TODO #######
# The compiler depended on a tmpfs memory-based filesystem to store the intermediate object files and compile things fast in memory
# That's not currently possible, but it's a TODO for as soon as we can get around to it, and before we actually deploy the compiler
# The following commands don't run inside a docker container. Instead, docker has a tmpfs config https://docs.docker.com/storage/tmpfs/
# The bash commands are:
# echo "tmpfs /mnt/tmp tmpfs rw,nodev,nosuid,size=2G 0 0" | tee -a /etc/fstab
# mount -o


WORKDIR /opt/codebender/compiler/Symfony

# # TODO: warmup symfony cache
RUN php app/console cache:warmup --env=prod --no-debug

USER root

# # TODO: set ACL rules to give proper permissions to cache and logs
RUN setfacl -R -m u:www-data:rwX -m u:deploy:rwX app/cache/ app/logs/
RUN setfacl -dR -m u:www-data:rwx -m u:deploy:rwx app/cache/ app/logs/

# add a record in /etc/hosts for our domain
# finally, start apache in the foreground to keep the container running
#CMD ["apache2ctl", "-DFOREGROUND"]
ENTRYPOINT ["/bin/sh", "-c" , "echo 127.0.0.1   compiler.dev.codebender.cc >> /etc/hosts && apache2ctl -DFOREGROUND" ]
