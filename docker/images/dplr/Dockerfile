ARG PHP_VERSION=7.4
FROM golang:alpine AS gossha

RUN apk --update --no-cache add --virtual build-dependencies \
    git make \
    && go install github.com/YuriyNasretdinov/GoSSHa@latest \
    && apk update && apk del build-dependencies

FROM php:${PHP_VERSION}-alpine

COPY --from=gossha /go/bin/GoSSHa /usr/local/bin/GoSSHa

ENV LANG "C.UTF-8"
ENV LANGUAGE "C.UTF-8"
ENV LC_ALL "C.UTF-8"

RUN echo http://dl-2.alpinelinux.org/alpine/edge/community/ >> /etc/apk/repositories \
    && apk update \
    && apk add --virtual build-deps --no-cache shadow \
    && apk add --no-cache --upgrade grep git openssh-client ca-certificates \
    && apk del build-deps

COPY key /root/.ssh/id_rsa

RUN chmod 700 /root/.ssh \
    && echo -e "Host *\n\tStrictHostKeyChecking no\n\n" > /root/.ssh/config \
    && chmod 600 /root/.ssh/id_rsa

RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

ENV PATH /var/www/dplr:$PATH

COPY php.ini /usr/local/etc/php/conf.d/prod.ini

WORKDIR /var/www/dplr
