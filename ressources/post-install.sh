#!/usr/bin/bash
PROGRESS_FILE=/tmp/post-install_mymodbus_in_progress
if [ ! -z $1 ]; then
	PROGRESS_FILE=$1
fi

touch ${PROGRESS_FILE}
echo 0 > ${PROGRESS_FILE}
echo "********************************************************"
echo "*    Installation de pyenv : peut prendre du temps     *"
echo "********************************************************"
echo $(date)
#export PYENV_ROOT="${HOME}/.pyenv"
export PYENV_ROOT="$(echo ~www-data)/.pyenv"
sudo -u www-data curl https://pyenv.run | bash
echo 20 > ${PROGRESS_FILE}
echo "****  Configuration de pyenv..."
sudo -u www-data cat >> ~www-data/.bashrc<< EOF
export PYENV_ROOT="$HOME/.pyenv"
command -v pyenv >/dev/null || export PATH="\$PYENV_ROOT/bin:\$PATH"
eval "\$(pyenv init -)"
EOF
echo 30 > ${PROGRESS_FILE}
echo "********************************************************"
echo "*             Installation de python 3.9.5             *"
echo "********************************************************"
~www-data/.pyenv/bin/pyenv install 3.9.5
echo 95 > ${PROGRESS_FILE}
echo "********************************************************"
echo "*       Configuration de pyenv avec python 3.9.5       *"
echo "********************************************************"
cd ../../plugins/mymodbus/ressources
~www-data/.pyenv/bin/pyenv local 3.9.5
~www-data/.pyenv/bin/pyenv exec pip install --upgrade pip setuptools
~www-data/.pyenv/bin/pyenv exec pip install requests serial pyudev pymodbus
echo 100 > ${PROGRESS_FILE}
rm ${PROGRESS_FILE}
echo "********************************************************"
echo "*             Installation terminée                    *"
echo "********************************************************"
