FROM php:8.3-apache

WORKDIR /app

COPY Docker/php.ini /usr/local/etc/php/conf.d/adlaire.ini
COPY Docker/entrypoint.sh /usr/local/bin/adlaire-entrypoint

RUN chmod +x /usr/local/bin/adlaire-entrypoint

ENV ADLAIRE_SQLITE_PATH=/data/adlaire.sqlite

EXPOSE 80

ENTRYPOINT ["adlaire-entrypoint"]
CMD ["apache2-foreground"]
