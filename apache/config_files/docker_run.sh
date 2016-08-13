#!/bin/bash

download_auto() {
    echo '--------------------------------------------------';
    echo "Downloading prestashop  https://www.prestashop.com/download/old/prestashop_$PS_VERSION.zip";
    curl -# -L https://www.prestashop.com/download/old/prestashop_$PS_VERSION.zip > /tmp/prestashop.zip;

    echo -n '\nUnzip... ';
    (bsdtar -xf /tmp/prestashop.zip -s'|[^/]*/||' -C /var/www/html \
         && mv /var/www/html/install /var/www/html/install-dev && mv /var/www/html/admin /var/www/html/admin-dev \
         && echo 'DONE!') \
        || echo 'FAIL!';

    echo -n 'Cleaning...';
    (rm -f /tmp/prestashop.zip && echo 'DONE!') || echo 'FAIL!';
}

install_auto() {
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

    id -u $SERVER_USER 2>/dev/null 1>/dev/null || useradd -r -g $SERVER_USER www-data
}

ssh_configuration() {
    echo '--------------------------------------------------';
    echo 'id rsa detected';
	  eval `ssh-agent -s`;
    echo 'ssh agent is now running...';
	  ssh-add /home/root/.ssh/id_rsa;
    echo 'Setting up ssh config:';
	  echo 'Host *\n	StrictHostKeyChecking no\n	UserKnownHostsFile=/dev/null' >> /etc/ssh/ssh_config;
	  GIT_CLONE_SSH=1;
}

# Generate a random sixteen-character
# string of alphabetical characters
randname() {
    local -x LC_ALL=C
    tr -dc '[:lower:]' < /dev/urandom |
        dd count=1 bs=16 2>/dev/null
}


run() {
    echo '[+] Run apache as ownership of the webroot'
    local owner group owner_id group_id tmp
    read owner group owner_id group_id < <(stat -c '%U %G %u %g' .)
    if [[ $owner = UNKNOWN ]]; then
        owner=$(randname)
        if [[ $group = UNKNOWN ]]; then
            group=$owner
            addgroup --system --gid "$group_id" "$group"
        fi
        adduser --system --uid=$owner_id --gid=$group_id "$owner"
    fi
    tmp=/tmp/$RANDOM
    {
        echo "User $owner"
        echo "Group $group"
        grep -v '^User' /etc/apache2/apache2.conf |
            grep -v '^Group'
    } >> "$tmp" &&
        cat "$tmp" > /etc/apache2/apache2.conf &&
        rm "$tmp"
    # Not volumes, so need to be chowned
    chown -R "$owner:$group" /var/{lock,log,run}/apache*
    exec /usr/sbin/apache2ctl "$@"
}

init() {
    echo 'init...'

    cat /etc/hosts

    # auto download
    if [ ! -f /var/www/html/config/config.inc.php ]; then
        # Download prestashop.
        if [ $PS_DOWNLOAD_AUTO = 1 ] && [ ! -z $PS_VERSION ]; then
            echo '[+] download auto...'
            download_auto
        fi
    fi

    # ssh configuration
    if [ -f /home/root/.ssh/id_rsa  ]; then
        echo '[+] ssh configuration...'
        ssh_configuration
    fi

    if [ ! -f /var/www/html/config/settings.inc.php ]; then

	      # Auto install
	      if [ $PS_INSTALL_AUTO = 1 ]; then
            echo '[+] install auto...'
            install_auto
	      fi

    fi

    echo '42' > /var/www/html/test36
}

# init sequence
init

# run sequence
run "$@"

echo 'ready!'
