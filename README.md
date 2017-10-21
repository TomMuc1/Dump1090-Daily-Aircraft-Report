# Dump1090-Daily-Aircraft-Report

short script that daily reports aircrafts from dump1090 and sends auto-email every day.



php-install - if not already installed:    

given raspbian jessie or stretch install with dump1090

sudo apt-get update

install sendmail:

sudo apt-get install sendmail

php install - raspbian jessie only:

sudo apt-get install php5-common php5-cgi php5-mysql php5-sqlite php5-curl php5

php install - raspbian stretch only:

sudo apt-get install php7.0-common php7.0-cgi php7.0-mysql php7.0-sqlite php7.0-curl php7.0

setup crontab to auto-run script:

sudo crontab -e

@reboot sleep 10 && /usr/bin/php /home/pi/ac_counter.php > /dev/null
