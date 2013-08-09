<?PHP
/**
 * Tool for providing usage statistics on library objects
 *
 *		Data is provided as a JSON string.
 *
 *	CHANGES -------------------------------------------------------------
 *
 *  2013-08-01 jb   Added referer check. See comment below.
 *  2013-07-18 jb	Added grouping by months:  hit counts will be summed
 *					and grouped by months if the unit is month or year;
 *                  days and weeks are left untouched.
 *
 *  ---------------------------------------------------------------------
 *
 * @author James Barrante <jb@fcgh.net> for SULB Saarbruecken
 * @package sulb-stat
 * @subpackage main
 * @version 0.90  barrantj 2013-04-17
 * @version 0.91  barrantj 2013-07-18
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
$id = @ trim($_REQUEST['id']);
###########################################################
# REFERER CHECKING.  You may add PCRE regular expressions
# as string to this array.  If this array is non-empty
# AND no match is found in the entire loop, the script
# will assume a referer-error and exit.
#
# [DE]    Regulaere Ausdruecke in PCRE-Format koennen in
# in diesen Array aufgenommen werden.  Wenn dieser Array
# nicht leer ist UND beim Durchlauf des Arrays im Abgleich
# mit dem HTTP-Referer-Header KEIN Treffer erzielt wird,
# bricht dieses Skript ab und erzeugt eine Fehlermeldung.
$rf = array();
# example:
# $rf[] = '%\.sulb\.uni-saarland.de\/frontdoor\.php\?source_opus\=\d+?%'

###########################################################
# @header("Content-Type: application/json; charset=utf-8");
@header("Content-Type: text/plain; charset=utf-8");

if ($id == '') {
	exit(); # no ID supplied, die
}

if (is_array($rf) && sizeof($rf) > 0) {
	$rm = false;
	foreach ($rf as $referer) {
		if (preg_match($referer, $_SERVER['HTTP_REFERER']) > 0) {
			$rm = true;
			break;
		};
	};
	if ($rm === false) {
		header("Status: 400 Bad Request");
		header("X-Error: Invalid Referer supplied!");
		exit();
	};
};

        /**
		 * Auxiliary function for pretty-printing JSON (lack of built-in in PHP < 5.4)
         * http://www.daveperrett.com/articles/2008/03/11/format-json-with-php/
         * Indents a flat JSON string to make it more human-readable.
         * @param string $json The original JSON string to process.
         * @return string Indented version of the original JSON string.
         */
        function indentJson($json) {
            $result      = '';
            $pos         = 0;
            $strLen      = strlen($json);
            $indentStr   = '  ';
            $newLine     = "\n";
            $prevChar    = '';
            $outOfQuotes = true;
            for ($i=0; $i<=$strLen; $i++) {
                // Grab the next character in the string.
                $char = substr($json, $i, 1);
                // Are we inside a quoted string?
                if ($char == '"' && $prevChar != '\\') {
                    $outOfQuotes = !$outOfQuotes;
                // If this character is the end of an element,
                // output a new line and indent the next line.
                } else if(($char == '}' || $char == ']') && $outOfQuotes) {
                    $result .= $newLine;
                    $pos --;
                    for ($j=0; $j<$pos; $j++) {
                        $result .= $indentStr;
                    }
                }
                // Add the character to the result string.
                $result .= $char;
                // If the last character was the beginning of an element,
                // output a new line and indent the next line.
                if (($char == ',' || $char == '{' || $char == '[') && $outOfQuotes) {
                    $result .= $newLine;
                    if ($char == '{' || $char == '[') {
                        $pos ++;
                    }

                    for ($j = 0; $j < $pos; $j++) {
                        $result .= $indentStr;
                    }
                }
                $prevChar = $char;
            }
            return $result;
        }



class oasUsageStat {

	function __construct($arrCfg) {
		// Setup base output variable, which will _ALWAYS_
		// be printed as JSON by __destruct function
		// except on unhandled exception^^
		$this->output = array();
		$this->output['title'] = sprintf("SULB OAS Statistics -- Generated %s", date("r"));
		$this->output['error'] = array();
		$this->output['debug'] = array();
		$this->output['data']  = false;
		# try to read configuration file
		$this->cf = $arrCfg['cfg_file'];
		if (!is_file($this->cf)) {
			$this->output['error'][] = sprintf("Provided path to Configuration file is no file :  `%s`", $this->cf);
		}
		if (!is_readable($this->cf)) {
			$this->output['error'][] = sprintf("Provided path to Configuration file not readable: `%s`", $this->cf);
		}
		# If this file contains an error, we are still lucky
		# that our __destruct function will be called.
		$this->config = false;
		require_once($this->cf);
		if (is_array($C) && $C['CheckCf'] === 1) {
			$this->config = $C;
		}
		// Set user variables and setup database connection
		$this->setID($arrCfg['id']);
		$this->setNum ($arrCfg['rn']);
		$this->setUnit($arrCfg['ru']);
		# Database connection
        try {
			$this->dbh = new PDO(
						$this->config['db'], 
						$this->config['db_user'], 
						$this->config['db_pass'], 
						array(
                        	PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES utf8'
            ));
        } catch (Exception $e) {
                $this->output['error'][] = sprintf("ERROR: Unable to establish database connection.\n\tDSN: '%s'\n\tUser: '%s'\n\tPass: {%s}\nDetails: %s\n", 
					$C['db'], $C['db_user'], str_repeat('*', strlen($C['db_pass'])), $e
				);
                exit(8);
        };
		# Set default time zone, else PHP will complain m(
		date_default_timezone_set($this->config['Timezone']);
#		$this->output['debug']['config'] = $this->config;
		# Get Statistics
		$this->getStat();
	}


	function usage() {
		$this->output['error']['Usage'][] = sprintf("Usage: ?id=<id>&u=<day|week|month|year|all>&n=<{int_count}>\n");
		$this->output['error']['Usage'][] = sprintf("Example: ?id=2007:152&u=day&n=30");
	}


	function setID($str) {
		$this->statID = $str;
		return $this;
	}
	function setUnit($str) {
		$str = strtolower($str);
		$this->statDays = $this->statNum;
		switch ($str) {
			case 'day':		$this->statUnit = $str;  $this->statDays*= 1; break; # WARNING: uses days   as unit
			case 'week':	$this->statUnit = $str;  $this->statDays*= 7; break; # WARNING: uses days   as unit
			case 'month':	$this->statUnit = $str;  $this->statDays*=31; break; # WARNING: uses months as unit
			case 'year':	$this->statUnit = $str;  $this->statDays*=366;break; #                 "
			case 'all':		$this->statUnit = $str;  $this->statDays =-1; break; #                 "
			default:
				$this->output['error'][] = ":setUnit: FATAL Error: Invalid string supplied for `unit` parameter.";
				$this->usage();
				exit(-16);
		}
		return $this;
	}
	function setNum($str) {
		if (is_numeric($str) && floor($str) != $str) {
			$this->output['error'][] = ":setNum: Warning: `$str` - only integers will be handled correctly for the `n` parameter.";
			$this->usage();
		};
		if (is_numeric($str)) {
			$this->statNum = intval($str);
		} else {
			$this->output['error'][] = ":setNum: Warning: `$str` - not an integer. Replaced with `1`.";
			$this->statNum = 1;
		};
		return $this;
	}



	function getStat() {
		# Query DB
		# Extra filters
		if ($this->statDays > 0) {
			# Change 2013-07-18: Returns days only smaller units according to request from Cornelius
			switch ($this->statUnit) {
				case 'day':
				case 'week':
					$df = ''; # Additional fields
					$dg = ''; # GROUP BY 
					$dw = sprintf('AND `date` >= (CURDATE() - INTERVAL %d DAY  )', $this->statDays); # WHERE
				break;
				default:
					$df = " ,
							SUM(`counter`) 				`counter`,
							SUM(`counter_abstract`) 	`counter_abstract`,
							SUM(`robots`) 				`robots`,
							DATE_FORMAT(`date`, '%Y-%m') `date`
					"; # Additional fields
					$dg = "GROUP BY date_format(`date`, '%Y-%m')"; # GROUP BY 
					$dw = sprintf('AND `date` >= (CURDATE() - INTERVAL %d MONTH)', $this->statNum);
			};
		} else {
			# no additional WHERE clause
			$dw = '';
		};
		$ds = $this->dbh->prepare(sprintf("
				SELECT * %s FROM gbv_stat
				WHERE 
				    identnum = :idnum
				AND identrep = :idrep
				%s
				%s
				ORDER BY `date` ASC
		", $df, $dw, $dg));
		$idrep = implode(':', array_slice(explode(':', $this->statID), 1,1));
		$idnum = array_pop(explode(':', $this->statID));
		$ds->execute(array(
            ':idnum' => $idnum,
            ':idrep' => $idrep
        ));
		$nr = $this->dbh->query('SELECT FOUND_ROWS() AS Count')->fetchColumn();
	#	$this->output['debug']['getStat:Query'] = sprintf("Query: %s", print_r($ds->queryString,1));
#		printf("/* Query: \n%s\n*/\n", print_r($ds->queryString,1));
	#	$this->output['debug']['getStat:ID']    = $this->statID;
	#	$this->output['debug']['getStat:IDnum'] = $idnum;
    #	$this->output['debug']['getStat:IDrep'] = $idrep;
	#	$this->output['debug']['getStat:Days']  = $this->statDays;
	#	$this->output['debug']['getStat:ErrD']  = htmlspecialchars($this->dbh->error);
	#	$this->output['debug']['getStat:ErrS']  = htmlspecialchars($ds->error);
	#	$this->output['debug']['getStat:Rows']  = $nr;
		if ($nr > 0) {
			$this->output['data'] = array();
		};
		while ($row = $ds->fetch(PDO::FETCH_ASSOC)) {
			$x = $row;
			unset($x['date']);
			unset($x['identifier']);
			unset($x['identnum']);
			unset($x['identrep']);
			$this->output['data'][$row['date']] = $x;
		};
	}



	function __destruct() {
		// We cannot use the PRETTY_PRINT option with PHP < 5.4
		// Error checking on configuration also takes place here!
		if (!is_array($this->config) || $this->config['CheckCf'] !== 1) {
			$this->output['error'][] = sprintf("FATAL ERROR: Please check your configuration file `%s` for PHP errors.", $this->cf);
		};
		print indentJson(json_encode($this->output));
	}

} # endClassDef oasUsageStat


# ---------------------------------------------------------------------
# ---------------------------------------------------------------------
# ---------------------------------------------------------------------
# ---------------------------------------------------------------------
# ---------------------------------------------------------------------

$oasUStat = new oasUsageStat(array(
	"id"       => trim($_REQUEST['id']), 	// identifier
	"ru"       => trim($_REQUEST['u']),  	// unit [day|month|year|...]
	"rn"       => trim($_REQUEST['n']),  	// integer range
	"cfg_file" => "./config.php"			// Location of configuration file providing database DSN
));


