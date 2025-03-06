FROM php:8.2-apache

# Cria a pasta public dentro de /var/www/html
RUN mkdir -p /var/www/html/public
RUN chown -R www-data:www-data /var/www/html

# Habilita módulos necessários
RUN a2enmod rewrite ssl

# Gera certificado autoassinado simples
RUN mkdir /etc/apache2/ssl && \
    openssl req -x509 -nodes -days 365 -newkey rsa:2048 \
    -keyout /etc/apache2/ssl/server.key \
    -out /etc/apache2/ssl/server.crt \
    -subj "/CN=localhost"

# Instala extensões necessárias
RUN apt-get update && apt-get install -y libzip-dev unzip \
    && docker-php-ext-install pdo pdo_mysql zip

# Copia nova conf SSL
COPY apache/default-ssl.conf /etc/apache2/sites-available/default-ssl.conf
COPY apache/000-default.conf /etc/apache2/sites-available/000-default.conf

# Ativa site SSL
RUN a2ensite default-ssl

# Permite uso de .htaccess
RUN sed -i 's/AllowOverride None/AllowOverride All/g' /etc/apache2/apache2.conf

# Ajusta o timezone
RUN apt-get update && apt-get install -y tzdata \
  && cp /usr/share/zoneinfo/America/Sao_Paulo /etc/localtime \
  && echo "America/Sao_Paulo" > /etc/timezone \
  && apt-get clean && rm -rf /var/lib/apt/lists/*