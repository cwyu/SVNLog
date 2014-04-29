#!/bin/bash

# parameter
repository=$1    # argument from caller

######################################################################
# DESCRIPTION:
#     (Use SVNPlot Toolkit)
#     Fetch subversion repository log data into SQLite local file
#
# IN:
#     $1    project name
# 
# RETURNS:
#     NONE
# 
# REFERENCE:
#     https://code.google.com/p/svnplot/
######################################################################
function fetch_sqlite()
{
    # parameter
    repository=$1

    #### # svnplot fetch database (using http:// protocol)
    #### python -m svnplot.svnlog2sqlite -l -v http://svn.openfoundry.org/$repository/ $output_path/$repository.sqlite

    # SVNPlot fetch svn log data into SQLite database (using file:// protocol)
    svnadmin create $output_path/$mirrors/$repository
    cat /dev/null > $output_path/$mirrors/$repository/hooks/pre-revprop-change
    echo '#!/bin/sh' >> $output_path/$mirrors/$repository/hooks/pre-revprop-change
    echo 'exit 0;' >> $output_path/$mirrors/$repository/hooks/pre-revprop-change
    chmod +x $output_path/$mirrors/$repository/hooks/pre-revprop-change
    svnsync initialize file://$output_path/$mirrors/$repository http://svn.openfoundry.org/$repository/
    svnsync synchronize file://$output_path/$mirrors/$repository
    python -m svnplot.svnlog2sqlite -l -v file://$output_path/$mirrors/$repository $output_path/$repository.sqlite

    # SVNPlot generate graphic visualization
    if [ "$is_graph" == "yes" ]; then
        mkdir -p $output_path/$svnplot/$repository
        python -m svnplot.svnplot-js -v $output_path/$repository.sqlite $output_path/$svnplot/$repository
    fi
}

######################################################################
# DESCRIPTION:
#     Convert queries from SQLite to MySQL, and insert to MySQL server
#
# IN:
#     $1    SQLite query string
# 
# RETURNS:
#     NONE
######################################################################
function sqlite_to_mysql()
{
    # parameter
    query=$1

    # variables
    project_name=$repository
    db_name="tmp-"$project_name    # preceding "tmp-" to avoid naming collision with target database (ex. svnplot)

    # create table
    mysql -u$db_user -p$db_password -h$db_hostname -e "create database \`$db_name\` character set utf8;"

    # convert and insert
    echo "$query" | $tool | mysql -u$db_user -p$db_password -h$db_hostname --default-character-set=utf8 $db_name

    # add new field, change column order, and set primary key (heredoc syntax)
    mysql -u$db_user -p$db_password -h$db_hostname <<-EOF
        alter table \`$db_name\`.\`$table_1\` add \`$new_field\` char(100) not null default '$project_name' first, change \`id\` \`id\` int(11) after \`$new_field\`;
        alter table \`$db_name\`.\`$table_2\` add \`$new_field\` char(100) not null default '$project_name' first, change \`id\` \`id\` int(11) after \`$new_field\`;
        alter table \`$db_name\`.\`$table_3\` add \`$new_field\` char(100) not null default '$project_name' first, change \`id\` \`id\` int(11) after \`$new_field\`;

        alter table \`$db_name\`.\`$table_1\` drop primary key, add primary key (\`$new_field\`, \`id\`);
        alter table \`$db_name\`.\`$table_2\` drop primary key, add primary key (\`$new_field\`, \`id\`);
        alter table \`$db_name\`.\`$table_3\` drop primary key, add primary key (\`$new_field\`, \`id\`);
	EOF

    # append data into target database (heredoc syntax)
    mysql -u$db_user -p$db_password -h$db_hostname <<-EOF                                                                                                                                            
        replace into \`$db_target\`.\`$table_1\`
        select * from \`$db_name\`.\`$table_1\`;
        replace into \`$db_target\`.\`$table_2\`
        select * from \`$db_name\`.\`$table_2\`;
        replace into \`$db_target\`.\`$table_3\`
        select * from \`$db_name\`.\`$table_3\`;
	EOF

    # delete temporal database
    mysql -u$db_user -p$db_password -h$db_hostname -e "drop database \`$db_name\`;"
}


#########################
## Program Starts Here ##
#########################

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
is_graph=`xmllint --nocdata --shell config.xml <<< "cat /config/sqlite/output/graph/text()" | grep -v "^/ >"` \
             || { echo "graph is not specified in the configuration file" ; exit; }
sqlite_output_directory=`xmllint --nocdata --shell config.xml <<< "cat /config/sqlite/output/directory/database/text()" | grep -v "^/ >"` \
             || { echo "sqlite/output/directory/database is not specified in the configuration file" ; exit; }
mirrors=`xmllint --nocdata --shell config.xml <<< "cat /config/sqlite/output/directory/mirrors/text()" | grep -v "^/ >"` \
             || { echo "mirrors is not specified in the configuration file" ; exit; }
svnplot=`xmllint --nocdata --shell config.xml <<< "cat /config/sqlite/output/directory/svnplot/text()" | grep -v "^/ >"` \
             || { echo "svnplot is not specified in the configuration file" ; exit; }
tool=`xmllint --nocdata --shell config.xml <<< "cat /config/program/open-source/sqlite3-to-mysql/file/text()" | grep -v "^/ >"` \
             || { echo "sqlite3-to-mysql/file is not specified in the configuration file" ; exit; }

# variables
table_1="SVNLog"    # table name used by SVNPlot open source tool
table_2="SVNLogDetail"    # table name used by SVNPlot open source tool
table_3="SVNPaths"    # table name used by SVNPlot open source tool
output_path="`pwd`/$sqlite_output_directory"    # absoulte path
output_sqlite_file="$output_path/$repository.sqlite" # path to .sqlite file
output_revision_file="$output_path/$mirrors/$repository/db/current"    # path to revision file (available when using file:// protocol)

# reserve write permission for other users (ex. root, apache, cwyu) under 'mirrors' directory
umask 000

# check environment
if [ ! -d "$output_path/$mirrors" ]; then    # mirror path
    mkdir -p "$output_path/$mirrors"
elif [ ! -f "$tool" ]; then    # open source tool
    echo "Error: cannot find open source translation tool $tool"
    exit
fi

# fetch sqlite
fetch_sqlite "$repository"

# check if up-to-date by .old file
if [ ! -f $output_sqlite_file ]; then
    echo "Something error, network connection may be broken while running open source translation tool $tool"
    exit
elif [ ! -f $output_sqlite_file.old ]; then    # the first time fetching data
    # sqlite to mysql
    query=$(sqlite3 $output_sqlite_file .dump)
    sqlite_to_mysql "$query"

    # reserve current database .sqlite file
    cp -fp $output_sqlite_file $output_sqlite_file.old

    # reserve current revision number
    [ -f $output_revision_file ] && cp -fp $output_revision_file $output_sqlite_file.old.revision
else    # update difference
    # compare between old and new version
    diff_old_new=$(diff --unchanged-group-format='' --changed-group-format=%\> --ignore-space-change --ignore-case --ignore-all-space <(sqlite3 $output_sqlite_file.old .dump) <(sqlite3 $output_sqlite_file .dump))

    # check if new data exists
    if [ -n "$diff_old_new" ]; then
        # sqlite to mysql (difference only)
        db_skeleton=$(sqlite3 $db_skeleton_file .dump | grep -i "CREATE TABLE")
        query=$(echo "$db_skeleton" "$diff_old_new")
        sqlite_to_mysql "$query"

        # reserve current database .sqlite file
        cp -fp $output_sqlite_file $output_sqlite_file.old

        # reserve current revision number
        [ -f $output_revision_file ] && cp -fp $output_revision_file $output_sqlite_file.old.revision
    else
        # do nothing
        :
    fi
fi

# avoid huge size (SVNPlot official bug <https://code.google.com/p/svnplot/issues/detail?id=57>)
rm -rf $output_path/$mirrors/$repository

echo "Complete"

