FROM debian:bookworm-slim

ARG PHP_VERSION=${PHP_VERSION:-8.0}
ARG UID=${UID:-1000}
ARG GID=${GID:-1000}

ENV PHP_VERSION=${PHP_VERSION}

# Installing php and extentions
RUN set -eux \
    && groupadd -g ${GID} dev \
    && useradd -u ${UID} -g ${GID} dev \
    && apt-get update \
    && apt-get install --no-install-recommends -yq \
        curl \
        unzip \
        ca-certificates \
    && curl -sSLo /tmp/debsuryorg-archive-keyring.deb https://packages.sury.org/debsuryorg-archive-keyring.deb \
    && dpkg -i /tmp/debsuryorg-archive-keyring.deb \
    && echo "deb [signed-by=/usr/share/keyrings/deb.sury.org-php.gpg] https://packages.sury.org/php/ bookworm main" > /etc/apt/sources.list.d/php.list \
    && apt-get update \
    && apt-get install --no-install-recommends -yq \
        php${PHP_VERSION}-cli \
        php${PHP_VERSION}-common \
        php${PHP_VERSION}-curl \
        php${PHP_VERSION}-dev \
        php${PHP_VERSION}-mbstring \
        php${PHP_VERSION}-mysql \
        php${PHP_VERSION}-opcache \
        php${PHP_VERSION}-phalcon5 \
        php${PHP_VERSION}-psr \
        php${PHP_VERSION}-xdebug \
        php${PHP_VERSION}-xml \
        php${PHP_VERSION}-xsl \
        php${PHP_VERSION}-zip \
    && apt-get autoremove --purge -y curl \
    && apt-get autoclean -y \
    && apt-get clean -y \
    && rm -rf /tmp/* /var/tmp/* \
    && find /var/cache/apt/archives /var/lib/apt/lists -not -name lock -type f -delete \
    && find /var/cache -type f -delete \
    && find /var/log -type f | while read f; do echo -n '' > ${f}; done

COPY --from=composer /usr/bin/composer /usr/bin/composer

WORKDIR /app

USER dev

ENTRYPOINT ["/bin/bash"]
