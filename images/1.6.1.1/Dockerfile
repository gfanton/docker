FROM php:5.6-apache

MAINTAINER Thomas Nabord <thomas.nabord@prestashop.com

ENV PS_VERSION 1.6.1.1

ENV PS_DOMAIN prestashop.local

ENV DB_SERVER 127.0.0.1
ENV DB_NAME prestashop
ENV DB_USER root
ENV DB_PASSWD admin

ENV ADMIN_MAIL demo@prestashop.com
ENV ADMIN_PASSWD prestashop_demo

ENV PS_LANGUAGE en
ENV PS_COUNTRY gb
ENV PS_INSTALL_AUTO 0
ENV PS_DEV_MODE 0
ENV PS_HOST_MODE 0
ENV PS_HANDLE_DYNAMIC_DOMAIN 0

ENV GIT_CLONE_SSH 0
ENV GIT_MODULES ":"
ENV GIT_OPERATIONS "install:uninstall:install"
ENV GIT_USER ":"


# Avoid MySQL questions during installation
ENV DEBIAN_FRONTEND noninteractive
RUN echo mysql-server-5.6 mysql-server/root_password password $DB_PASSWD | debconf-set-selections
RUN echo mysql-server-5.6 mysql-server/root_password_again password $DB_PASSWD | debconf-set-selections

RUN apt-get update \
	&& apt-get install -y libmcrypt-dev \
		libjpeg62-turbo-dev \
		libpng12-dev \
		libfreetype6-dev \
		libxml2-dev \
		mysql-client \
		mysql-server \
		wget \
		unzip \
		git \
		ssh \
    && docker-php-ext-configure gd --with-freetype-dir=/usr/include/ --with-jpeg-dir=/usr/include/ \
    && docker-php-ext-install iconv mcrypt pdo mysql pdo_mysql mbstring soap gd

# Get PrestaShop
ADD https://www.prestashop.com/download/old/prestashop_1.6.1.1.zip /tmp/prestashop.zip
RUN unzip -q /tmp/prestashop.zip -d /tmp/
RUN cp -R /tmp/prestashop/* /var/www/html
RUN mv /var/www/html/install /var/www/html/install-dev
RUN mv /var/www/html/admin /var/www/html/admin-dev
COPY config_files/docker_updt_ps_domains.php /var/www/html/

# git config
RUN git config --global advice.implicitIdentity false
RUN git config --global user.email $ADMIN_MAIL
ADD config_files/.gitconfig /home/root/

# Apache configuration
RUN a2enmod rewrite
RUN chown www-data:www-data -R /var/www/html/

# Add symbolic link
RUN ln -s /var/www/html /html

# PHP configuration
COPY config_files/php.ini /usr/local/etc/php/

# MySQL configuration
RUN sed -i -e"s/^bind-address\s*=\s*127.0.0.1/bind-address = 0.0.0.0/" /etc/mysql/my.cnf
EXPOSE 3306

VOLUME /var/www/html/modules
VOLUME /var/www/html/themes
VOLUME /var/www/html/override
VOLUME /home/root/.ssh

COPY config_files/docker_run.sh /tmp/
COPY config_files/module_install.php /tmp/
ENTRYPOINT ["/tmp/docker_run.sh"]
