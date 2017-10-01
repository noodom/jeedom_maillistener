#!/bin/bash
cd $1
touch /tmp/maillistener_dep
echo "Début de l'installation"

echo 0 > /tmp/maillistener_dep
DIRECTORY="/var/www"
if [ ! -d "$DIRECTORY" ]; then
  echo "Création du home www-data pour npm"
  sudo mkdir $DIRECTORY
fi
sudo chown -R www-data $DIRECTORY
echo 10 > /tmp/maillistener_dep
actual=`nodejs -v | awk -F v '{ print $2 }'`;
echo "Version actuelle : ${actual}"

if [[ $actual -g 4 ]]
then
  echo "Ok, version suffisante";
else
  echo "KO, version obsolète à upgrader";
  echo "Suppression du Nodejs existant et installation du paquet recommandé"
  sudo apt-get -y --purge autoremove nodejs npm
  arch=`arch`;
  echo 30 > /tmp/maillistener_dep
  if [[ $arch == "armv6l" ]]
  then
    echo "Raspberry 1 détecté, utilisation du paquet pour armv6"
    sudo rm /etc/apt/sources.list.d/nodesource.list
    wget http://node-arm.herokuapp.com/node_latest_armhf.deb
    sudo dpkg -i node_latest_armhf.deb
    sudo ln -s /usr/local/bin/node /usr/local/bin/nodejs
    rm node_latest_armhf.deb
  else
    echo "Utilisation du dépot officiel"
    curl -sL https://deb.nodesource.com/setup_8.x | sudo -E bash -
    sudo apt-get install -y nodejs
  fi
  new=`nodejs -v`;
  echo "Version actuelle : ${new}"
fi

echo 70 > /tmp/maillistener_dep

npm cache clean
sudo npm cache clean
sudo rm -rf node_modules

echo 80 > /tmp/maillistener_dep
npm install
npm install request
echo 83 > /tmp/maillistener_dep
npm install imap
echo 85 > /tmp/maillistener_dep
npm install mailparser
echo 88 > /tmp/maillistener_dep
npm install async
echo 90 > /tmp/maillistener_dep

rm -rf attachments
mkdir attachments

sudo chown -R www-data node_modules

rm /tmp/maillistener_dep

echo "Fin de l'installation"
