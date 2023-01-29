echo "++++++++++++++++++++++++++++++++++++++"
echo "+  MyModbus Install dependancies"
echo "+  v1.5"
echo "+  By Bebel27"
echo "++++++++++++++++++++++++++++++++++++++"

PROGRESS_FILE=/tmp/dependancy_mymodbus_in_progress
if [ ! -z $1 ]; then
	PROGRESS_FILE=$1
fi
touch ${PROGRESS_FILE}
echo 0 > ${PROGRESS_FILE}
echo "++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++"
echo "MyModbus - Debut de l'installation des dependances..."
echo "++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++"
echo "-"
sudo date
echo "-"
echo "++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++"
echo "Installation dependance  python-pip"
echo "++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++"
sudo apt-get -y install python3-pip python3-setuptools
echo 60 > ${PROGRESS_FILE}
echo "-"
echo "++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++"
echo "Installation des dependances: pyModbus et ses dependances"
echo "++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++"
sudo python3 -m pip install install pyModbus>=3.1.1
echo 95 > ${PROGRESS_FILE}
echo "-"
echo "++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++"
echo "Controle version..."
echo "++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++"
echo " Version de python"
sudo python3 --version
echo " Version de PIP "
sudo pip3 --version
echo 100 > ${PROGRESS_FILE}
echo "++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++"
echo "Fin de l'installation des dependances MyModbus..."
echo "++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++"

sudo chmod -R 755 ${PROGRESS_FILE}
rm ${PROGRESS_FILE}