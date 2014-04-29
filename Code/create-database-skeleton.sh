#!/bin/sh

# parameter
repository=$1    # argument from caller

# load configuration file
db_hostname=`xmllint --nocdata --shell config.xml <<< "cat /config/mysql/hostname/text()" | grep -v "^/ >"` \
             || { echo "hostname is not specified in the configuration file" ; exit; }
db_user=`xmllint --nocdata --shell config.xml <<< "cat /config/mysql/username/text()" | grep -v "^/ >"` \
             || { echo "username is not specified in the configuration file" ; exit; }
db_password=`xmllint --nocdata --shell config.xml <<< "cat /config/mysql/password/text()" | grep -v "^/ >"` \
             || { echo "password is not specified in the configuration file" ; exit; }
db_target=`xmllint --nocdata --shell config.xml <<< "cat /config/mysql/output/database/text()" | grep -v "^/ >"` \
             || { echo "mysql/output/database is not specified in the configuration file" ; exit; }
db_skeleton_file=`xmllint --nocdata --shell config.xml <<< "cat /config/mysql/resource/database-skeleton/file/text()" | grep -v "^/ >"` \
             || { echo "database-skeleton/file is not specified in the configuration file" ; exit; }
new_field=`xmllint --nocdata --shell config.xml <<< "cat /config/mysql/output/new-field/text()" | grep -v "^/ >"` \
             || { echo "new-field is not specified in the configuration file" ; exit; }
tool=`xmllint --nocdata --shell config.xml <<< "cat /config/program/open-source/sqlite3-to-mysql/file/text()" | grep -v "^/ >"` \
             || { echo "sqlite3-to-mysql/file is not specified in the configuration file" ; exit; }

# variables
table_1="SVNLog"    # table name used by SVNPlot open source tool
table_2="SVNLogDetail"    # table name used by SVNPlot open source tool
table_3="SVNPaths"    # table name used by SVNPlot open source tool


#########################
## Program Starts Here ##
#########################
if [ ! -f "$db_skeleton_file" ]; then    # check database skeleton file
    echo "Error: cannot find database skeleton $db_skeleton_file"
    exit
elif [ ! -f "$tool" ]; then    # check open source tool
    echo "Error: cannot find open source translation tool $tool"
    exit
else    # create database skeleton
    # variables
    file=$db_skeleton_file
    project_name=$(basename $file)    # skip path
    db_name="${project_name%%.*}"    # skip file extension

    # create table
    mysql -u$db_user -p$db_password -h$db_hostname -e "create database if not exists \`$db_target\` character set utf8;"

    # convert and insert
    sqlite3 $file .dump | $tool | mysql -u$db_user -p$db_password -h$db_hostname --default-character-set=utf8 $db_target

    # add new field, change column order, and set primary key (heredoc syntax)
    mysql -u$db_user -p$db_password -h$db_hostname <<-EOF
        alter table \`$db_target\`.\`$table_1\` add \`$new_field\` char(100) not null default '$db_name' first, change \`id\` \`id\` int(11) after \`$new_field\`;
        alter table \`$db_target\`.\`$table_2\` add \`$new_field\` char(100) not null default '$db_name' first, change \`id\` \`id\` int(11) after \`$new_field\`;
        alter table \`$db_target\`.\`$table_3\` add \`$new_field\` char(100) not null default '$db_name' first, change \`id\` \`id\` int(11) after \`$new_field\`;

        alter table \`$db_target\`.\`$table_1\` drop primary key, add primary key (\`$new_field\`, \`id\`);
        alter table \`$db_target\`.\`$table_2\` drop primary key, add primary key (\`$new_field\`, \`id\`);
        alter table \`$db_target\`.\`$table_3\` drop primary key, add primary key (\`$new_field\`, \`id\`);
	EOF
fi

echo "Complete"

