#include <stdio.h>
#include <stdlib.h>
#include <string.h>
#include <getopt.h>
#include <unistd.h>
#include <sys/types.h>
#include <sys/stat.h>

#define BUF_SIZE (1024)
#define PRIORITY (-20)    // priority value for `nice`

extern int optind;    // remaining arguments index
extern char *optarg;    // option-argument value

void get_stop_command(char *command, const char *pid_list);
void get_start_command(char *command, const char *script, const char *svn_repository);
void get_script_file(char *script, const char *file_path);
void set_writable_permission(char *command, const char *svn_repository);

int main(int argc, const char *argv[])
{
    // check
    if (argc < 3) {
        printf("Usage: set-priority --script=file_path [--with-running-jobs=pid,...] SVN_REPOSITORY\n");
        exit(EXIT_FAILURE);
    }

    // variables
    char command[BUF_SIZE] = {0};
    char script[BUF_SIZE] = {0};
    const char short_options[] = "S:P:";
    const struct option long_options[] = {
        {"script", required_argument, NULL, 'S'},    // -S, --script
        {"with-running-jobs", required_argument, NULL, 'P'},    // -P, --with-running-jobs
        {0, 0, 0, 0}
    };

    // become root
    int root_uid = 0;
    setuid(root_uid);

    // parse optional arguments
    int opt = 0;
    while ( (opt = getopt_long(argc, (char * const *) argv, short_options, long_options, NULL)) != -1) {
        switch (opt) {
        case 'S':    // --script
            // get script file path
            get_script_file(script, optarg);
            break;
        case 'P':    // --with-running-jobs
            // stop running jobs
            get_stop_command(command, optarg);
            system(command);
            break;
        default:
            printf("Usage: set-priority --script=file_path [--with-running-jobs=pid,...] SVN_REPOSITORY\n");
            break;
        }
    }

    // set repository
    const char *svn_repository = argv[optind];

    // start new job in high priority
    get_start_command(command, script, svn_repository);
    system(command);

    // set write permission to '.sqlite' and '.sqlite.old' files for others
    set_writable_permission(command, svn_repository);
    system(command);

    exit(EXIT_SUCCESS);
}

// get stop command
void get_stop_command(char *command, const char *pid_list)
{
    // system command `pkill` with parent pid-list options
    sprintf(command, "pkill -P %s\0", pid_list);
}

// get start command
void get_start_command(char *command, const char *script, const char *svn_repository)
{
    // system command `nice` with priority options
    sprintf(command, "nice -n %d %s %s\0", PRIORITY, script, svn_repository);
}

void get_script_file(char *script, const char *file_path)
{
    // get script file path
    strcpy(script, file_path);
}

// set writable permission
void set_writable_permission(char *command, const char *svn_repository)
{
    // system command `chmod` with write permission for all users
    sprintf(command, "chmod a+w database/%s*\0", svn_repository);
}

