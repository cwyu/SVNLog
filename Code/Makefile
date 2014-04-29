# compiler
CC = gcc
SQLITE_CC = sqlite3

# load configuration file
DATADIR = `xmllint --nocdata --shell config.xml <<< "cat /config/sqlite/output/directory/database/text()" | grep -v "^/ >"`
SET_PRIORITY_SRC = `xmllint --nocdata --shell config.xml <<< "cat /config/program/set-priority/source-file/text()" | grep -v "^/ >"`
SET_PRIORITY_DIR = `xmllint --nocdata --shell config.xml <<< "cat /config/program/set-priority/executable/directory/text()" | grep -v "^/ >"`
SET_PRIORITY_EXE = `xmllint --nocdata --shell config.xml <<< "cat /config/program/set-priority/executable/filename/text()" | grep -v "^/ >"`
DB_SKELETON_FILE = `xmllint --nocdata --shell config.xml <<< "cat /config/mysql/resource/database-skeleton/file/text()" | grep -v "^/ >"`
LOG_FILE = `xmllint --nocdata --shell config.xml <<< "cat /config/log/file/text()" | grep -v "^/ >"`

all:
	# output directory for *.sqlite files
	test -d $(DATADIR) || mkdir -p $(DATADIR) && chmod 777 $(DATADIR)

	# update_now service: compile executable file
	test -d $(SET_PRIORITY_DIR) || mkdir -p $(SET_PRIORITY_DIR)
	$(CC) $(SET_PRIORITY_SRC) -o $(SET_PRIORITY_DIR)/$(SET_PRIORITY_EXE)

	# update_now service: set the effective UID to root for others (ex. apache user)
	sudo chown root:root $(SET_PRIORITY_DIR)/$(SET_PRIORITY_EXE) && sudo chmod u+s $(SET_PRIORITY_DIR)/$(SET_PRIORITY_EXE)

	# create database skeleton: minimize queries
	$(SQLITE_CC) $(DB_SKELETON_FILE) .dump | grep -i "CREATE TABLE" | $(SQLITE_CC) tmp && mv -f tmp $(DB_SKELETON_FILE)

	# create database skeleton
	./create-database-skeleton.sh

	# set log file writable
	touch $(LOG_FILE) && chmod 666 $(LOG_FILE)

