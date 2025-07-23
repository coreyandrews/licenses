# Use an official PHP image with Apache
FROM php:8.2-apache

# Install necessary system packages for SQLite development headers
RUN apt-get update && apt-get install -y libsqlite3-dev \
    && rm -rf /var/lib/apt/lists/*

# Install PDO and PDO SQLite extensions
RUN docker-php-ext-install pdo pdo_sqlite

# Set the working directory inside the container
WORKDIR /var/www/html

# Copy the custom php.ini file into the PHP configuration directory
COPY php.ini /usr/local/etc/php/conf.d/php.ini

# Copy the application files from your local directory to the container's web root
COPY index.php .
COPY history.php . 
COPY upload_license.php . 

# Copy the logo.png file to the web root for iOS app icon
COPY logo.png .

# Create directories for data and uploaded documents
RUN mkdir -p /var/www/html/data /var/www/html/uploads

# Set appropriate permissions for the entire web root and its contents
RUN chown -R www-data:www-data /var/www/html
RUN chmod -R 755 /var/www/html # Read and execute for others, write for owner

# Allow group write for the volume-mounted directories (data and uploads)
RUN chmod -R 775 /var/www/html/data /var/www/html/uploads

# Expose port 80, which Apache listens on by default
EXPOSE 80
