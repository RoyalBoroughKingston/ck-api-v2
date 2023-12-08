# Set base image.
FROM node:16.20

# Set maintainer to Ayup Digital.
LABEL maintainer="Ayup Digital"

# Install git for faster package downloads.
RUN apt-get install -y git

RUN mkdir -p /home/node/app/.npm \
&& chown -R node:node /home/node/app/.npm

ENV npm_config_cache /home/node/app/.npm

# Set the working directory to the project root.
WORKDIR /var/www/html
