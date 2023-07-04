FROM 741660149381.dkr.ecr.us-west-2.amazonaws.com/codebender-base-ubuntu-14.04-dev-compiler:latest


RUN mkdir -p /opt/codebender/compiler
RUN rm /opt/codebender/website
RUN ln -s /opt/codebender/compiler /opt/codebender/website

# Create the compiler Symfony config
WORKDIR /opt/codebender
COPY . compiler
WORKDIR /opt/codebender/compiler

# COPY symfony-configs/compiler-parameters.yml Symfony/app/config/parameters.yml
COPY Symfony/app/config/parameters.yml.dist Symfony/app/config/parameters.yml
RUN chown -R deploy:www-data /opt/codebender/compiler
RUN chmod -R g+w /opt/codebender/compiler

RUN mkdir -p Symfony/app/cache/
RUN mkdir -p Symfony/app/logs/
RUN rm -rf Symfony/app/cache/*
RUN rm -rf Symfony/app/logs/*

# # TODO: set ACL rules to give proper permissions to cache and logs
RUN setfacl -R -m u:www-data:rwX -m u:deploy:rwX Symfony/app/cache/ Symfony/app/logs/
RUN setfacl -dR -m u:www-data:rwx -m u:deploy:rwx Symfony/app/cache/ Symfony/app/logs/

WORKDIR /opt/codebender/compiler/Symfony
RUN composer install
# RUN php app/console cache:warmup --env=prod --no-debug
# RUN php app/console cache:warmup --env=dev
# RUN php app/console cache:warmup --env=test
RUN rm -rf Symfony/app/cache/*
RUN rm -rf Symfony/app/logs/*

# add a record in /etc/hosts for our domain
# finally, start apache in the foreground to keep the container running
#CMD ["apache2ctl", "-DFOREGROUND"]
ENTRYPOINT ["/bin/sh", "-c" , "echo 127.0.0.1   compiler.dev.codebender.cc >> /etc/hosts && apache2ctl -DFOREGROUND" ]
