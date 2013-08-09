#!/bin/bash
if [ "$1" = "help" ]
then
  echo "To see the debug messages, call 'getJsconFiles.sh debug'. Else the output will be stored in 'lastrun.log' or 'lastrun_subreponame.log'."
fi
php5=$(which php5)

c=$(find . -type f -name config_subrepo_*.php | grep -c .)
if [ "$c" == "0" ]
then
  if [ "$1" = "debug" ]
  then
      # Log to stdout (sends mail if run from CRON)
      $php5 getJsonFiles.php $i 2>&1 | tee lastrun.log
    else
      # Log to file only
      $php5 getJsonFiles.php $i 2>&1 | tee lastrun.log > /dev/null
    fi
else
  for i in $(ls config_subrepo_*.php); do
      m=$(echo $i | sed 's/config_subrepo_//g' | sed 's/\.php//g')
      if [ "$1" = "debug" ]
      then
        # Log to stdout (sends mail if run from CRON)
        $php5 getJsonFiles.php $i 2>&1 | tee lastrun_$m.log
      else
        # Log to file only
        $php5 getJsonFiles.php $i 2>&1 | tee lastrun_$m.log > /dev/null
      fi
  done;
fi