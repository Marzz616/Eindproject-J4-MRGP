# =============================================================
#  Voedselbank Maaskantje — één image voor frontend én backend
#
#  Stap 1 (node):   bouwt de Vue-app naar statische bestanden (dist/)
#  Stap 2 (apache): PHP-backend + de gebouwde frontend
#     /            -> Vue-app (frontend/dist)
#     /api/...     -> backend/public/index.php (front controller)
# =============================================================

# ---------- stap 1: Vue-frontend bouwen ----------
FROM node:22-alpine AS frontend
WORKDIR /app
COPY frontend/package*.json ./
RUN npm ci --no-audit --no-fund
COPY frontend/ ./
RUN npm run build

# ---------- stap 2: PHP + Apache ----------
FROM php:8.3-apache

# PDO MySQL-driver (zonder deze extensie kan PHP niet met MySQL praten)
RUN docker-php-ext-install pdo_mysql \
 && a2enmod rewrite headers

# Nederlandse tijd, zodat "vandaag" in de app dezelfde dag is als bij de medewerker
ENV TZ=Europe/Amsterdam
RUN printf 'date.timezone = Europe/Amsterdam\nexpose_php = Off\n' > /usr/local/etc/php/conf.d/voedselbank.ini

COPY docker/apache.conf /etc/apache2/sites-available/000-default.conf
COPY backend/ /var/www/backend/
COPY --from=frontend /app/dist/ /var/www/html/
