#!/usr/bin/bash
PROGRESS_FILE=/tmp/post-install_mymodbus_in_progress
if [ ! -z $1 ]; then
	PROGRESS_FILE=$1
fi
# Version of python into pyenv to install
PYENV_VERSION="3.9.16"

touch "$PROGRESS_FILE"
echo 0 > "$PROGRESS_FILE"
echo "********************************************************"
echo "*            Nettoyage de l'ancienne version           *"
echo "********************************************************"
if [ -f ../../plugins/mymodbus/core/php/mymodbus.inc.php ]; then
    rm ../../plugins/mymodbus/core/php/mymodbus.inc.php
fi
if [ -f ../../plugins/mymodbus/desktop/images/adam_icon.png ]; then
    rm ../../plugins/mymodbus/desktop/images/adam_icon.png
fi
if [ -f ../../plugins/mymodbus/desktop/images/crouzet_m3_icon.png ]; then
    rm ../../plugins/mymodbus/desktop/images/crouzet_m3_icon.png
fi
if [ -f ../../plugins/mymodbus/desktop/images/logo_icon.png ]; then
    rm ../../plugins/mymodbus/desktop/images/logo_icon.png
fi
if [ -f ../../plugins/mymodbus/desktop/images/rtu_icon.png ]; then
    rm ../../plugins/mymodbus/desktop/images/rtu_icon.png
fi
if [ -f ../../plugins/mymodbus/desktop/images/rtuovertcp_icon.png ]; then
    rm ../../plugins/mymodbus/desktop/images/rtuovertcp_icon.png
fi
if [ -f ../../plugins/mymodbus/desktop/images/tcpip_icon.png ]; then
    rm ../../plugins/mymodbus/desktop/images/tcpip_icon.png
fi
if [ -f ../../plugins/mymodbus/desktop/images/wago_icon.png ]; then
    rm ../../plugins/mymodbus/desktop/images/wago_icon.png
fi
if [ -f ../../plugins/mymodbus/desktop/modal/adam.configuration.php ]; then
    rm ../../plugins/mymodbus/desktop/modal/adam.configuration.php
fi
if [ -f ../../plugins/mymodbus/desktop/modal/crouzet_m3.configuration.php ]; then
    rm ../../plugins/mymodbus/desktop/modal/crouzet_m3.configuration.php
fi
if [ -f ../../plugins/mymodbus/desktop/modal/logo.configuration.php ]; then
    rm ../../plugins/mymodbus/desktop/modal/logo.configuration.php
fi
if [ -f ../../plugins/mymodbus/desktop/modal/rtu.configuration.php ]; then
    rm ../../plugins/mymodbus/desktop/modal/rtu.configuration.php
fi
if [ -f ../../plugins/mymodbus/desktop/modal/rtuovertcp.configuration.php ]; then
    rm ../../plugins/mymodbus/desktop/modal/rtuovertcp.configuration.php
fi
if [ -f ../../plugins/mymodbus/desktop/modal/tcpip.configuration.php ]; then
    rm ../../plugins/mymodbus/desktop/modal/tcpip.configuration.php
fi
if [ -f ../../plugins/mymodbus/desktop/modal/templates.php ]; then
    rm ../../plugins/mymodbus/desktop/modal/templates.php
fi
if [ -f ../../plugins/mymodbus/desktop/modal/wago.configuration.php ]; then
    rm ../../plugins/mymodbus/desktop/modal/wago.configuration.php
fi
if [ -f ../../plugins/mymodbus/ressources/global.py ]; then
    rm ../../plugins/mymodbus/ressources/global.py
fi
if [ -f ../../plugins/mymodbus/ressources/install_apt.sh ]; then
    rm ../../plugins/mymodbus/ressources/install_apt.sh
fi
if [ -f ../../plugins/mymodbus/ressources/mymodbus_demond.py ]; then
    rm ../../plugins/mymodbus/ressources/mymodbus_demond.py
fi
if [ -f ../../plugins/mymodbus/ressources/mymodbus_serv.py ]; then
    rm ../../plugins/mymodbus/ressources/mymodbus_serv.py
fi
if [ -f ../../plugins/mymodbus/ressources/mymodbus_write.py ]; then
    rm ../../plugins/mymodbus/ressources/mymodbus_write.py
fi
if [ -d ../../plugins/mymodbus/ressources/__pycache__ ]; then
    rm -rf ../../plugins/mymodbus/ressources/__pycache__
fi
if [ -d ../../plugins/mymodbus/ressources/demond ]; then
    rm -rf ../../plugins/mymodbus/ressources/demond
fi
if [ -d ../../plugins/mymodbus/ressources/jeedom ]; then
    rm -rf ../../plugins/mymodbus/ressources/jeedom
fi
echo 5 > "$PROGRESS_FILE"
echo "********************************************************"
echo "*               Installation de pyenv                  *"
echo "********************************************************"
echo $(date)
export PYENV_ROOT="$(realpath ../../plugins/mymodbus/ressources)/_pyenv"
if [ ! -d "$PYENV_ROOT" ]; then
    sudo -E -u www-data curl https://pyenv.run | bash
    echo 20 > "$PROGRESS_FILE"
    echo "****  Configuration de pyenv..."
    sudo -E -u www-data cat >> ~www-data/.bashrc<< EOF
export PYENV_ROOT="$PYENV_ROOT"
command -v pyenv >/dev/null || export PATH="\$PYENV_ROOT/bin:\$PATH"
eval "\$(pyenv init -)"
EOF
fi
echo 30 > "$PROGRESS_FILE"
if [ ! -d "$PYENV_ROOT/versions/$PYENV_VERSION" ]; then
    echo "********************************************************"
    echo "*    Installation de python $PYENV_VERSION (dure longtemps)    *"
    echo "********************************************************"
    chown -R www-data:www-data "$PYENV_ROOT"
    sudo -E -u www-data "$PYENV_ROOT"/bin/pyenv install "$PYENV_VERSION"
fi
echo 95 > "$PROGRESS_FILE"
echo "********************************************************"
echo "*      Configuration de pyenv avec python $PYENV_VERSION       *"
echo "********************************************************"
cd ../../plugins/mymodbus/ressources/mymodbusd
chown -R www-data:www-data "$PYENV_ROOT"
sudo -E -u www-data "$PYENV_ROOT"/bin/pyenv local "$PYENV_VERSION"
sudo -E -u www-data "$PYENV_ROOT"/bin/pyenv exec pip install --upgrade pip setuptools
chown -R www-data:www-data "$PYENV_ROOT"
sudo -E -u www-data "$PYENV_ROOT"/bin/pyenv exec pip install --upgrade requests pyserial pyudev pymodbus
chown -R www-data:www-data "$PYENV_ROOT"
echo 100 > "$PROGRESS_FILE"
rm "$PROGRESS_FILE"
echo "********************************************************"
echo "*             Installation terminée                    *"
echo "********************************************************"
