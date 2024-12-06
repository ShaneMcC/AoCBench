FROM registry.shanemcc.net/public/docker-apache-php-base:latest

RUN \
  apt-get update && \
  apt-get -y install git bash-static wget bsdextrautils moreutils xxd docker.io jq && \
  cd / && \
  wget https://github.com/sharkdp/hyperfine/releases/download/v1.18.0/hyperfine-v1.18.0-x86_64-unknown-linux-musl.tar.gz && \
  tar -xvf hyperfine-v1.18.0-x86_64-unknown-linux-musl.tar.gz && \
  git clone https://github.com/elasticdog/transcrypt.git /transcrypt && \
  cd /transcrypt && \
  git config user.email "aocbench@aocbench.docker.local" && \
  git config user.name "AoCBench" && \
  git cherry-pick 01d79239ce5974b0e8b0fa093557635b69ce1b0a && \
  git cherry-pick 4dca9c2934e63886072ed339f69138295af247cd && \
  ln -s /transcrypt/transcrypt /usr/local/bin/transcrypt && \
  ln -s /hyperfine-v1.18.0-x86_64-unknown-linux-musl/hyperfine /usr/bin/hyperfine

RUN \
  docker-php-source extract && \
  docker-php-ext-install pcntl && \
  docker-php-ext-install sockets && \
  docker-php-source delete

COPY . /aocbench

RUN \
  echo "<?php" > /aocbench/config.local.php && \
  echo "if (file_exists('/aocbench-docker_config.local.php')) {" >> /aocbench/config.local.php && \
  echo "  include('/aocbench-docker_config.local.php');" >> /aocbench/config.local.php && \
  echo "}" >> /aocbench/config.local.php

RUN \
  rm -Rfv /var/www/html && \
  ln -s /aocbench/www /var/www/html && \
  mkdir -p /aocbench/participants /aocbench/results && \
  chown -Rfv www-data: /aocbench/ /var/www/ && \
  su www-data --shell=/bin/bash -c "cd /aocbench; /usr/bin/composer install; "

# Replace ENTRYPOINT
ENTRYPOINT ["/aocbench/docker/entrypoint.sh"]
