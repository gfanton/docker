#!/bin/sh

# Avoid double install.
if [ ! -f /var/www/html/config/settings.inc.php  ]; then

    # Download prestashop.
    if [ ! -z $PS_VERSION ]; then
        echo '--------------------------------------------------';
        echo "Downloading prestashop  https://www.prestashop.com/download/old/prestashop_$PS_VERSION.zip";
        curl -# -L https://www.prestashop.com/download/old/prestashop_$PS_VERSION.zip > /tmp/prestashop.zip;

        echo -n '\nUnzip... ';
        (bsdtar -xf /tmp/prestashop.zip -s'|[^/]*/||' -C /var/www/html \
             && mv /var/www/html/install /var/www/html/install-dev && mv /var/www/html/admin /var/www/html/admin-dev \
             && chown www-data:www-data -R /var/www/html/ \
             && echo 'DONE!') \
            || echo 'FAIL!';

        echo -n 'Cleaning...';
        (rm -f /tmp/prestashop.zip && echo 'DONE!') || echo 'FAIL!';
    else
        echo 'PS_VERSION undefined'
        exit 1
    fi
fi

# Grant mysql perm.
if [ $DB_SERVER = "localhost" ] || [ $DB_SERVER = "127.0.0.1" ]; then
    echo '--------------------------------------------------';
	  echo "\n* Starting internal MySQL server ...";
	  service mysql start;
	  if [ $DB_PASSWD != "" ] && [ ! ! -f /var/www/html/config/settings.inc.php  ]; then
		    echo "\n* Grant access to MySQL server ...";
		    mysql -h $DB_SERVER -u $DB_USER -p$DB_PASSWD --execute="GRANT ALL ON *.* to $DB_USER@'localhost' IDENTIFIED BY '$DB_PASSWD'; " 2> /dev/null;
		    mysql -h $DB_SERVER -u $DB_USER -p$DB_PASSWD --execute="GRANT ALL ON *.* to $DB_USER@'%' IDENTIFIED BY '$DB_PASSWD'; " 2> /dev/null;
		    mysql -h $DB_SERVER -u $DB_USER -p$DB_PASSWD --execute="flush privileges; " 2> /dev/null;
	  fi
fi


# ssh configuration
if [ -f /home/root/.ssh/id_rsa  ]; then
    echo '--------------------------------------------------';
    echo 'id rsa detected';
	  eval `ssh-agent -s`;
    echo 'ssh agent is now running...';
	  ssh-add /home/root/.ssh/id_rsa;
    echo 'Setting up ssh config:';
	  echo 'Host *\n	StrictHostKeyChecking no\n	UserKnownHostsFile=/dev/null' >> /etc/ssh/ssh_config;
	  GIT_CLONE_SSH=1;
fi

# Avoid double install.
if [ ! -f /var/www/html/config/settings.inc.php  ]; then

    echo '--------------------------------------------------';
	  if [ $PS_DEV_MODE -ne 0 ]; then
		    #echo "Set DEV MODE > true";
		    sed -ie "s/define('_PS_MODE_DEV_', false);/define('_PS_MODE_DEV_',\ true);/g" /var/www/html/config/defines.inc.php
	  fi

	  if [ $PS_HOST_MODE -ne 0 ]; then
		    #echo "Set HOST MODE > true";
		    echo "define('_PS_HOST_MODE_', true);" >> /var/www/html/config/defines.inc.php
	  fi

	  if [ $PS_HANDLE_DYNAMIC_DOMAIN = 0 ]; then
		    rm /var/www/html/docker_updt_ps_domains.php
	  else
		    sed -ie "s/DirectoryIndex\ index.php\ index.html/DirectoryIndex\ docker_updt_ps_domains.php\ index.php\ index.html/g" /etc/apache2/apache2.conf
	  fi

	  # Auto install
	  if [ $PS_INSTALL_AUTO = 1 ]; then
		    echo "\n* Installing PrestaShop, this may take a while ...";
		    if [ $DB_PASSWD = "" ]; then
			      mysqladmin -h $DB_SERVER -u $DB_USER drop $DB_NAME --force 2> /dev/null;
			      mysqladmin -h $DB_SERVER -u $DB_USER create $DB_NAME --force 2> /dev/null;
		    else
			      mysqladmin -h $DB_SERVER -u $DB_USER -p$DB_PASSWD drop $DB_NAME --force 2> /dev/null;
			      mysqladmin -h $DB_SERVER -u $DB_USER -p$DB_PASSWD create $DB_NAME --force 2> /dev/null;
		    fi

		    php /var/www/html/install-dev/index_cli.php --domain=$PS_HOST --db_server=$DB_SERVER --db_name="$DB_NAME" --db_user=$DB_USER \
			      --db_password=$DB_PASSWD --firstname="John" --lastname="Doe" \
			      --password=$ADMIN_PASSWD --email="$ADMIN_MAIL" --language=$PS_LANGUAGE --country=$PS_COUNTRY \
			      --newsletter=0 --send_email=0 \
            --http-host=$PS_HOST

		    chown www-data:www-data -R /var/www/html/
	  fi
fi

echo "\n* Almost ! Starting Apache now\n";
/usr/sbin/apache2ctl -D FOREGROUND
echo "\n* Done!!\n"
