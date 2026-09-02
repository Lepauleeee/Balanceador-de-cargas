FROM php:8.2-apache

# Instalar extensiones de MySQL y agregar Python 3 con pyotp para el sistema 2FA
RUN apt-get update && apt-get install -y \
    python3 \
    python3-pyotp \
    && rm -rf /var/lib/apt/lists/* \
    && docker-php-ext-install mysqli && docker-php-ext-enable mysqli
