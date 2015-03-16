<?php
/***
! User settings
Edit these lines according to your need
***/
//{{{
$AUTHENTICATE_USER = true;	// true | false - disables user authentication
$override_filename = true;  // overrides the given filename with [username].html, does not work without 'AUTHENTICATE_USER'
                            // usernames shall not contain slashes to avoid path traversal
$override_savedir = true;   // i discourage anyone from using user-provided directory names
$savedir = './';            // default save directory (uploadDir)
$override_backupdir = true; // override the given backupdir 
$backupdir = './backup';    // default backup directory
                            
$USERS = array(
	'user1'=>'pass1', 
	'user2'=>'pass2', 
  'user3'=>'pass3'); // set usernames and strong passwords

$DEBUG = false;				// true | false
$CLEAN_BACKUP = true; 		// during backuping a file, remove overmuch backups
$FOLD_JS = true; 			// if javascript files have been expanded during download the fold them
error_reporting(E_ERROR | E_WARNING | E_PARSE);
//}}}
/***
!Code
No change needed under
***/
//{{{

/***
 * store.php - upload a file in this directory
 * version :1.6.1 - 2015/03/16 - makefu@github   
 * 
 * see : 
 *	http://tiddlywiki.bidi.info/#UploadPlugin for usage
 *	http://www.php.net/manual/en/features.file-upload.php 
 *		for details on uploading files
 * usage : 
 *	POST  
 *		UploadPlugin[backupDir=<backupdir>;user=<user>;password=<password>;uploadir=<uploaddir>;[debug=1];;]
 *		userfile <file>
 *	GET
 *
 * each external javascript file included by download.php is change by a reference (src=...)
 *
 * Revision history
 * V1.6.1 - 2015/03/16 
 * Security: - add override_filename to ignore the given filename
 *           -  
 * V1.6.1 - 2007/08/01
 * Enhancement: Add javascript folding
 * V1.6.0 - 2007/05/17
 * Enhancement: Add backup management
 * V1.5.2 - 2007/02/13
 * Enhancement: Add optional debug option in client parameters
 * V1.5.1 - 2007/02/01
 * Enhancement: Check value of file_uploads in php.ini. Thanks to Didier Corbière
 * V1.5.0 - 2007/01/15
 * Correct: a bug in moving uploadFile in uploadDir thanks to DaniGutiérrez for reporting
 * Refactoring
 * V 1.4.3 - 2006/10/17 
 * Test if $filename.lock exists for GroupAuthoring compatibility
 * return mtime, destfile and backupfile after the message line
 * V 1.4.2 - 2006/10/12
 *  add error_reporting(E_PARSE);
 * v 1.4.1 - 2006/03/15
 *	add chmo 0664 on the uploadedFile
 * v 1.4 - 2006/02/23
 * 	add uploaddir option :  a path for the uploaded file relative to the current directory
 *	backupdir is a relative path
 *	make recusively directories if necessary for backupDir and uploadDir
 * v 1.3 - 2006/02/17
 *	presence and value of user are checked with $USERS Array (thanks to PauloSoares)
 * v 1.2 - 2006/02/12 
  *	POST  
 *		UploadPlugin[backupDir=<backupdir>;user=<user>;password=<password>;]
 *		userfile <file>
*	if $AUTHENTICATE_USER
 *		presence and value of user and password are checked with 
 *		$USER and $PASSWORD
 * v 1.1 - 2005/12/23 
 *	POST  UploadPlugin[backupDir=<backupdir>]  userfile <file>
 * v 1.0 - 2005/12/12 
 *	POST userfile <file>
 *
 * Copyright (c) BidiX@BidiX.info 2005-2007
 ***/
//}}}

//{{{

if ($_SERVER['REQUEST_METHOD'] == 'GET') {
	/*
	 * GET Request
	 */
?>
<!DOCTYPE html PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN">
<html>
	<head>
		<meta http-equiv="Content-Type" content="text/html;charset=utf-8" >
		<title>BidiX.info - TiddlyWiki UploadPlugin - Store script</title>
	</head>
	<body>
		<p>
		<p>store.php V 1.6.1
		<p>BidiX@BidiX.info
		<p>&nbsp;</p>
		<p>&nbsp;</p>
		<p>&nbsp;</p>
		<p align="center">This page is designed to upload a <a href="http://www.tiddlywiki.com/">TiddlyWiki<a>.</p>
		<p align="center">for details see : <a href="http://TiddlyWiki.bidix.info/#HowToUpload">TiddlyWiki.bidix.info/#HowToUpload<a>.</p>	
	</body>
</html>
<?php
exit;
}

/*
 * POST Request
 */
	 
// Recursive mkdir
function mkdirs($dir) {
	if( is_null($dir) || $dir === "" ){
		return false;
	}
	if( is_dir($dir) || $dir === "/" ){
		return true;
	}
	if( mkdirs(dirname($dir)) ){
		return mkdir($dir);
	}
	return false;
}

function toExit() {
	global $DEBUG, $filename, $backupFilename, $options;
	if ($DEBUG) {
		echo ("\nHere is some debugging info : \n");
		echo("\$filename : $filename \n");
		echo("\$backupFilename : $backupFilename \n");
		print ("\$_FILES : \n");
		print_r($_FILES);
		print ("\$options : \n");
		print_r($options);
}
exit;
}

function ParseTWFileDate($s) {
	// parse date element
	preg_match ( '/^(\d\d\d\d)(\d\d)(\d\d)\.(\d\d)(\d\d)(\d\d)/', $s , $m );
	// make a date object
	$d = mktime($m[4], $m[5], $m[6], $m[2], $m[3], $m[1]);
	// get the week number
	$w = date("W",$d);

	return array(
		'year' => $m[1], 
		'mon' => $m[2], 
		'mday' => $m[3], 
		'hours' => $m[4], 
		'minutes' => $m[5], 
		'seconds' => $m[6], 
		'week' => $w);
}

function cleanFiles($dirname, $prefix) {
	$now = getdate();
	$now['week'] = date("W");

	$hours = Array();
	$mday = Array();
	$year = Array();
	
	$toDelete = Array();

	// need files recent first
	$files = Array();
	($dir = opendir($dirname)) || die ("can't open dir '$dirname'");
	while (false !== ($file = readdir($dir))) {
		if (preg_match("/^$prefix/", $file))
        array_push($files, $file);
    }
	$files = array_reverse($files);
	
	// decides for each file
	foreach ($files as $file) {
		$fileTime = ParseTWFileDate(substr($file,strpos($file, '.')+1,strrpos($file,'.') - strpos($file, '.') -1));
		if (($now['year'] == $fileTime['year']) &&
			($now['mon'] == $fileTime['mon']) &&
			($now['mday'] == $fileTime['mday']) &&
			($now['hours'] == $fileTime['hours']))
				continue;
		elseif (($now['year'] == $fileTime['year']) &&
			($now['mon'] == $fileTime['mon']) &&
			($now['mday'] == $fileTime['mday'])) {
				if (isset($hours[$fileTime['hours']]))
					array_push($toDelete, $file);
				else 
					$hours[$fileTime['hours']] = true;
			}
		elseif 	(($now['year'] == $fileTime['year']) &&
			($now['mon'] == $fileTime['mon'])) {
				if (isset($mday[$fileTime['mday']]))
					array_push($toDelete, $file);
				else
					$mday[$fileTime['mday']] = true;
			}
		else {
			if (isset($year[$fileTime['year']][$fileTime['mon']]))
				array_push($toDelete, $file);
			else
				$year[$fileTime['year']][$fileTime['mon']] = true;
		}
	}
	return $toDelete;
}

function replaceJSContentIn($content) {
	if (preg_match ("/(.*?)<!--DOWNLOAD-INSERT-FILE:\"(.*?)\"--><script\s+type=\"text\/javascript\">(.*)/ms", $content,$matches)) {
		$front = $matches[1];
		$js = $matches[2];
		$tail = $matches[3];
		if (preg_match ("/<\/script>(.*)/ms", $tail,$matches2)) {		
			$tail = $matches2[1];
		}
		$jsContent = "<script type=\"text/javascript\" src=\"$js\"></script>";
		$tail = replaceJSContentIn($tail);
		return($front.$jsContent.$tail);
	}
	else
		return $content;
}

// Check if file_uploads is active in php config
if (ini_get('file_uploads') != '1') {
   echo "Error : File upload is not active in php.ini\n";
   toExit();
}

// var definitions
$uploadDirError = false;
$backupError = false;
$optionStr = $_POST['UploadPlugin'];
$optionArr=explode(';',$optionStr);
$options = array();
$backupFilename = '';
$filename = basename($_FILES['userfile']['name']);
// fix for path traversal
$destfile = $filename;

// get options
foreach($optionArr as $o) {
	list($key, $value) = split('=', $o);
	$options[$key] = $value;
}

// debug activated by client
if ($options['debug'] == 1) {
	$DEBUG = true;
}

// authenticate User
if (($AUTHENTICATE_USER)
	&& ((!$options['user']) || (!$options['password']) || ($USERS[$options['user']] != $options['password']))) {
	echo "Error : UserName or Password do not match \n";
	echo "UserName : [".$options['user']. "] Password : [". $options['password'] . "]\n";
	toExit();
}
// $options['user'] must match a name in the array

if ($override_filename) {
  $filename = $options['user'] . ".html";
}



// ignoring whatever uploaddir is set, defaulting to cwd
if ($override_savedir){
  $uploadDir=$savedir;
}elseif ( ($options['uploaddir']) ) {
	$uploadDir = $options['uploaddir'];
	// path control for uploadDir   
  if (!(strpos($uploadDir, "../") === false)) {
    echo "Error: directory to upload specifies a parent folder";
    toExit();
	}
	if (! is_dir($uploadDir)) {
		mkdirs($uploadDir);
	}
	if (! is_dir($uploadDir)) {
		echo "UploadDirError : $uploadDirError - File NOT uploaded !\n";
		toExit();
  }
  if ($uploadDir{0} == '/') {
    echo "no absolute path can be used for $uploaddir - file NOT uploaded!\n";
    toExit();
  }
	if ($uploadDir{strlen($uploadDir)-1} != '/') {
		$uploadDir = $uploadDir . '/';
	}
}

$destfile = $uploadDir . $filename;

// ignoring backup directory given
if ($override_backupdir) {
  $bdir= $backupdir;
}else{
  $bdir= $options['backupDir'];
    if (!(strpos($bdir, "../") === false)) {
        echo "BackupDirError: directory to upload specifies a parent folder";
        toExit();
	}
	if (! is_dir($bdir)) {
		mkdirs($bdir);
	}
	if (! is_dir($bdir)) {
		echo "BackupDirError : $bdir - File NOT uploaded !\n";
		toExit();
  }
  if ($bdir{0} == '/') {
    echo "BackupDirError : no absolute path can be used for $bdir - file NOT uploaded!\n";
    toExit();
  }
	if ($bdir{strlen($bdir)-1} != '/') {
		$bdir = $bdir . '/';
	}
}

// backup existing file

if (file_exists($destfile) && ($bdir)) {
  // i discourage anyone from using this function with user_provided directory name
	if (! is_dir($bdir)) {
		mkdirs($bdir);
		if (! is_dir($bdir)) {
			$backupError = "backup mkdir error";
		}
	}
	$backupFilename = $bdir.'/'.substr($filename, 0, strrpos($filename, '.'))
				.date('.Ymd.His').substr($filename,strrpos($filename,'.'));
	rename($destfile, $backupFilename) or ($backupError = "rename error");
	// remove overmuch backup
	if ($CLEAN_BACKUP) {
		$toDelete = cleanFiles($bdir, substr($filename, 0, strrpos($filename, '.')));
		foreach ($toDelete as $file) {
			$f = $bdir.'/'.$file;
			if($DEBUG) {
				echo "delete : ".$bdir.'/'.$file."\n";
			}
			unlink($bdir.'/'.$file);
		}
	}
}

// move uploaded file to uploadDir
if (move_uploaded_file($_FILES['userfile']['tmp_name'], $destfile)) {
	if ($FOLD_JS) {
		// rewrite the file to replace JS content
		$fileContent = file_get_contents ($destfile);
		$fileContent = replaceJSContentIn($fileContent);
		if (!$handle = fopen($destfile, 'w')) {
	         echo "Cannot open file ($destfile)";
	         exit;
	    }
	    if (fwrite($handle, $fileContent) === FALSE) {
	        echo "Cannot write to file ($destfile)";
	        exit;
	    }
	    fclose($handle);
	}
    
	chmod($destfile, 0644);
	if($DEBUG) {
		echo "Debug mode \n\n";
	}
	if (!$backupError) {
		echo "0 - File successfully loaded in " .$destfile. "\n";
	} else {
		echo "BackupError : $backupError - File successfully loaded in " .$destfile. "\n";
	}
	echo("destfile:$destfile \n");
	if (($backupFilename) && (!$backupError)) {
		echo "backupfile:$backupFilename\n";
	}
	$mtime = filemtime($destfile);
	echo("mtime:$mtime");
} 
else {
	echo "Error : " . $_FILES['error']." - File NOT uploaded !\n";

}
toExit();
//}}}
?>
