#!/bin/bash

cd $1

touch nodep

echo "Start of dep installation"

if [ ! -d "/var/www" ]; then
  # Control will enter here if $DIRECTORY doesn't exist.
  sudo mkdir /var/www

fi

sudo chown www-data:www-data /var/www

sudo rm -rf node_modules
npm install
npm install request
npm install imap
npm install mailparser
npm install async

rm -rf attachments
mkdir attachments

rm nodep

echo "End of dep installation"
