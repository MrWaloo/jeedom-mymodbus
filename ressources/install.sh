touch /tmp/dependancy_modbus_in_progress
echo 0 > /tmp/dependancy_modbus_in_progress
echo "Launch install of modbus dependancy"
sudo apt-get update
echo 50 > /tmp/dependancy_modbus_in_progress
sudo apt-get install -y python-pip 
echo 66 > /tmp/dependancy_modbus_in_progress
sudo pip install sudo pip install pyModbusTCP
echo 95 > /tmp/dependancy_modbus_in_progress
cd /tmp
echo 100 > /tmp/dependancy_modbus_in_progress
echo "Everything is successfully installed!"
rm /tmp/dependancy_modbus_in_progress