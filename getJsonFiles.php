<?PHP
/* vim: tw=76 ts=4 sw=4: */
/* getJsonFiles.php -- part of OAS JSON stats
 *
 * @author James Barrante <jb@fcgh.net>,
           Saarlaendische Universitaets- und Landesbibliothek, Saarbruecken
 * @history
   ------------------------------------------------------------------------
    2013-04-26  Initial development
    2013-08-09  Documentation & Upload
   ------------------------------------------------------------------------
 * Copyright (C) 2013 Saarland University, Saarbruecken, Germany
 *
 * @license MIT
 * The MIT License (MIT)
 * 
 * Copyright (c) 2013 SULB
 * 
 * Permission is hereby granted, free of charge, to any person obtaining a copy of
 * this software and associated documentation files (the "Software"), to deal in
 * the Software without restriction, including without limitation the rights to
 * use, copy, modify, merge, publish, distribute, sublicense, and/or sell copies of
 * the Software, and to permit persons to whom the Software is furnished to do so,
 * subject to the following conditions:
 * 
 * The above copyright notice and this permission notice shall be included in all
 * copies or substantial portions of the Software.
 * 
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY, FITNESS
 * FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR
 * COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY, WHETHER
 * IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM, OUT OF OR IN
 * CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE SOFTWARE.
 * 
 */

error_reporting(E_ALL);

		# !TODO! Limitation of PHP < 5.3: Cannot put preg_replace_callback function inside class scope, can't use anonymous function!!! - jb 2013-06-07
		function _preg_callback_url($m) {
			// Replaces an HTTP password with '*'
	        return str_replace($m[1], '********', $m[0]);
		};


class OAS_GetJSON {

	function OAS_GetJSON($cf) {
		$this->PID = getmypid();
		$this->Log(sprintf("Configuration file: %s", $cf));
		try {
			require($cf);
		} catch(Exception $e) {
			printf("ERROR: Unable to include configuration file `%s`.\nError details: %s\n", $cf, $e);
			exit(1);
		};
		if (!is_array($C)) {
			printf("ERROR: Configuration file does not comply with expected format.\n");
			exit(2);
		};
		if (is_array($C) && @!$C['CheckCf']) {
			printf("ERROR: Configuration file misses 'CheckCf' set to 1.\n");
			exit(4);
		};
		$this->C = $C;
		// Try to get a database connection
		try {
			$db_opt = array(
				PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES utf8',
			);
			$this->dbh = new PDO($C['db'], $C['db_user'], $C['db_pass'], $db_opt);
		} catch (Exception $e) {
			printf("ERROR: Unable to establish database connection.\n\tDSN: '%s'\n\tUser: '%s'\n\tPass: {%s}\nDetails: %s\n", $C['db'], $C['db_user'], str_repeat('*', strlen($C['db_pass'])), $e);
			exit(8);
		};
		# $this->Log(sprintf("Configuration: %s\n", print_r($C,1)));
	} # endfn __construct



	function Log($s, $level = 3, $break = true) {
		if (@$this->C['logLevel'] === 0) return;
		$h = sprintf("%s [PID%05d]\t", strftime("%Y-%m-%d %H:%M:%S"), $this->PID);
		$m = sprintf("%s%s%s", (!$break ? $h : ""), $s, ($break ? "\n" : ""));
		file_put_contents('php://stderr', $m);
	} # endfn Log


	function Usage() {
		$str = <<<eot

OAS Aggregated Analysis File Parser
Version %ver% / %date%
Copyright 2013 Saarland University
Authors: %auth%

Usage: %0% [config_file] [--help]

If the config_file argument is ommited, config.php is assumed.

--help	This help message.

The configuration file is a PHP script which is included in the main program.
It must be valid PHP.  The file defines an array $C, which has named keys.
The file describes each configuration directive in detailed comments.

eot;
		$r = array();
		$r["%ver%"]   = "0.1";
		$r["%date%"]  = date("Y-m-d", filemtime(__FILE__));
		$r["%0%"]     = $GLOBALS['argv'][0];
		print str_replace(array_keys($r), array_values($r), $str);
	}



	function createDateRangeArray($strDateFrom,$strDateTo) {
    	// takes two dates formatted as YYYY-MM-DD and creates an
	    // inclusive array of the dates between the from and to dates.
	    $aryRange=array();
	    $iDateFrom=mktime(1,0,0,substr($strDateFrom,5,2),     substr($strDateFrom,8,2),substr($strDateFrom,0,4));
	    $iDateTo=mktime(1,0,0,substr($strDateTo,5,2),     substr($strDateTo,8,2),substr($strDateTo,0,4));
	    if ($iDateTo>=$iDateFrom) {
	        array_push($aryRange,date('Y-m-d',$iDateFrom)); // first entry
	        while ($iDateFrom<$iDateTo) {
	            $iDateFrom+=86400; // add 24 hours
	            array_push($aryRange,date('Y-m-d',$iDateFrom));
	        }
	    }
	    return $aryRange;
	}


    /* 
		This function creates a file list based on the parameters passed.
		The dates MUST be passed in a strtotime-readable format, i. e.
		2012-04-30
	*/
	function GetFilesArray($startDate, $endDate = false) {
/*
		// See: http://stackoverflow.com/questions/5209057/loop-through-dates-with-php
		// Better: http://www.php.net/manual/en/class.dateperiod.php
		$endDate   = $endDate === false ? date("Ymd") : $endDate;
		$startDate = strtotime(
*/
		if (isset($this->C['JSONindexURL'])) {
			
			$data = array();
			$ccmd = sprintf('curl -s %s', escapeshellarg($this->C['JSONindexURL']));
			exec($ccmd, $cout, $cret);
			$json = json_decode(implode("\n", $cout),1);
			if (is_array($json) && is_array($json['FileList'])) {
				$this->usesIndexJson = true;
				# We MUST not check every file via HTTP-HEAD request,
				# as we now have the data in one place
				$arr = array(); # new file list
				foreach ($json['FileList'] as $x) {
					# Check against database if we have already seen this file
					# Hash provided might differ, thus "OR" -- 2013-06-19 for index.json-based searching
					$path = '/' . ltrim($x['url'], '/');
					$mdtm = date("Y-m-d H:i:s", strtotime($x['changed']));
					$size = intval($x['size']);
					$stmt = $this->dbh->prepare("SELECT * FROM gbv_stat_files WHERE file = :file AND mdtm = :mdtm)");
					$stmt->execute(array(
						':file' => $path,
						':mdtm' => $mdtm,
					));
					$rows = 0;
					foreach ($stmt AS $sa) {
						$rows++;
					};
					printf(":> index.json: %s\t%s \t%8s Bytes [%d]\n", $path, $mdtm, $size, $rows);
					if ($rows < 1) {
						# Nothing found in file database, add file to list
						$arr[] = sprintf('%s%s', $this->C['BaseURL'], ltrim($path, '/'));
					};
				};
			} else {
				$this->Log(sprintf("Invalid JSON String or no sub-array called `FileList`. Dump:\n----\n\t%s\n----\n", implode("\n\t", $cout)));
				exit(42);
			}
			$this->JsonFileList = array();
			$this->JsonFileList['urls'] = $arr;
			return $arr;
		} else {
			// Use brute_force method
			if (strtotime($startDate) === false) {
				printf("GetFilesArray:\$startdate: Invalid date!\n");
			};
			if ($endDate !== false && strtotime($endDate) === false) {
				printf("GetFilesArray:\$enddate: Invalid date!\n");
			};
			$arr = array();
			$std = date("Y-m-d", strtotime(($startDate)));	# start date
			$end = date("Y-m-d", $endDate === false ? time() : strtotime($endDate)); 	# end date
			# printf("Start date: %s\n", var_export($std,1));
			# printf("  End date: %s\n", var_export($end,1));
			$dz  = $this->createDateRangeArray($std, $end);
			foreach ($dz as $date) {
				$unixtime   = strtotime($date);
				$arr[$date] = sprintf('%s%s', 
								strftime($this->C['BaseURL'], $unixtime), 
								strftime($this->C['FileStr'], $unixtime)
				);
			};
			$this->JsonFileList = array();
			$this->JsonFileList['urls'] = $arr;
			return $arr; #####
			print_r($arr);
		};
	} # endfn getfilesarray



		function RandomFileName() {
			do {
				$name = '.curltemp-';
				$name.= substr(preg_replace('/[^a-z0-9]/m', '', file_get_contents('/dev/urandom', false, null, -1, 1024)), 0, 8);
				$name.= '.txt';
			} while (file_exists($name));
			return $name;
		}



	function GetRemoteJSONFile($url) {
		# First, check the headers (and if the file exists)
		# Also build a hash based on these headers
		$data = array();
		$ccmd = sprintf('curl -s --head %s', escapeshellarg($url));
		exec($ccmd, $cout, $cret);
		$head = array();
		foreach ($cout AS $line) {
			$line = trim($line);
			if ($line == '') continue;
			$line = array_map('trim', explode(':', $line, 2));
			if (sizeof($line) < 2) {
				$head['STATUS'] = intval(array_pop(array_slice(explode(' ', $line[0]), 1, 1)));
			} else {
				$head[strtoupper($line[0])] = is_numeric($line[1]) ? intval($line[1]) : $line[1];
			};
		};
	@	$head['LAST-MODIFIED'] = strtotime($head['LAST-MODIFIED']);
		$head['LAST-MODIFIED'] = $head['LAST-MODIFIED'] === false ? NULL : date("Y-m-d H:i:s", $head['LAST-MODIFIED']);
		$iurl = parse_url($url);
		$file = basename($iurl['path']);
	@	$hash = sha1(sprintf('%s::%s::%08x::%s', $file, $head['LAST-MODIFIED'], $head['CONTENT-LENGTH'], $head['ETAG']));
		$data['url']  = $url;
		$data['http'] = $head['STATUS'];
		$data['path'] = $iurl['path'];
		$data['file'] = $file;
		$data['hash'] = $hash;
		$data['head'] = $head;
		$data['mdtm'] = $head['LAST-MODIFIED'];
		$data['seen'] = false;
		$data['body'] = false;
		# Check against database if we have already seen this file
        # Hash provided might differ, thus "OR" -- 2013-06-19 for index.json-based searching
		$stmt = $this->dbh->prepare("SELECT * FROM gbv_stat_files WHERE file = :file AND (hash = :hash OR mdtm = :mdtm)");
		$stmt->execute(array(
			':file' => $data['path'],
			':hash' => $data['hash'],
			':mdtm' => $head['LAST-MODIFIED'],
		));
		foreach ($stmt AS $X) {
			$data['seen'] = $X;
			break;
		};
	#	print_r($data); exit();
		# We haven't processed this file yet, so do it now
		if (!is_array($data['seen']) && $data['http'] == 200) {
			unset($ccmd, $cout, $cret);
		#   no longer use tempfile, we can use exec output from memory
		#	$this->JSONFile = $this->RandomFileName();
		#	$ccmd = sprintf('curl -s --compressed --output %s %s', escapeshellarg($this->JSONFile), escapeshellarg($url));
			$ccmd = sprintf('curl -s --compressed --output - %s', escapeshellarg($url));
			exec($ccmd, $cout, $cret);
			$data['body'] = implode("\n", $cout);
			$data['data'] = json_decode($data['body'], true);
		};
		$this->CurrJSON = $data;
		return @is_array($data['data']) ? true : false;
	}



	function ProcessJSONFile() {
		if (!@is_array($this->CurrJSON['data'])) {
			return false;
		};
		if (!@is_array($this->CurrJSON['data']['entries'])) {
			return false;
		};
		$i = 0;
		# Prepare insertion statement
		$ins = $this->dbh->prepare("
			INSERT  INTO gbv_stat SET
			identnum   			= :identnum,
			identrep   			= :identrep,
			`date`     			= :date,
			counter    			= :counter,
			counter_abstract	= :counter_abstract,
			robots				= :robots,
			country				= :country
		");
		$rep = array(); # for counting repositories
		foreach ($this->CurrJSON['data']['entries'] as $X) {
			/* $X holds this: Array
				(
				    [identifier] => oai:psydok.sulb.uni-saarland.de:1025
				    [date] => 2013-04-29
				    [counter] => 0
				    [counter_abstract] => 0
				    [robots] => 2
				)
			*/
			$nc = $X['counter'];
			$na = $X['counter_abstract'];
			$nr = $X['robots'];
			if ($nc + $na + $nr < 1) {
				printf(">\t[%04d] %s  %48s - ---------SKIP--------\n",     $i, $X['date'], $X['identifier']);
			} else {
				printf(">\t[%04d] %s  %48s - [ft:%03d ab:%03d ro:%03d]\n", $i, $X['date'], $X['identifier'], $nc, $na, $nr);
				try {
				$crep = array_pop(array_slice(explode(':', $X['identifier']), 1,1));
				$ins->execute(array(
    		        ':identnum'             => array_pop(explode(':', $X['identifier'])),
       			    ':identrep'             => $crep,
            		':date'                 => $X['date'],
            		':counter'              => $X['counter'],
            		':counter_abstract'     => $X['counter_abstract'],
            		':robots'               => $X['robots'],
            		':country'              => '--'
				));
				@ $rep[$crep]++;
				} catch(PDOException $e){
					printf("ERROR: %s\n", $e->getMessage()); 
				};
			};
			$i++;
		};
		# Prepare filestat statement
		# This is to ensure we don't process the same file more than once
	    # Hash shall be computed from HTTP headers only: filesize and modification date
		$ifs = $this->dbh->prepare("
			REPLACE INTO gbv_stat_files SET
			`file` = :file,
			`date` = :date,
			`mdtm` = :mdtm,
			`hash` = :hash,
			`proc` = NOW()
		");
		$ifs->execute(array(
			':file' => $this->CurrJSON['path'],
			':date' => $this->CurrJSON['data']['from'],
			':mdtm' => $this->CurrJSON['mdtm'],
			':hash' => $this->CurrJSON['hash']
		));
		# printf("IFS: %s\n", var_export($ifs,1));
		# print_r($ifs);
		# print_r($this->dbh->errorInfo());
		$ret = array();
		$ret['total'] = $i;
		$ret['repos'] = $rep;
		return $ret;
	}


	function ProcessJSONFileList($aURL = false) {
		# aURL is an array of URLs to process
		$aURL = $aURL === false ? $this->JsonFileList['urls'] : $aURL;
		if (!is_array($aURL)) {
			printf("ERROR: ProcessJSONFileList: Argument is not an array.\nDump: %s\n--\n", var_export($aURL,1));
		};
		$url_preg = '%https?://.+?:(.+?)@%m';
		foreach ($aURL as $k => $url) {
			# we only want to log URLs with masked password
			$urx = preg_replace_callback($url_preg, "_preg_callback_url", $url);
			$this->Log(sprintf("Get: %s", $urx), null, false);
			$this->GetRemoteJSONFile($url);
			if ($this->CurrJSON['http'] == 200) {
				$this->Log(sprintf("\t> Status: %d (Date: %s; %d Bytes)", $this->CurrJSON['http'], $this->CurrJSON['head']['LAST-MODIFIED'], $this->CurrJSON['head']['CONTENT-LENGTH']));
				if (is_array($this->CurrJSON['seen'])) {
					$this->Log(sprintf("\t> Data already processed on %s.", $this->CurrJSON['seen']['proc']));
				} else {
					$stat = $this->ProcessJSONFile();
					$this->Log(sprintf("\t> Processed %8d records.", $stat['total']));
					foreach ($stat['repos'] as $xk => $xv) {
						$this->Log(sprintf("\t\t>> %16s:%6d records.", $xk, $xv));
					};
				};
				sleep(1); # server load
			} else {
				$this->Log(sprintf("\t> Status: %d (ERROR)", $this->CurrJSON['http']));
			};
			# if ($this->CurrJSON['http'] == 200) break;
		};
	}



}


###############################################################
# MAIN PROGRAM

if (isset($argv[1]) && trim($argv[1])!=='') {
	$cfg = $argv[1];
} else {
	$cfg = dirname(__FILE__) . "/config.php";
};

define("CFG_FILE", $cfg);
chdir(dirname(__FILE__));

if (!file_exists(CFG_FILE)) 	{ printf("Configuration file %s does not exist.\n", CFG_FILE); exit(255); }
if (!is_readable(CFG_FILE)) 	{ printf("Configuration file %s is unreadable..\n", CFG_FILE); exit(254); }

$oas_getjson = new OAS_GetJSON(CFG_FILE);

$oas_getjson->GetFilesArray("2013-04-21");
$oas_getjson->ProcessJSONFileList();

# print_r($oas_getjson);

?>