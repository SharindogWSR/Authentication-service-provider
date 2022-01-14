FROM ubuntu/apache2:latest as server
ENV TZ=Asia/Yekaterinburg
RUN apt update -y --force-yes
RUN apt upgrade -y --force-yes
RUN apt install -y --force-yes php php-gd php-mbstring php-xml php-json php-pdo php-mysqli composer
RUN rm -rf /etc/apache2/sites-available/000-default.conf /var/www/html/*
COPY ./html/ /var/www/html/
COPY ./000-default.conf /etc/apache2/sites-available/
RUN a2enmod rewrite
