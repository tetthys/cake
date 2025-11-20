# PHP 8.3 CLI base image
FROM php:8.3-cli

# Install basic tools (git, unzip, zip, bash, CA certificates)
RUN apt-get update \
 && apt-get install -y --no-install-recommends git unzip zip bash ca-certificates \
 && rm -rf /var/lib/apt/lists/*

# Install Composer with signature verification (as requested)
WORKDIR /usr/local/bin

RUN php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');" \
&& php -r "if (hash_file('sha384', 'composer-setup.php') === 'c8b085408188070d5f52bcfe4ecfbee5f727afa458b2573b8eaaf77b3419b0bf2768dc67c86944da1544f06fa544fd47') { echo 'Installer verified'.PHP_EOL; } else { echo 'Installer corrupt'.PHP_EOL; unlink('composer-setup.php'); exit(1); }" \
&& php composer-setup.php --install-dir=/usr/local/bin --filename=composer \
&& php -r "unlink('composer-setup.php');"

# Default working directory for your project (mounted via docker-compose)
WORKDIR /app