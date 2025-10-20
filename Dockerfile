# PHP 8.3 CLI base image
FROM php:8.3-cli

# Install basic tools (git, unzip, zip, bash, CA certificates)
RUN apt-get update \
 && apt-get install -y --no-install-recommends git unzip zip bash ca-certificates \
 && rm -rf /var/lib/apt/lists/*

# Install Composer with signature verification (as requested)
WORKDIR /usr/local/bin
RUN php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');" \
 && php -r "if (hash_file('sha384', 'composer-setup.php') === 'ed0feb545ba87161262f2d45a633e34f591ebb3381f2e0063c345ebea4d228dd0043083717770234ec00c5a9f9593792') { echo 'Installer verified'.PHP_EOL; } else { echo 'Installer corrupt'.PHP_EOL; unlink('composer-setup.php'); exit(1); }" \
 && php composer-setup.php --install-dir=/usr/local/bin --filename=composer \
 && php -r "unlink('composer-setup.php');"

# Default working directory for your project (mounted via docker-compose)
WORKDIR /app