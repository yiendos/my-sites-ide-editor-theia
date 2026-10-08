#1.76.0 is Debian 12 (bookworm) - 1.69.0 was Debian 11, whose security packages have gone
FROM ghcr.io/eclipse-theia/theia-ide/theia-ide:1.76.0

USER root

SHELL ["/bin/bash", "-c"]

#PHP 8.4 CLI for composer, laravel new and the PHP/Laravel extensions - sites themselves run on
#the php plugin's fpm container, so no php-fpm here
RUN apt-get update \
    && apt-get install -y --no-install-recommends \
    lsb-release ca-certificates curl gnupg2 build-essential wget unzip tree sqlite3 nano \
    && curl -fsSL https://packages.sury.org/php/apt.gpg \
    | gpg --dearmor -o /etc/apt/trusted.gpg.d/sury-php.gpg \
    && echo "deb https://packages.sury.org/php/ $(lsb_release -sc) main" \
    | tee /etc/apt/sources.list.d/sury-php.list \
    && apt-get update \
    && apt-get install -y --no-install-recommends \
    php8.4-cli php8.4-mysql php8.4-zip php8.4-xml php8.4-gd php8.4-curl php8.4-mbstring \
    php8.4-sqlite3 php8.4-bcmath php8.4-soap php8.4-readline php8.4-intl php8.4-common \
    && HASH=$(curl -sS https://composer.github.io/installer.sig) \
    && curl -sS https://getcomposer.org/installer -o /tmp/composer-setup.php \
    && php -r "if (hash_file('SHA384', '/tmp/composer-setup.php') !== '$HASH') { echo 'Installer corrupt'; unlink('/tmp/composer-setup.php'); exit(1); } echo 'Installer verified';" \
    && php /tmp/composer-setup.php --install-dir=/usr/local/bin --filename=composer \
    && rm /tmp/composer-setup.php \
    && rm -rf /var/lib/apt/lists/* \
    && mkdir -p /home/theia/.config/psysh \
    && chown -R theia:theia /home/theia/.config

#the Laravel installer goes in the image, not COMPOSER_HOME - that's the plugin's storage at
#runtime, which would otherwise hide it
RUN COMPOSER_HOME=/usr/local/share/composer \
    composer global require laravel/installer --no-interaction \
    && rm -rf /usr/local/share/composer/cache

USER theia

#Claude Code CLI → /home/theia/.local/bin
RUN curl -fsSL https://claude.ai/install.sh | bash

ENV PATH="/home/theia/.local/bin:/usr/local/share/composer/vendor/bin:${PATH}"

#extensions aren't baked in - editor:theia-extensions unpacks them into the plugin's storage,
#which Theia loads on start
