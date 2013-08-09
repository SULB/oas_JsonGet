# oas_JsonGet

## Description

Retrieve aggregated data from service provider and import into a database.

This program should do the following jobs:

- Get aggregated OAI JSON data from Goettingen's server
  - Put the retrieved data into our MySQL database
  - Mark the retrieved data as processed based upon checksum
  - Write its actions to a logfile

- Provide access to the statistics data gathered from Goettingen's server

## Installation

Create database, make it accessible by **\_dbuser_**. Edit [create_tables.sql](./create_tables.sql) and change ````_repoidentifiers_```` to a comma separated list of repository identifiers like: ````'psydok.sulb.uni-saarland.de','scidok.sulb.uni-saarland.de'```` and execute the sql statements in your database.

## Configuration

### Basics

Edit [config.php](./config.php). Replace the following things

 ````_user_```` - the username (login name) for access to aggregated data. (Ask GBV)
 
 ````_password_```` - the password to login for access to aggregated data. (Ask GBV)
 
 ````_repopath_```` - the path for access to aggregated data. (Ask GBV)
 
 ````_dbname_```` - the name of the database to import data to

 ````_dbuser_```` and ````_dbpasswd_```` - the database access credentials

### Multi-repository configuration
 
 For more then one repository you can copy [**config_subrepo.php**](./config_subrepo.php) to **config\_subrepo\_*reponame*.php**. Edit the path
 
 ````php 
 $C["BaseURL"] = "http://_user_:_password_@oase.gbv.de/_repopath_/_subrepopath_/%Y/%m/"; 
 ````
 
in this file and replace ````_subrepopath_```` with path to the name of the repository (ask GBV). (````_user_````, ````_password_```` and ````_repopath_```` as in config.php)
 
## Running

Call 

 ````sh
 bash getJsonFiles.sh help
 ````
for a small info. Else with ````debug```` for debug messages on stdout or __without__ arguments for logging messages into a file.


### Making data accessible

Call [index.php](./index.php) without parameters for an info or with following parameters:

 - ````id```` - ID of a document
 
 - ````u```` - one of: day, week, month, year, all
 
 - ````n```` - count of: day, week, monts...


For visits of the last seven days on document ````oai:scidok.sulb.uni-saarland.de:1199```` call:

 ````index.php?id=oai:scidok.sulb.uni-saarland.de:1199&u=day&n=7````


## License

The MIT [License](./LICENSE) (MIT)


