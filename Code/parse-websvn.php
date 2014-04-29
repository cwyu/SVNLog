<?php

/**
 *
 * DESCRIPTION:
 *     This program is to collect (automatically and periodically) all OpenFoundry 
 *     subversion log data into MySQL database, which can be used to generate 
 *     visualization data for analysis. The idea of this implementation is to fetch 
 *     subversion repository log data (use open source tool SVNPlot) into SQLite, 
 *     and then converts SQLite queries to MySQL ones (use open source), and then 
 *     insert queries to MySQL server.
 *
 * ENVIRONMENT:
 *     PHP version 5
 *
 * @author     Cheng-Wei Yu <cwyu.cs@gmail.com>
 * @copyright  Open Source
 * @project    OpenFoundry (http://www.openfoundry.org/)
 */

// constants
define("CONFIG_FILE", "./config.xml");    // configuration file location

// load configuration file
$config = new Config(CONFIG_FILE);

// check environment requirement
if (!function_exists("curl_init")) {
    die("cURL module for PHP not exists.");
} else if (!class_exists("DOMDocument")) {
    die("Class DOMDocument for PHP not exists.");
} else if (!class_exists("DomXpath")) {
    die("Class DomXpath for PHP not exists.");
} else if (!file_exists($config->fetch_sqlite_to_mysql_script)) {
    die("Fetch *.sqlite program '" . $config->fetch_sqlite_to_mysql_script . "' not exists.");
} else if (!file_exists($config->set_priority_program)) {
    die("Set priority program '" . $config->set_priority_program . "' not exists.");
}

// get repository list
$svn_list = getRepositoryList($config->websvn_url);

// HTTP service
if (isset($_REQUEST['update_now']) && !empty($_REQUEST['update_now'])) {    // assign high priority for one repository
    // set repository
    $svn_repository = $_REQUEST['update_now'];

    // check if the repository exists
    if (!in_array($svn_repository, $svn_list)) {
        echo "Project '$svn_repository' is NOT in the list" . "<br />";
    } else {
        // check if data is up-to-date
        if (isUpToDate($svn_repository) == true) {
            // output message
            echo "Project '$svn_repository' revision is up-to-date, do nothing" . "<br />";
        } else {
            // variables
            $script = $config->fetch_sqlite_to_mysql_script;
            $set_priority = $config->set_priority_program;

            // get running job(s) if any
            $pids = shell_exec("pgrep -fl \"$script $svn_repository\" | grep -v pgrep | awk '{print $1}'");

            // set log command (redirect stdout/stderr to log file)
            $log_redirect = ($config->is_logging == "yes") ? "&>> $config->log_file" : "";

            // fetch data in high priority
            if (empty($pids)) {
                // start new job (background execution)
                pclose(popen("$set_priority --script=$script $svn_repository $log_redirect &", "w"));
            } else {
                // make running job list
                $stop_jobs = $pids;
                $pid_list = str_replace("\n", ",", trim($pids));

                // first stop running jobs and then start new one (background execution)
                pclose(popen("$set_priority --script=$script $svn_repository --with-running-jobs=$pid_list $log_redirect &", "w"));
            }

            // output message
            echo "Project '$svn_repository' is now running in high priority." . "<br />";
        }
    }
} else {    // scan all repositories, and execute shell script program (fetch SQLite -> convert to MySQL queries -> insert to MySQL server)
    // output message (HTML table visualization)
    echo "Total Project Count = " . count($svn_list) . "<br /><br />";
    echo "<table border='1' width=50%>";
    echo "<tr bgcolor=gray><th>Project</th><th width=70%>Status</th></tr>";

    // scan all svn repositories, and execute program in background respectively
    foreach ($svn_list as $svn_repository) {
        // check if data is up-to-date
        if (isUpToDate($svn_repository) == true) {
            // output message
            echo "<tr><td>" . $svn_repository . "</td><td>" . "revision up-to-date, do nothing" . "</td></tr>";
        } else {
            // variables
            $script = $config->fetch_sqlite_to_mysql_script;

            // get running job(s) if any
            $pid_info = shell_exec("pgrep -fl \"$script $svn_repository\" | grep -v pgrep");

            // set log command (redirect stdout/stderr to log file)
            $log_redirect = ($config->is_logging == "yes") ? "&>> $config->log_file" : "";

            // fetch data in normal priority
            if (empty($pid_info)) {
                // execute program (background execution)
                pclose(popen("sh && $script $svn_repository $log_redirect &", "w"));    // "sh && " for two-level process to continue work while interrupting (stopping) by update_now service

                // output message
                echo "<tr><td>" . $svn_repository . "</td><td>" . "revision out-of-date, now fetching" . "</td></tr>";
            } else {
                // output message
                echo "<tr><td>" . $svn_repository . "</td><td>" . "revision out-of-date, already running" . "</td></tr>";
            }
        }

        // flush stdout
        ob_flush();
        flush();
    }

    // output message (HTML table visualization)
    echo "</table>";
}


/**
 * DESCRIPTION:
 *     Get OpenFoundry repository list
 *
 * IN:
 *     $url    URL
 *
 * OUT:
 *     NONE
 * 
 * RETURNS:
 *     Returns repository list (array type) if exists, else empty array
 */
function getRepositoryList($url)
{
    // init result container
    $svn_list = array();

    // get HTML node list
    $html = file_get_contents($url);
    $class_names = array("project", "project shaded");    // http://view-source beforehand
    $uri_node_list = getHtmlNodeListByClassName($html, $class_names);

    // search for links
    if (!is_null($uri_node_list)) {
        foreach ($uri_node_list as $uri_node) {
            array_push($svn_list, $uri_node->nodeValue);
        }
    }

    return $svn_list;
}

/**
 * DESCRIPTION:
 *     Get node list from HTML by class name
 *
 * IN:
 *     $html           HTML context
 *     $class_names    array of class names in HTML
 *
 * OUT:
 *     NONE
 * 
 * RETURNS:
 *     Returns node list if exists, else null
 */
function getHtmlNodeListByClassName($html, $class_names)
{
    // parse HTML
    $doc = new DOMDocument();
    $doc->loadHTML($html);    // symbol '@' is to ignore warning message due to the original HTML code
    //@$doc->loadHTML($html);    // symbol '@' is to ignore warning message due to the original HTML code

    // check error
    if (!$doc) {
        // print error message and exit
        echo "Error: call to DOMDocument::loadHTML() function failed." . "<br />";
        exit;
    }

    $xpath = new DomXpath($doc);

    // generate query string
    $query_string = "";
    foreach ($class_names as $class_name) {
        $query_string .= "//*[@class=\"$class_name\"]";    // http://view-source beforehand

        // append if not the last element
        if ($class_name != end($class_names)) {
            $query_string .= "|";
        }
    }

    $node_list = $xpath->query($query_string);    // will get DOMNodeList if query success, else false

    // adjust return value
    $node_list = ($node_list == false) ? null : $node_list;

    return $node_list;
}

/**
 * DESCRIPTION:
 *     Check whether data is up-to-date by comparing with revision between website and SQLite database
 *
 * IN:
 *     $repository    project name
 *
 * OUT:
 *     NONE
 * 
 * RETURNS:
 *     Boolean type: true if up-to-date, else false
 * 
 * NOTE:
 *     In case out-of-date, the reason includes that failed to get revision from website
 */
function isUpToDate($repository) {
    $result = false;

    // get website revision
    $web_revno = getWebsvnRevision($repository);

    // check if the same revision between websvn and current SQLite
    if (!is_null($web_revno)) {
        // current SQLite revision
        global $config;
        $db_revno_file = "./$config->sqlite_output_directory/$repository.sqlite.old.revision";

        // check if reserved last time
        if (file_exists($db_revno_file)) {
            // get current SQLite revision
            $db_revno = file_get_contents($db_revno_file);

            // the same or not
            if ($db_revno != false && intval($web_revno) == intval($db_revno)) {
                $result = true;
            }
        }
    }

    return $result;
}

/**
 * DESCRIPTION:
 *     Get HTML title contents
 *
 * IN:
 *     $url    URL
 *
 * OUT:
 *     NONE
 * 
 * RETURNS:
 *     Title information string if success, else null
 */
function getHtmlTitleContents($url)
{
    // set regular expression parameters
    $html = file_get_contents($url);
    $pattern = "/\<title\>(.*)\<\/title\>/i";

    // perform match
    if ($html != false && preg_match($pattern, $html, $matches)) {
        return $matches[1];
    } else {
        // failure, ex. <http://svn.openfoundry.org/aguotest/>, password required
        return null;
    }
}

/**
 * DESCRIPTION:
 *     Get revision from <http://svn.openfoundry.org/[PROJECT_NAME]/>
 *
 * IN:
 *     $repository    project name
 *
 * OUT:
 *     NONE
 * 
 * RETURNS:
 *     Revision number if success, else null
 */
function getWebsvnRevision($repository)
{
    // set URL
    global $config;
    $url = $config->svn_homepage . $repository . "/";

    // set regular expression parameters
    $title = getHtmlTitleContents($url);
    $pattern = "/Revision (?P<number>\w+):/i";

    // perform match
    if (!is_null($title) && preg_match($pattern, $title, $matches)) {
        return $matches['number'];
    } else {
        // failure
        return null;
    }
}

/**
 * DESCRIPTION:
 *     Configuration class
 */
class Config
{
    var $websvn_url;
    var $svn_homepage;
    var $sqlite_output_directory;
    var $fetch_sqlite_to_mysql_script;
    var $set_priority_program;
    var $is_logging;
    var $log_file;

    /**
     * DESCRIPTION:
     *     Set data members (default constructor)
     *
     * IN:
     *     $file    path to configuration file, defaults to "config.xml"
     *
     * OUT:
     *     NONE
     * 
     * RETURNS:
     *     NONE
     */
    function __construct($file = "config.xml")
    {
        // load configuration file
        try {
            $config = new SimpleXmlElement(file_get_contents($file));
        } catch (Exception $e) {
            die("Error: failed to open configuration file." . "<br />");
        }

        // set data members
        $node =  $config->xpath("/config/subversion/url/websvn");
        $this->websvn_url = (string) $node[0];
        $node =  $config->xpath("/config/subversion/url/homepage");
        $this->svn_homepage = (string) $node[0];
        $node =  $config->xpath("/config/sqlite/output/directory/database");
        $this->sqlite_output_directory = (string) $node[0];
        $node =  $config->xpath("/config/program/fetch-script/file");
        $this->fetch_sqlite_to_mysql_script = (string) $node[0];
        // set_priority_program = directory + filename
        $node =  $config->xpath("/config/program/set-priority/executable/directory");
        $tmp_directory = (string) $node[0];
        $node =  $config->xpath("/config/program/set-priority/executable/filename");
        $tmp_filename = (string) $node[0];
        $this->set_priority_program = (!isset($tmp_directory) || !isset($tmp_filename)) ? "" :  "./" . $tmp_directory . DIRECTORY_SEPARATOR . $tmp_filename;
        $node =  $config->xpath("/config/log/enable");
        $this->is_logging = (string) $node[0];
        $node =  $config->xpath("/config/log/file");
        $this->log_file = (string) $node[0];

        // do check
        $this->check();
    }

    /**
     * DESCRIPTION:
     *     Check configuration file errors
     *
     * IN:
     *     NONE
     *
     * OUT:
     *     NONE
     * 
     * RETURNS:
     *     NONE
     */
    function check()
    {
        $error_message = null;

        // websvn_url
        if (empty($this->websvn_url)) {
            $error_message .= "websvn is not specified in the configuration file";
        }

        // svn_homepage
        if (empty($this->svn_homepage)) {
            $delimiter = (is_null($error_message)) ? "" : ", ";    // for string concatenation
            $error_message .= $delimiter . "homepage is not specified in the configuration file";
        }

        // sqlite_output_directory
        if (empty($this->sqlite_output_directory)) {
            $delimiter = (is_null($error_message)) ? "" : ", ";    // for string concatenation
            $error_message .= $delimiter . "directory/database is not specified in the configuration file";
        }

        // fetch_sqlite_to_mysql_script
        if (empty($this->fetch_sqlite_to_mysql_script)) {
            $delimiter = (is_null($error_message)) ? "" : ", ";    // for string concatenation
            $error_message .= $delimiter . "fetch-script is not specified in the configuration file";
        }

        // set_priority_program directory
        if (empty($this->set_priority_program)) {
            $delimiter = (is_null($error_message)) ? "" : ", ";    // for string concatenation
            $error_message .= $delimiter . "set-priority/executable/directory or set-priority/executable/filename is not specified in the configuration file";
        }

        // is_logging
        if (empty($this->is_logging)) {
            $delimiter = (is_null($error_message)) ? "" : ", ";    // for string concatenation
            $error_message .= $delimiter . "log/enable is not specified in the configuration file";
        }

        // log_file
        if (empty($this->log_file)) {
            $delimiter = (is_null($error_message)) ? "" : ", ";    // for string concatenation
            $error_message .= $delimiter . "log/file is not specified in the configuration file";
        }

        // terminate program if any error occurs
        if (!is_null($error_message)) {
            die("Error: '$error_message'");
        }
    }
}

?>
