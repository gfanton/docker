FROM php:5.6-apache

MAINTAINER Guilhem Fanton <guilhem.fanton@gmail.com>

ENV PS_DOMAIN prestashop.local

ENV PS_VERSION 1.6.1.5
ENV DB_SERVER localhost
ENV DB_NAME prestashop
ENV DB_USER root
ENV DB_PASSWD admin

ENV ADMIN_MAIL demo@prestashop.com
ENV ADMIN_PASSWD 12345678

ENV PS_LANGUAGE en
ENV PS_COUNTRY gb
ENV PS_DOWNLOAD_AUTO 0
ENV PS_INSTALL_AUTO 0
ENV PS_DEV_MODE 0
ENV PS_HOST_MODE 0
ENV PS_HANDLE_DYNAMIC_DOMAIN 1
ENV PS_HOST localhost

ENV DEBIAN_FRONTEND noninteractive

RUN mkdir -p /var/www/html/
RUN mkdir -p /scripts/
RUN mkdir -p /cache

# Add symbolic link
RUN ln -s /var/www/html /html

RUN apt-get update && apt-get install -y \
            libmcrypt-dev \
            libcurl4-gnutls-dev \
            libjpeg62-turbo-dev \
            libpng12-dev \
            libfreetype6-dev \
            libxml2-dev \
            mysql-client \
            wget \
            unzip \
            git \
            ssh \
            bsdtar \
            libxslt-dev \
            && docker-php-ext-configure gd --with-freetype-dir=/usr/include/ --with-jpeg-dir=/usr/include/ \
            && docker-php-ext-install iconv mcrypt pdo mysql pdo_mysql mbstring soap gd zip bcmath xsl

COPY ./config_files/docker_updt_ps_domains.php /var/www/html/
COPY ./config_files/xdebug-enabler.ini /etc/php5/mods-available/
COPY ./config_files/docker_run /usr/local/bin/
COPY ./config_files/php.ini /usr/local/etc/php/

# Apache configuration
RUN a2enmod rewrite

EXPOSE 80
EXPOSE 443

WORKDIR /var/www/html

ENTRYPOINT ["docker_run"]
CMD ["-DFOREGROUND"]
