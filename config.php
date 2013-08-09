<?PHP
/* config.php -- part of OAS JSON stats
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


$C = array(

	// Use <http://meyerweb.com/eric/tools/dencoder/> to properly 
	// encode credentials which are passed in the URL, 
	// i.e. <http://username:password@host/>

	// Base path to the host and its directory where JSON files reside
	// This string will be passed to PHP's `strftime` function to
	// interpret date-based placeholders. Examples:
	// 	%Y for  year in format 2013
	// 	%m for month in format 05
	// 	%d for   day in format 07
	// See: <http://php.net/strftime>
	// BEWARE: '%' characters MUST be escaped as '%%'

	"BaseURL"	=> "http://_user_:_password_@oase.gbv.de/_repopath_/%Y/%m/",
	
	// File schema to use when looking for files
	// Uses PHP's `sscanf` function, but will not be used any further
	// for brute force file searching or the like. <http://php.net/sscanf>
	// It is only used to find files listed in the directory's index.html!
	// Example: The actual file name is 	2013-04-24_2013-04-24.json
	//	 ...so the sscanf string is	%04d-%02d-%02d_%04d-%02d-%02d.json
	"FileStr"	=> "%04d-%02d-%02d_%04d-%02d-%02d.json",

	// IMPORTANT! 2013-05-02 -- changed our minds
	// Now is a strftime string!
	"FileStr"	=> "%Y-%m-%d_%Y-%m-%d.json",

	// Database connection. This program uses PDO
	# "db"		=> 'mysql:host=localhost;dbname=oas_data_provider_demo',
	"db"		=> 'mysql:host=localhost;dbname=_dbname_',
	"db_user"	=> '_dbuser_',
	"db_pass"	=> '_dbpasswd_',

	// Timezone MUST be configured, otherwise PHP will complain.
	// See: <http://www.php.net/manual/en/timezones.php>
	"Timezone"	=> 'Europe/Berlin',

	// Set this to 1 after you have come here to customize things!
	"CheckCf"	=> 1
);

?>