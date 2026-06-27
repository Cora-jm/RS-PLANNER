FROM php:8.2-apache

# Dépendances SQLite + PDO
RUN apt-get update && apt-get install -y \
    libsqlite3-dev \
    && docker-php-ext-install pdo pdo_sqlite \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# Module rewrite Apache
RUN a2enmod rewrite && \
    sed -i 's/AllowOverride None/AllowOverride All/g' /etc/apache2/apache2.conf

WORKDIR /var/www/html

# Copier le code backend
COPY . .

# Créer les dossiers persistants si absents
RUN mkdir -p /var/www/html/data \
             /var/www/html/uploads \
             /var/www/html/logs

# Initialiser la base SQLite si elle n'existe pas
RUN if [ ! -f /var/www/html/data/rs_planner.db ]; then \
      sqlite3 /var/www/html/data/rs_planner.db < /var/www/html/schema.sql; \
    fi

# Permissions Apache
RUN chown -R www-data:www-data /var/www/html

EXPOSE 80