About
=================
 Network Line Monitor (NLM) is an application created to monitor IP SLA statistics on Cisco devices. Line in this context is used to describe logical connection between IP SLA source device and IP SLA responder device. Application allows you to periodically gather IP SLA statistics from devices via SNMP, store them in database, visualize them in form of graphs, setup threshold values for each statistic and generate alert in case of their contravention via user friendly GUI.

Supported IP SLA operations: icmp echo, icmp jitter

**NOTICE: Application is still in development.**


Installation
------------
**Tested for Ubuntu server 20.04 and Apache2**

1. Install dependencies:
	```
	sudo apt-get install rrdtool apache2 postgresql-12 php php-cli php-json php-common  php-pear php-gd php-snmp php-pgsql php-rrd php-fpm git
	```

2. Create user (future owner of project folder):
	```
	sudo useradd nlm -d /var/lib/nlm -M -r -s /bin/bash
	```

3. Get Composer:
	To install Composer follow steps from: https://getcomposer.org/download/ 
	After instal run following command to enable global use of Composer:
	```
	sudo mv ./composer.phar ~/bin/composer # or /usr/local/bin/composer
	```

4. Copy project from github to /var/www/:
	```
	cd /var/www
	```
	```
	sudo git clone https://github.com/mdok/test.git
	```

5. Run Composer update in project folder to install additional project dependencies:
	Change dir to project
	```
	sudo composer update --no-plugins --no-scripts
	```

6. Prepare database:
	Run folloving commands one by one:
	```
	sudo su - postgres
	psql 
	DROP DATABASE IF EXISTS nlm;
	DROP USER IF EXISTS nlm;
	CREATE DATABASE nlm;
	CREATE USER nlm WITH ENCRYPTED PASSWORD 'nlm'; #change pw to your pw	
	GRANT ALL PRIVILEGES ON DATABASE nlm TO nlm;
	\c nlm
	\i /var/www/network-line-mon/bin/db.sql
	quit
	exit
	```


7. Modify configuration:
	Open common.neon located in app/config/ directory of project and setup SNMP commmunity string (replace "test" with your community string) and poll interval (minimum allowed poll interval is 60s). Poll interval decides how often are new statistics gathered from devices and affects configuration of round robin database files for archivation of those statistics. Suggestion is to set this interval to minimum (but greater or eaqual 60s) of interval used in IP SLA configuration on devices.
	Keep in mind that the same interval will need to be setup for cron job for running scripts periodically in step 9, therefore chose the interval wisely according to possibilities of cron.

	Open local.neon located in same directory and change the database password value to value you filled during atabase setup. (step 6)

	**Do not change anything else inside the configuration files**
	

8. Setup file permissions accordingly:
	```
 	sudo chown -R nlm /var/www/network-line-mon/
 	sudo chgrp -R www-data /var/www/network-line-mon/
	sudo chmod -R 750 /var/www/network-line-mon/
	sudo chmod -R g+w /var/www/network-line-mon/temp
	sudo chmod -R g+w /var/www/network-line-mon/log
	sudo chmod -R g+w /var/www/network-line-mon/rrd
	sudo chmod -R g+w /var/www/network-line-mon/www/graph
	sudo find /var/www/network-line-mon -name .htaccess | xargs sudo chmod 444
	sudo find /var/www/network-line-mon -name *.neon  | xargs sudo chmod 640
	sudo chmod g+s /var/www/network-line-mon/
	```

9. Setup cron job for cli scripts to run periodically:
	
	```
	sudo crontab -u www-data -e
	```
	
	Insert following lines and change the execution interval to interval you set up for poll in step 7 (to decide on interval value you can use: https://crontab-generator.org/)
	For default poll (value every 60s) leave the interval as it is.
	```
	* * * * * php /var/www/network-line-mon/bin/frequentSlaPoll.php >> /dev/null 2>&1
	* * * * * php /var/www/network-line-mon/bin/frequentDevicePoll.php >> /dev/null 2>&1
	```

10. Setup Apache2
	Enable mod rewrite:
	```
	sudo a2enmod rewrite
	```
	
	Append following to directory section of Apache configuration file:
	```
	sudo vi /etc/apache2/apache2.conf`
	```
	```
	<Directory /var/www/network-line-mon>
        Options Indexes FollowSymLinks
        AllowOverride All
        Require all granted
	</Directory>
	```

	Change virtual host DocumentRoot to:
	```
	DocumentRoot /var/www/network-line-mon/www
	```

	Finally restart apache:
	```
	sudo service apache2 restart
	```

	**This is just quick start example. You can setup Apeche as you wish as long you use specified DocumentRoot and Directory setup.**

11. Access the configured website. You should be able to log in using automatically created user:
	```
	username: nlm
	password: nlm
	```

**Create your own admin account upon logging in and delete automatically created user nlm as soon as possible.**
