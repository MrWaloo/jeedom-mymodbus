# post-install script for Jeedom plugin MyModbus
PROGRESS_FILE=/tmp/post-install_mymodbus_in_progress
if [ -n "$1" ]; then
	PROGRESS_FILE="$1"
fi
if [ -d ../../plugins/mymodbus ]; then
  cd ../../plugins/mymodbus
else
  echo "Ce script doit être appelé depuis .../core/data"
  exit
fi
TMP_FILE=/tmp/post-install_mymodbus_bashrc
export PYENV_ROOT="$(realpath ressources)/_pyenv"
PYENV_VERSION="3.9.16"

touch "$PROGRESS_FILE"
echo 0 > "$PROGRESS_FILE"
echo "********************************************************"
echo "*      Nettoyage de l'ancienne version       *"
echo "********************************************************"
date
if [ -f core/php/mymodbus.inc.php ]; then
  rm core/php/mymodbus.inc.php
fi
if [ -f desktop/images/adam_icon.png ]; then
  rm desktop/images/adam_icon.png
fi
if [ -f desktop/images/crouzet_m3_icon.png ]; then
  rm desktop/images/crouzet_m3_icon.png
fi
if [ -f desktop/images/logo_icon.png ]; then
  rm desktop/images/logo_icon.png
fi
if [ -f desktop/images/rtu_icon.png ]; then
  rm desktop/images/rtu_icon.png
fi
if [ -f desktop/images/rtuovertcp_icon.png ]; then
  rm desktop/images/rtuovertcp_icon.png
fi
if [ -f desktop/images/tcpip_icon.png ]; then
  rm desktop/images/tcpip_icon.png
fi
if [ -f desktop/images/wago_icon.png ]; then
  rm desktop/images/wago_icon.png
fi
if [ -f desktop/modal/adam.configuration.php ]; then
  rm desktop/modal/adam.configuration.php
fi
if [ -f desktop/modal/crouzet_m3.configuration.php ]; then
  rm desktop/modal/crouzet_m3.configuration.php
fi
if [ -f desktop/modal/logo.configuration.php ]; then
  rm desktop/modal/logo.configuration.php
fi
if [ -f desktop/modal/rtu.configuration.php ]; then
  rm desktop/modal/rtu.configuration.php
fi
if [ -f desktop/modal/rtuovertcp.configuration.php ]; then
  rm desktop/modal/rtuovertcp.configuration.php
fi
if [ -f desktop/modal/tcpip.configuration.php ]; then
  rm desktop/modal/tcpip.configuration.php
fi
if [ -f desktop/modal/templates.php ]; then
  rm desktop/modal/templates.php
fi
if [ -f desktop/modal/wago.configuration.php ]; then
  rm desktop/modal/wago.configuration.php
fi
if [ -f ressources/Biblio.zip ]; then
  rm ressources/Biblio.zip
fi
if [ -f ressources/demon.py ]; then
  rm ressources/demon.py
fi
if [ -f ressources/global.py ]; then
  rm ressources/global.py
fi
if [ -f ressources/globals.py ]; then
  rm ressources/globals.py
fi
if [ -f ressources/install_apt.sh ]; then
  rm ressources/install_apt.sh
fi
if [ -f ressources/install.sh ]; then
  rm ressources/install.sh
fi
if [ -f ressources/mymodbus_demond.py ]; then
  rm ressources/mymodbus_demond.py
fi
if [ -f ressources/mymodbus_serv.py ]; then
  rm ressources/mymodbus_serv.py
fi
if [ -f ressources/mymodbus_write.py ]; then
  rm ressources/mymodbus_write.py
fi
if [ -d ressources/__pycache__ ]; then
  rm -rf ressources/__pycache__
fi
if [ -d ressources/demond ]; then
  rm -rf ressources/demond
fi
if [ -d ressources/images ]; then
  rm -rf ressources/images
fi
if [ -d ressources/jeedom ]; then
  rm -rf ressources/jeedom
fi
echo 5 > "$PROGRESS_FILE"
echo "********************************************************"
echo "*         Installation de pyenv          *"
echo "********************************************************"
date
if [ ! -d "$PYENV_ROOT" ]; then
  sudo -E -u www-data curl https://pyenv.run | bash
  echo 20 > "$PROGRESS_FILE"
fi
echo "****  Configuration de pyenv..."
grep -vi pyenv ~/.bashrc > "$TMP_FILE"
cat "$TMP_FILE" > ~/.bashrc
cat >> ~/.bashrc<< EOF
export PYENV_ROOT="$PYENV_ROOT"
command -v pyenv >/dev/null || export PATH="\$PYENV_ROOT/bin:\$PATH"
eval "\$(pyenv init -)"
EOF
sudo -E -u www-data grep -vi pyenv ~www-data/.bashrc > "$TMP_FILE"
cat "$TMP_FILE" > ~www-data/.bashrc
sudo -E -u www-data cat >> ~www-data/.bashrc<< EOF
export PYENV_ROOT="$PYENV_ROOT"
command -v pyenv >/dev/null || export PATH="\$PYENV_ROOT/bin:\$PATH"
eval "\$(pyenv init -)"
EOF
echo 30 > "$PROGRESS_FILE"
if [ ! -d "$PYENV_ROOT/versions/$PYENV_VERSION" ]; then
  echo "********************************************************"
  echo "*  Installation de python $PYENV_VERSION (dure longtemps)  *"
  echo "********************************************************"
  date
  chown -R www-data:www-data "$PYENV_ROOT"
  sudo -E -u www-data "$PYENV_ROOT"/bin/pyenv install "$PYENV_VERSION"
fi
echo 95 > "$PROGRESS_FILE"
echo "********************************************************"
echo "*    Configuration de pyenv avec python $PYENV_VERSION     *"
echo "********************************************************"
date
command -v pyenv >/dev/null || export PATH="$PYENV_ROOT/bin:$PATH"
eval "$(pyenv init -)"
cd ressources/mymodbusd
chown -R www-data:www-data "$PYENV_ROOT"
sudo -E -u www-data "$PYENV_ROOT"/bin/pyenv local "$PYENV_VERSION"
sudo -E -u www-data "$PYENV_ROOT"/bin/pyenv exec pip install --upgrade pip setuptools
chown -R www-data:www-data "$PYENV_ROOT"
sudo -E -u www-data "$PYENV_ROOT"/bin/pyenv exec pip install --upgrade requests pyserial pyudev pymodbus
chown -R www-data:www-data "$PYENV_ROOT"
echo 100 > "$PROGRESS_FILE"
rm "$PROGRESS_FILE"
rm "$TMP_FILE"
echo "********************************************************"
echo "*       Installation terminée          *"
echo "********************************************************"
date