### Description
crontab          configuration file for cron jobs
svnlog2sqlite.py SVNPlot toolkit
my.cnf           configuration file for MySQL server

### File Location
crontab          /var/spool/cron/[USER]
svnlog2sqlite.py /usr/lib/python2.6/site-packages/svnplot/svnlog2sqlite.py
my.cnf           /etc/my.cnf

### Modification ###
    # svnlog2sqlite.py
        # Before
        451     def CreateTables(self):                                                                                                                                                      
        452         cur = self.dbcon.cursor()
        453         cur.execute("create table if not exists SVNLog(revno integer, commitdate timestamp, author text, msg text, \
        454                             addedfiles integer, changedfiles integer, deletedfiles integer)")
        455         cur.execute("create table if not exists SVNLogDetail(revno integer, changedpathid integer, changetype text, copyfrompathid integer, copyfromrev integer, \
        456                     pathtype text, linesadded integer, linesdeleted integer, lc_updated char, entrytype char)")
        457         cur.execute("CREATE TABLE IF NOT EXISTS SVNPaths(id INTEGER PRIMARY KEY AUTOINCREMENT, path text, relpathid INTEGER DEFAULT null)")

        # After (add parameter "id INTEGER PRIMARY KEY AUTOINCREMENT")
        451     def CreateTables(self):
        452         cur = self.dbcon.cursor()
        453         cur.execute("create table if not exists SVNLog(revno integer, commitdate timestamp, author text, msg text, \
        454                             addedfiles integer, changedfiles integer, deletedfiles integer, id INTEGER PRIMARY KEY AUTOINCREMENT)")
        455         cur.execute("create table if not exists SVNLogDetail(revno integer, changedpathid integer, changetype text, copyfrompathid integer, copyfromrev integer, \
        456                     pathtype text, linesadded integer, linesdeleted integer, lc_updated char, entrytype char, id INTEGER PRIMARY KEY AUTOINCREMENT)")
        457         cur.execute("CREATE TABLE IF NOT EXISTS SVNPaths(id INTEGER PRIMARY KEY AUTOINCREMENT, path text, relpathid INTEGER DEFAULT null)")

    # my.cnf
        # Before
        [mysqld]
        datadir=/var/lib/mysql
        socket=/var/lib/mysql/mysql.sock
        user=mysql
        # Disabling symbolic-links is recommended to prevent assorted security risks
        symbolic-links=0

        [mysqld_safe]
        log-error=/var/log/mysqld.log
        pid-file=/var/run/mysqld/mysqld.pid

        # After (increase maximum connection counts)
        [mysqld]
        datadir=/var/lib/mysql
        socket=/var/lib/mysql/mysql.sock
        user=mysql
        # Disabling symbolic-links is recommended to prevent assorted security risks
        symbolic-links=0
        max_connections=100000

        [mysqld_safe]
        log-error=/var/log/mysqld.log
        pid-file=/var/run/mysqld/mysqld.pid
