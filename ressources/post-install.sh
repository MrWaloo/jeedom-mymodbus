#!/usr/bin/bash
PROGRESS_FILE=/tmp/post-install_mymodbus_in_progress
if [ ! -z $1 ]; then
	PROGRESS_FILE=$1
fi

touch ${PROGRESS_FILE}
echo 0 > ${PROGRESS_FILE}
echo "********************************************************"
echo "*     Installation de pyenv : peut prendre du temps    *"
echo "********************************************************"
echo $(date)
sudo curl https://pyenv.run | bash
echo 40 > ${PROGRESS_FILE}
echo "********************************************************"
echo "*               Configuration de pyenv                 *"
echo "********************************************************"
sudo cat >> /root/.bashrc<< EOF
export PYENV_ROOT="$HOME/.pyenv"
command -v pyenv >/dev/null || export PATH="\$PYENV_ROOT/bin:\$PATH"
eval "\$(pyenv init -)"
EOF
echo 50 > ${PROGRESS_FILE}
echo "********************************************************"
echo "*             Installation de python 3.9.5             *"
echo "********************************************************"
sudo /root/.pyenv/bin/pyenv install -k 3.9.5
echo 85 > ${PROGRESS_FILE}
echo "********************************************************"
echo "*       Configuration de pyenv avec python 3.9.5       *"
echo "********************************************************"
cd plugins/mymodbus/ressources
sudo /root/.pyenv/bin/pyenv local 3.9.5
sudo /root/.pyenv/bin/pyenv exec pip install --upgrade pip setuptools
sudo /root/.pyenv/bin/pyenv exec pip install requests serial pyudev pymodbus
echo 100 > ${PROGRESS_FILE}
rm ${PROGRESS_FILE}
echo "********************************************************"
echo "*             Installation terminée                    *"
echo "********************************************************"

