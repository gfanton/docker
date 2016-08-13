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

RUN apt-get update \
	&& apt-get install -y libmcrypt-dev \
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
    && docker-php-ext-configure gd --with-freetype-dir=/usr/include/ --with-jpeg-dir=/usr/include/ \
    && docker-php-ext-install iconv mcrypt pdo mysql pdo_mysql mbstring soap gd

COPY ./config_files/docker_updt_ps_domains.php /var/www/html/

# Apache configuration
RUN a2enmod rewrite
RUN mkdir -p /var/www/html/
RUN mkdir -p /scripts/

# Add symbolic link
RUN ln -s /var/www/html /html

# PHP configuration
COPY ./config_files/php.ini /usr/local/etc/php/

EXPOSE 80
EXPOSE 443

COPY ./config_files/docker_run.sh /usr/local/bin/

CMD ["docker_run.sh", "-DFOREGROUND"]
WORKDIR /var/www/html
