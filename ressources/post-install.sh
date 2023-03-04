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
echo "*               Installation de pyenv                  *"
echo "********************************************************"
echo $(date)
export PYENV_ROOT="$(realpath ../../plugins/mymodbus/ressources)/_pyenv"
if [ ! -d "$PYENV_ROOT"]; then
    sudo -u www-data curl https://pyenv.run | bash
    echo 20 > "$PROGRESS_FILE"
    echo "****  Configuration de pyenv..."
    sudo -u www-data cat >> ~www-data/.bashrc<< EOF
    export PYENV_ROOT="$PYENV_ROOT"
    command -v pyenv >/dev/null || export PATH="\$PYENV_ROOT/bin:\$PATH"
    eval "\$(pyenv init -)"
EOF
fi
echo 30 > "$PROGRESS_FILE"
if [ ! -d "$PYENV_ROOT/versions/$PYENV_VERSION"]; then
    echo "********************************************************"
    echo "*    Installation de python $PYENV_VERSION (dure longtemps)    *"
    echo "********************************************************"
    chown -R www-data:www-data "$PYENV_ROOT"
    sudo -u www-data "$PYENV_ROOT"/bin/pyenv install "$PYENV_VERSION"
fi
echo 95 > "$PROGRESS_FILE"
echo "********************************************************"
echo "*      Configuration de pyenv avec python $PYENV_VERSION       *"
echo "********************************************************"
cd ../../plugins/mymodbus/ressources/mymodbusd
chown -R www-data:www-data "$PYENV_ROOT"
sudo -u www-data "$PYENV_ROOT"/bin/pyenv local "$PYENV_VERSION"
sudo -u www-data "$PYENV_ROOT"/bin/pyenv exec pip install --upgrade pip setuptools
chown -R www-data:www-data "$PYENV_ROOT"
sudo -u www-data "$PYENV_ROOT"/bin/pyenv exec pip install --upgrade requests serial pyudev pymodbus
chown -R www-data:www-data "$PYENV_ROOT"
echo 100 > "$PROGRESS_FILE"
rm "$PROGRESS_FILE"
echo "********************************************************"
echo "*             Installation terminée                    *"
echo "********************************************************"
