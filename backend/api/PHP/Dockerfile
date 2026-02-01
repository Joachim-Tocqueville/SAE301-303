FROM php:8.2-apache

# Installation des extensions nécessaires pour PostgreSQL (Supabase)
RUN apt-get update && apt-get install -y libpq-dev \
    && docker-php-ext-install pdo pdo_pgsql

# Activation du module rewrite d'Apache (souvent utile en PHP)
RUN a2enmod rewrite

# Copie de tout le dossier backend dans le serveur
COPY . /var/www/html/

# On donne les droits au serveur web
RUN chown -R www-data:www-data /var/www/html

EXPOSE 80