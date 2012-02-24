<?php

$output = "";

## TODO: TEST HTMLDOC
$execTest = false;
$execTestMessage = "Trying";
$file = "C:/Inetpub/wwwroot/mediawiki/images/pdfbook/".uniqid('pdf-book-');
$file = str_replace('/', DIRECTORY_SEPARATOR, $file);
$fh = @fopen($file,'w+');
@fwrite($fh,"<h1>Test</h1>");
@fclose($fh);

$cmd = " --headfootsize 8 --quiet --jpeg --color";
$cmd .= " --format pdf14 ";

$htmldocpath = "C:\\Program Files\\HTMLDOC\\htmldoc.exe";
putenv("HTMLDOC_NOCGI=1");

$execTestMessage="";
try {
    $exec  = escapeshellarg($htmldocpath)." -t pdf $cmd -f ".escapeshellarg($file.".pdf")." ".escapeshellarg($file)."";
    $obj =  new COM("WScript.Shell") or die("Unable to init WScript.Shell for pdf file");
    //$Res = $obj->Run($exec, 1, true);
    // for IIS
    $Res = $obj->Exec("cmd.exe /C ".$exec);
    // On Windows, PHP sometimes does not immediately recognize that the file is there.
    // http://de.php.net/manual/en/function.file-exists.php#56121
} catch (Exception $e) {
    $execTestMessage = "<b>Command:</b><br>".$exec."<br><b>Error:</b><br>".$e->__toString();
}
print ($exec."<br>");
print ($execTestMessage);
?>