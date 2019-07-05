FROM php:7.2-cli

COPY --from=composer:1.8 /usr/bin/composer /usr/bin/composer

RUN apt-get update && apt-get install -y \
    git \
    mc
RUN apt-get clean && rm -rf /var/lib/apt/lists/* /tmp/* /var/tmp/*

### https://jtreminio.com/blog/running-docker-containers-as-current-host-user/

ARG USER_ID
ARG GROUP_ID

RUN if [ ${USER_ID:-0} -ne 0 ] && [ ${GROUP_ID:-0} -ne 0 ]; then \
    if id www-data >/dev/null 2>&1 ; then userdel --force www-data; fi && \
    if getent group www-data ; then groupdel www-data; fi && \
    groupadd --gid ${GROUP_ID} www-data && \
    useradd --no-log-init --create-home --shell /bin/bash --uid ${USER_ID} --gid www-data www-data ; \
else \
    echo "\033[1;93;41m The USER_ID and GROUP_ID arguments are required..\033[0m" ; \
    exit 1 \
;fi

USER www-data

RUN composer global require hirak/prestissimo --no-plugins --no-scripts

WORKDIR ${WORKDIR:-/project}
