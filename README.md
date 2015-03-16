This is the fork of the store.php script out of the bidix tiddlyhome project.
It changes some of the more 'questionable' code (security-wise) in the store.php script.

# Installation


    apt-get install php5-common php5-cli php5-fpm  nginx
    cp nginx/default.conf /etc/nginx/sites-available/wiki.conf
    # edit the nginx file to represent your environment
    ln -s /etc/nginx/sites-available/wiki.conf /etc/nginx/sites-enabled/wiki.conf
    mkdir -p /var/www/wiki/backup
    chown www-data:www-data -R /var/www/wiki/

    # edit store.php to represent your environment
    cp store.php /var/www/wiki
    chown root:root /var/www/wiki/store.php
    chmod 755 /var/www/wiki/store.php

# Configure Tiddlywiki

see https://excogitation.de/wiki/#Tiddlywiki%20saving%20with%20php%20script

