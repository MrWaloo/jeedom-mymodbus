echo "++++++++++++++++++++++++++++++++++++++"
echo "+  MyModbus Install dependancies"
echo "+  v1.1"
echo "+  By Bebel27"
echo "++++++++++++++++++++++++++++++++++++++"

PROGRESS_FILE=/tmp/dependances_MyModbus_en_cours
if [ ! -z $1 ]; then
	PROGRESS_FILE=$1
fi
touch ${PROGRESS_FILE}
echo 0 > ${PROGRESS_FILE}
echo "-"
sudo date
echo "++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++"
echo "MyModbus - Debut de l'installation des dependances ..."
echo "++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++"
cd /tmp
# mises a jours  ne fais plus car peut foutre le bazard
#echo "-"
#echo "++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++"
#echo "Mises a jour du systeme en cours ..."
#echo "/!\ Peut etre long suivant l'anciennete de votre systeme."
#echo "++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++"
#sudo apt-get -y update
#sudo apt-get -y upgrade
#sudo apt-get -y dist-upgrade
#sudo  pip uninstall pyModbusTCP -y

echo 50 > ${PROGRESS_FILE}
echo "-"
echo "++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++"
echo "Installation dependance  python-pip"
echo "++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++"
//sudo apt-get -y install python{,3}-pip python{,3}-setuptools

echo 60 > ${PROGRESS_FILE}
echo "-"
echo "++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++"
echo "Installation dependance  pypModbusTCP"
echo "++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++"
sudo pip install pyModbus
sudo pip install pyModbusTCP
echo 70 > ${PROGRESS_FILE}
echo "-"
echo "++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++"
echo "Installation dependance  python-serial"
echo "++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++"
//sudo apt-get -y install python-serial
//sudo pip3 uninstall serial
//sudo pip3 install pyserial

echo 80 > ${PROGRESS_FILE}
echo "-"
echo "++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++"
echo "Installation dependance git"
echo "++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++"

sudo apt-get -y install git

echo 90 > ${PROGRESS_FILE}
echo "-"
echo "++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++"
echo "Clonage de mbtget"
echo "++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++"
#
#    git clone https://github.com/sourceperl/mbtget.git
#    cd mbtget
#    perl Makefile.PL
#    make
#    sudo make install

echo 100 > ${PROGRESS_FILE}
echo "-"
echo "++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++"
echo "Fin de l'installation des dependances MyModbus..."
echo "++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++"
sudo chmod -R 755 ${PROGRESS_FILE}
rm ${PROGRESS_FILE}
