FROM php:8-fpm

# Copy composer.lock and composer.json
COPY composer.lock composer.json /var/www/

# Set working directory
WORKDIR /var/www

# Install dependencies
RUN apt-get update && apt-get install -y \
    build-essential \
    libpng-dev \
    libjpeg62-turbo-dev \
    libfreetype6-dev \
    locales \
    zip \
    jpegoptim optipng pngquant gifsicle \
    vim \
    unzip \
    git \
    curl \
    supervisor \
    redis-server \
    cron \
    sudo \
    mailutils \
    libzip-dev

# Add crontab file in the cron directory
COPY cron/ulizaln-cron /etc/cron.d/ulizaln-cron

# Clear cache
RUN apt-get clean && rm -rf /var/lib/apt/lists/*

# Install extensions
#RUN docker-php-ext-install mbstring pdo_mysql zip exif pcntl
#RUN docker-php-ext-install mbstring
RUN docker-php-ext-install pdo_mysql
RUN docker-php-ext-install zip
RUN docker-php-ext-install exif
RUN docker-php-ext-install pcntl
#RUN docker-php-ext-configure gd --with-gd --with-freetype-dir=/usr/include/ --with-jpeg-dir=/usr/include/ --with-png-dir=/usr/include/
RUN docker-php-ext-install gd
RUN pecl install redis && docker-php-ext-enable redis

# Install composer
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

# Give execution rights on the cron job
RUN chmod 0644 /etc/cron.d/ulizaln-cron

# Apply cron job
RUN crontab /etc/cron.d/ulizaln-cron

# Create the log file to be able to run tail
RUN touch /var/log/cron.log

# Copy existing application directory contents
COPY . /var/www

#copy supervisord configs
COPY ./supervisor/supervisord.conf /etc/supervisor/supervisord.conf
COPY ./supervisor/lara-app.conf /etc/supervisor/conf.d/lara-app.conf

#start supervisor
CMD ["/usr/bin/supervisord", "-c", "/etc/supervisor/supervisord.conf"]

# Run the command on container startup
CMD cron && tail -f /var/log/cron.log

# Change current user to www
#USER www

# Expose port 9000 and start php-fpm server
EXPOSE 9000
CMD ["php-fpm"]
