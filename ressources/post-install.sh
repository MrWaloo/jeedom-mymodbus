# post-install script for Jeedom plugin MyModbus
PROGRESS_FILE=/tmp/jeedom_install_in_progress_mymodbus
if [ -n "$1" ]; then
	PROGRESS_FILE="$1"
fi
if [ -d ../../plugins/mymodbus ]; then
  cd ../../plugins/mymodbus
else
  echo "Ce script doit être appelé depuis .../core/data"
  exit
fi

touch "$PROGRESS_FILE"
echo 0 > "$PROGRESS_FILE"
echo "********************************************************"
echo "*           Nettoyage de l'ancienne version            *"
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
if [ -f desktop/modal/wago.configuration.php ]; then
  rm desktop/modal/wago.configuration.php
fi
if [ -f desktop/modal/configuration.serial.php ]; then
  rm desktop/modal/configuration.serial.php
fi
if [ -f desktop/modal/configuration.tcp.php ]; then
  rm desktop/modal/configuration.tcp.php
fi
if [ -f desktop/modal/configuration.udp.php ]; then
  rm desktop/modal/configuration.udp.php
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
if [ -d ressources/mymodbusd/__pycache__ ]; then
  rm -rf ressources/mymodbusd/__pycache__
fi
if [ -d ressources/mymodbusd/jeedom/__pycache__ ]; then
  rm -rf ressources/mymodbusd/jeedom/__pycache__
fi
if [ -d ressources/_pyenv ]; then
  rm -rf ressources/_pyenv
fi
echo 100 > "$PROGRESS_FILE"
echo "********************************************************"
echo "*           Installation terminée                      *"
echo "********************************************************"
date