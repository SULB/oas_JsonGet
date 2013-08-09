<?PHP
/* config_subrepo.php -- part of OAS JSON stats
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

/*
 *  include base configuration first
 */
require("config.php");
/* 
 * Change the the original repo path to the actual 'subrepo'
 */
$C["BaseURL"] = "http://_user_:_password_@oase.gbv.de/_repopath_/_subrepopath_/%Y/%m/";

?>