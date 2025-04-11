# Use an official PHP image with Apache
FROM php:8.1-apache

# Install dependencies for Python and Supervisor
RUN apt-get update && apt-get install -y \
    python3 \
    python3-pip \
    supervisor \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# Enable Apache modules for proxying
RUN a2enmod proxy proxy_http

# Copy your PHP and Python code into the container.
# For example, assume your project root contains the PHP files in the web root.
COPY . /var/www/html/

# Copy the custom Apache configuration file to override the default.
# This should happen after the base image and module installations,
# so that your custom configuration is in place before Apache starts.
COPY 000-default.conf /etc/apache2/sites-available/000-default.conf

# Install Python dependencies; ensure you have a requirements.txt in your project.
COPY requirements.txt /tmp/requirements.txt
RUN pip3 install -r /tmp/requirements.txt

# Copy Supervisor config file into the container (we’ll create this next)
COPY supervisord.conf /etc/supervisor/conf.d/supervisord.conf

# Expose the port used by Railway – use Apache’s default port (80)
EXPOSE 80

# Set the entrypoint to run Supervisor
CMD ["/usr/bin/supervisord", "-n"]
