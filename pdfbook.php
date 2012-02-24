<?php
# Extension:PdfBook
# - Licenced under LGPL (http://www.gnu.org/copyleft/lesser.html)
# - Author: [http://www.organicdesign.co.nz/nad User:Nad]
# - Started: 2007-08-08
 
if (!defined('MEDIAWIKI')) die('Not an entry point.');
 
define('PDFBOOK_VERSION','0.0.11, 2008-05-30');
 
global $PdfBookShowTab, $PdfBookCodePage;	
$PdfBookShowTab		= false;
$PdfBookCodePage	= "";


$dir = dirname(__FILE__) . '/';
require_once($dir.'checkpdfbook.php');
$wgExtensionMessagesFiles['pdfbook'] = $dir.'pdfbook.i18n.php';
$wgPdfBookMagic                = "book";
$wgExtensionFunctions[]        = 'wfSetupPdfBook';
$wgHooks['LanguageGetMagic'][] = 'wfPdfBookLanguageGetMagic';

$wgExtensionCredits['parserhook'][] = array(
	'name'	      => 'Pdf Book',
	'author'      => '[http://www.organicdesign.co.nz/nad User:Nad]',
	'description' => 'Composes a book from articles in a category and exports as a PDF book',
	'url'	      => 'http://www.mediawiki.org/wiki/Extension:Pdf_Book',
	'version'     => PDFBOOK_VERSION
	);

class PdfBook {
 
	# Constructor
	function PdfBook() {
		global $wgHooks,$wgParser,$wgPdfBookMagic;
		$wgParser->setFunctionHook($wgPdfBookMagic,array($this,'magicBook'));
		$wgHooks['UnknownAction'][] = $this;
 
		# Add a new pdf log type
		global $wgLogTypes,$wgLogNames,$wgLogHeaders,$wgLogActions;
		$wgLogTypes[]             = 'pdf';
		$wgLogNames  ['pdf']      = 'pdflogpage';
		$wgLogHeaders['pdf']      = 'pdflogpagetext';
		$wgLogActions['pdf/book'] = 'pdflogentry';
	}
 
	# Expand the book-magic
	function magicBook(&$parser) {
 
		# Populate $argv with both named and numeric parameters
		$argv = array();
		foreach (func_get_args() as $arg) if (!is_object($arg)) {
			if (preg_match('/^(.+?)\\s*=\\s*(.+)$/',$arg,$match)) $argv[$match[1]] = $match[2]; else $argv[] = $arg;
		}
 
		return $text;
	}
 
	function onUnknownAction($action,$article) {
		global $wgOut,$wgUser,$wgTitle,$wgParser;
		global $wgServer,$wgArticlePath,$wgScriptPath,$wgUploadPath,$wgUploadDirectory,$wgScript;
		global $PdfBookCodePage;
 
		if ($action == 'pdfbook') {
 
			# Log the export
			$msg = $wgUser->getUserPage()->getPrefixedText().' exported as a PDF book';
			$log = new LogPage('pdf',false);
			$log->addEntry('book',$wgTitle,$msg);
 
			# Initialise PDF variables
			$layout  = '--firstpage toc';
			$left    = $this->setProperty('LeftMargin',  '1cm');
			$right   = $this->setProperty('RightMargin', '1cm');
			$top     = $this->setProperty('TopMargin',   '1cm');
			$bottom  = $this->setProperty('BottomMargin','1cm');
			$font    = $this->setProperty('Font',	'Arial');
			$size    = $this->setProperty('FontSize',    '8');
			$link    = $this->setProperty('LinkColour',  '217A28');
			$levels  = $this->setProperty('TocLevels',   '2');
			$exclude = $this->setProperty('Exclude',     array());
			if (!is_array($exclude)) $exclude = split('\\s*,\\s*',$exclude);
 
			# Select articles from members if a category or links in content if not
			$articles = array();
			$title    = $article->getTitle();
			$opt      = ParserOptions::newFromUser($wgUser);
			if ($title->getNamespace() == NS_CATEGORY) {
				$db     = &wfGetDB(DB_SLAVE);
				$cat    = $db->addQuotes($title->getDBkey());
	    			$result = $db->query("select cl_from, cl_sortkey, to_title as level from categorylinks left outer join fchw_relation on (from_id = cl_from) where cl_to = $cat and ((relation is null) or (upper(relation) = 'LEVEL')) group by cl_from, cl_sortkey, level");
				//if ($result instanceof ResultWrapper) $result = $result->result;
				while ($row = $db->fetchObject($result)) {
				    $articles[str_pad($row->level, 10, "0", STR_PAD_LEFT)."_".$row->cl_from] = Title::newFromID($row->cl_from);					
				}
    				ksort($articles);
			}
			else {
				$text = $article->fetchContent();
				$text = $wgParser->preprocess($text,$title,$opt);
				if (preg_match_all('/^\\*\\s*\\[{2}\\s*([^\\|\\]]+)\\s*.*?\\]{2}/m',$text,$link))
					foreach ($links[1] as $link) $articles[] = Title::newFromText($link);
			}
 
			# Format the article's as a single HTML document with absolute URL's
			$book	  = $title->getText();
			$html	  = '';
			$wgArticlePath = $wgServer.$wgArticlePath;
			$wgScriptPath  = $wgServer.$wgScriptPath;
			$wgUploadPath  = $wgServer.$wgUploadPath;
			$wgScript      = $wgServer.$wgScript;
			foreach ($articles as $title) {
				$ttext = $title->getPrefixedText();
				if (!in_array($ttext,$exclude)) {
					$article = new Article($title);
					$text    = $article->fetchContent();
					$text    = preg_replace('/<!--([^@]+?)-->/s','@@'.'@@$1@@'.'@@',$text); # preserve HTML comments
					$text   .= '__NOTOC__';
					$opt->setEditSection(false);    # remove section-edit links
					$wgOut->setHTMLTitle($ttext);   # use this so DISPLAYTITLE magic works
					$out     = $wgParser->parse($text,$title,$opt,true,true);
					$ttext   = $wgOut->getHTMLTitle();
					$text    = $out->getText();
					$text    = preg_replace('|(<img[^>]+?src=")(/.+?>)|',"$1$wgServer$2",$text);
					$text    = preg_replace('|@{4}([^@]+?)@{4}|s','<!--$1-->',$text); # HTML comments hack
					$text    = preg_replace('|<table|','<table border borderwidth=2 cellpadding=3 cellspacing=0',$text);
					$ttext   = basename($ttext);
					if ($PdfBookCodePage == "") 	
    					    $html   .= utf8_decode("<h1>$ttext</h1>$text\n");
					  else
					if ($PdfBookCodePage == "iso-8859-2") 	
					    $html   .= UTF8toISO88592("<h1>$ttext</h1>$text\n");
					  else
					    die("Codepage $PdfBookCodePage is not supported");
				}
			}
 
			# If format=html in query-string, return html content directly
			if (isset($_REQUEST['format']) && $_REQUEST['format'] == 'html') {
				$wgOut->disable();
				header("Content-Type: text/html");
				header("Content-Disposition: attachment; filename=\"$book.html\"");
				print $html;
			}
			else {
				# Write the HTML to a tmp file
				$file = "$wgUploadDirectory/pdfbook/".uniqid('pdf-book');
				$fh = fopen($file,'w+');
				fwrite($fh,$html);
				fclose($fh);
 
				$charset = "";
				if ($PdfBookCodePage != "") 
				    $charset = " --charset $PdfBookCodePage ";
 
				# Send the file to the client via htmldoc converter
				$wgOut->disable();
				header("Content-Type: application/pdf");
				header("Content-Disposition: attachment; filename=\"$book.pdf\"");
				$cmd  = "--left $left --right $right --top $top --bottom $bottom";
				//$cmd .= " --header ... --footer .1. --headfootsize 8 --quiet --jpeg --color";
				$cmd .= " --headfootsize 8 --quiet --jpeg --color";
				$cmd .= " --bodyfont $font --fontsize $size --linkstyle plain --linkcolor $link";
				$cmd .= " --toclevels $levels --format pdf14 --numbered $layout";
			        //$cmd  = "htmldoc -t pdf $charset $cmd $file";
	$file2  = str_replace('/', DIRECTORY_SEPARATOR, $file);
	//				echo($cmd);			
	global $PdfBookHtmlDoc;
	if (!isset($PdfBookHtmlDoc)) 
	    $htmldocpath = "htmldoc";
	  else
	    $htmldocpath = $PdfBookHtmlDoc;
	putenv("HTMLDOC_NOCGI=1");
	if (substr(php_uname(), 0, 7) == "Windows") {
	    $cmd  = escapeshellarg($htmldocpath)." -t pdf $charset $cmd -f ".escapeshellarg($file2.".pdf")." ".escapeshellarg($file2)."";
	    $obj = new COM("WScript.Shell");
	    $obj->Run($cmd, 1, true);
	    readfile($file2.".pdf");
	} else {
	    $cmd  = escapeshellarg($htmldocpath)." -t pdf $charset $cmd ".escapeshellarg($file2)."";
	    passthru($cmd);
	}
					@unlink($file2);
			}
			return false;
		}
 
		return true;
	}
 
	# Return a property for htmldoc using global, request or passed default
	function setProperty($name,$default) {
		if (isset($_REQUEST["pdf$name"]))      return $_REQUEST["pdf$name"];
		if (isset($GLOBALS["wgPdfBook$name"])) return $GLOBALS["wgPdfBook$name"];
		return $default;
	}
 
	# Needed in some versions to prevent Special:Version from breaking
	function __toString() { return 'PdfBook'; }
}
 
# Called from $wgExtensionFunctions array when initialising extensions
function wfSetupPdfBook() {
	global $wgPdfBook, $wgHooks, $wgMessageCache, $PdfBookShowTab;
	$wgPdfBook = new PdfBook();
	if ($PdfBookShowTab) {
    	    wfLoadExtensionMessages('pdfbook');
	    $wgMessageCache->addMessage('pdfbook', wfMsg('pdfbook_ExportPDF'));
	    $wgHooks['SkinTemplateContentActions'][] = 'wfPdfBookActionContentHook';
	}
}
 
# Needed in MediaWiki >1.8.0 for magic word hooks to work properly
function wfPdfBookLanguageGetMagic(&$magicWords,$langCode = 0) {
	global $wgPdfBookMagic;
	$magicWords[$wgPdfBookMagic] = array(0,$wgPdfBookMagic);
	return true;
}

# show pdfbok tab on category pages
function wfPdfBookActionContentHook( &$content_actions ) {
    global $wgRequest, $wgRequest, $wgTitle;
    $action = $wgRequest->getText('action');
    global $wgPdfBook;
    if (!isset($wgPdfBook)) 
	return true;
    if ( $wgTitle->getNamespace() != NS_SPECIAL ) {
	if ($wgTitle->getNamespace() == NS_CATEGORY) {
            $content_actions['myact'] = array(
	        'class' => $action == 'pdfbook' ? 'selected' : false,
    	        'text' => wfMsg('pdfbook'),
        	'href' => $wgTitle->getLocalUrl('action=pdfbook')
    	    );
	}
    }
    return true;
}

static $UTF8_ISO88592 = array(
		"\xC3\x81" => "Á",  
		"\xC3\x84" => "Ä",  
		"\xC4\x86" => "Æ",  
		"\xC4\x8C" => "È",  
		"\xC4\x8E" => "Ï",  
		"\xC3\x89" => "É",  
		"\xC4\x9A" => "Ì",  
		"\xC3\x8D" => "Í",  
		"\xC4\xBB" => "Å",  
		"\xC4\xBD" => "¥",  
		"\xC5\x87" => "Ò",  
		"\xC3\x93" => "Ó",  
		"\xC3\x94" => "Ô",  
		"\xC3\x96" => "Ö",  
		"\xC5\x94" => "À",  
		"\xC5\x98" => "Ø",  
		"\xC5\xA0" => "©",  
		"\xC5\xA4" => "«",  
		"\xC3\x9A" => "Ú",  
		"\xC5\xAE" => "Ù",  
		"\xC3\x9C" => "Ü",  
		"\xC3\x9D" => "Ý",  
		"\xC5\xBD" => "®",  
		"\xC3\x9F" => "ß",  
		"\xC3\xA1" => "á",  
		"\xC3\xA4" => "ä",  
		"\xC4\x87" => "æ",  
		"\xC4\x8D" => "è",  
		"\xC4\x8F" => "ï",   
		"\xC3\xA9" => "é",  
		"\xC4\x9B" => "ì",  
		"\xC3\xAD" => "í",  
		"\xC4\xBA" => "å",  
		"\xC4\xBE" => "µ",  
		"\xC5\x88" => "ò",  
		"\xC3\xB3" => "ó",  
		"\xC3\xB4" => "ô",  
		"\xC3\xB6" => "ö",  
		"\xC5\x95" => "à",  
		"\xC5\x99" => "ø",  
		"\xC5\xA1" => "¹",  
		"\xC5\xA5" => "»",  
		"\xC3\xBA" => "ú",  
		"\xC5\xAF" => "ù",  
		"\xC3\xBC" => "ü",  
		"\xC3\xBD" => "ý",  
		"\xC5\xBE" => "¾"  
		);

function UTF8toISO88592($input) {
    global $UTF8_ISO88592;
    return strtr($input, $UTF8_ISO88592);
}