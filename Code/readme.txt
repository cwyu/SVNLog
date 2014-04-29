# Description
This program is to collect (automatically and periodically) all OpenFoundry subversion log data into MySQL database, 
which can be used to generate visualization data for analysis. The idea of this implementation is to fetch subversion 
repository log data (use open source tool SVNPlot) into SQLite, and then converts SQLite queries to MySQL ones (use 
open source), and then insert queries to MySQL server.

# Output
1. SQLite files in local: ./database/REPOSITORY_NAME.sqlite
2. SVN log data of all repositories in MySQL database
3. (option) SVNPlot graphs in local: ./database/svnplot/REPOSITORY_NAME/

# Periodical Service
    # Scan All Repositories Periodically
    Step: Type `crontab -e` in terminal and then set the relative jobs on time

    # Example (every 15 minutes, see "./backup-conf/crontab")
    */15   *   *   *   * cd /var/www/html/code/ && php parse-websvn.php

# HTTP Service
    # Scan All Repositories Just Once
    http://YOUR_IP_ADDRESS/PATH/parse-websvn.php
    # Update Specified Repository Immediately
    http://YOUR_IP_ADDRESS/PATH/parse-websvn.php?update_now=REPOSITORY_NAME

    # Example
    http://140.109.17.50/code/parse-websvn.php
    http://140.109.17.50/code/parse-websvn.php?update_now=a12345

# Directory Structure
    .
    ├── backup-conf
    │   ├── crontab
    │   ├── my.cnf
    │   ├── readme.txt
    │   └── svnlog2sqlite.py
    ├── bin
    │   └── set-priority
    ├── config.xml
    ├── create-database-skeleton.sh
    ├── database
    ├── fetch-sqlite-to-mysql.sh
    ├── log.txt
    ├── Makefile
    ├── parse-websvn.php
    ├── readme.txt
    ├── resource
    │   ├── database-skeleton.sqlite
    │   └── SVNPlot-0.7.10.zip
    ├── set-priority.c
    └── tool
        └── open-source
            ├── download.url
            └── sqlite3-to-mysql.py

# Directory Structure Description
./backup-conf/crontab                   # Backup of configuration file for cron jobs
./backup-conf/my.cnf                    # Backup of configuration file for MySQL server
./backup-conf/readme.txt                # Backup of configuration file for MySQL server
./backup-conf/svnlog2sqlite.py          # Backup of modified code of SVNPlot tookit
./bin/set-priority                      # Executable (setuid) program to change priority
./config.xml                            # Configuration file
./create-database-skeleton.sh           # Shell script to create MySQL database skeleton
./database                              # Directory to save *.sqlite files (write permission is required)
./fetch-sqlite-to-mysql.sh              # Shell script (fetch SQLite -> convert to MySQL queries -> insert to MySQL server)
./log.txt                               # Log file, enabled by configuration file (NOTE: may cause huge size)
./Makefile                              # To build environment setting, including the "./bin/set-priority" program compilation, MySQL database skeleton creation, etc.
./parse-websvn.php                      # Scan all repositories, and execute shell script program (fetch-sqlite-to-mysql.sh) to fetch data
./readme.txt                            # Readme file
./resource/database-skeleton.sqlite     # Database skeleton, this file can be any *.sqlite file, and generate by rename to "database-skeleton.sqlite", and then run `make`
./resource/SVNPlot-0.7.10.zip           # Source code of SVNPlot
./set-priority.c                        # Source code that can change priority
./tool/open-source/download.url         # Download URL
./tool/open-source/qlite3-to-mysql.py   # Convert queries from SQLite to MySQL

