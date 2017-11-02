### Dump1090-Daily-Aircraft-Report

Aggregates Dump1090 received messages to a daily report then sends an email report and/or writes a log-file and/or writes to MySql database

![Alt text](screen.png?raw=true "Sample Report")

one line sample database output using an inner join to basestation.sqb:

    sql: select * from daily_report inner join basestation on daily_report.transponder = basestation.ModeS

	id    report_date    transponder    messages    flight    category    squawk    first_seen                    first_latitude    first_longitude    first_altitude    last_seen                    last_latitude    last_longitude    last_altitude    low_dist    high_dist    low_rssi    high_rssi    mlat    AircraftID    ModeS    ModeSCountry    Country    Registration    Status    Manufacturer    ICAOTypeCode    Type    SerialNo    RegisteredOwners    OperatorFlagCode
	1     20171022       4b19e8         478         SWR117G   A0          3024      2017-10-22 20:47:21 Sunday    47.468851         8.820261           13750             2017-10-22 21:02:03 Sunday   48.658447        9.533169          4525             74.2        102.5        -30.7       -9.6                 1282          4B19E8   Switzerland     HB         HB-JVC          A         Fokker          F100            F100    11501       Helvetic Airways    OAW

or simply count all aircrafts/messages per day:

	select count(report_date) as seen_aircrafts, sum(messages) as received_messages, report_date as count_date from daily_report group by report_date order by count_date desc

	seen_aircrafts    received_messages    count_date
	3705              6304047              20171026
	3617              6376172              20171025
	3291              5900191              20171024
	3233              5822322              20171023
	
**=> do the needed settings at top of ac_counter.php - then place the script e.g. in /home/pi/ and follow below instructions**

**starting with raspbian jessie or stretch install with dump1090:**

    sudo apt-get update

	install sendmail (only needed for email option):
	sudo apt-get install sendmail

	php install - raspbian jessie only:
	sudo apt-get install php5-common php5-cgi php5-mysql php5-sqlite php5-curl php5

	php install - raspbian stretch only:
	sudo apt-get install php7.0-common php7.0-cgi php7.0-mysql php7.0-sqlite php7.0-curl php7.0


**setup script system service:**

    sudo chmod 755 /home/pi/ac_counter.php
    sudo nano /etc/systemd/system/ac_counter.service

-> in nano insert the following lines

    [Unit]
    Description=ac_counter.php
    
    [Service]
    ExecStart=/home/pi/ac_counter.php
    Restart=always
    RestartSec=10
    StandardOutput=null
    StandardError=null
    
    [Install]
    WantedBy=multi-user.target

save and exit nano ctrl+x -> ctrl+y -> enter

    sudo chmod 644 /etc/systemd/system/ac_counter.service
    sudo systemctl enable ac_counter.service
    sudo systemctl start ac_counter.service
    sudo systemctl status ac_counter.service
    
**alternative but not reccomended you can run the script via cron:**

	setup crontab to auto-run script:
	sudo crontab -e
	@reboot sleep 10 && /usr/bin/php /home/pi/ac_counter.php > /dev/null


