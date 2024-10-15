<?php
/* TODO
//include properties
historique des PJs, restauration?
themes
macros : html, ??
txt2tags
images size
configuration
//*/
$_sw['login']='changethis';
$_sw['pwd']='changethis';
$_sw['title']='WikiTitle';
$_sw['default']='Accueil';
@include(dirname(__FILE__).'/inc.php');//include a side file (storing parameters for instance)
/***************************************/
$a = array();
preg_match('~^([:\.]?[\w \',!]+)/?([\w\.]*)/?([\d-]*)/?(.*)~', urldecode($_SERVER['QUERY_STRING']), $a); // $page/$mode/$version/$action, leading ':' for special pages
$p = count($a)>0?$a[1]:$_sw['default'];$p=ucfirst($p);//page
$p = preg_replace('/^_/',':',$p);//editable special pages dirty fix
$m = count($a)>1?$a[2]:'';//mode (can be '', 'edit' or 'history')
$v = count($a)>2?$a[3]:'';//version
$d = count($a)>3?$a[4]:'';//action (can be 'set' or 'delete')
$s = (basename(__FILE__)=='index.php')?'':basename(__FILE__);

if(isset($_COOKIE['PHPSESSID']) || $m=='edit' || $m=='history') // we only want a session if we already have one of if we need one.
	session_start();

//echo $p;

/* special pages *************************/
if(substr($p,0,1)==':') { //special pages
	if ($p==':all') {
		swSpecialAll();
	}
	if ($p==':last') {
		swSpecialLast();
	}
	if ($p==':blog') {
		swSpecialBlog();
	}
	if ($p==':rss') {
		swSpecialRss();
	}
	if ($p==':search') {
		swSpecialSearch();
	}
	die;
}
/* standard pages *************************/
if ($m=='') {
	swDisplay();
} elseif ($m=='edit') {//edit
	swEdit();
} elseif ($m=='history') { //history
	swHistory();
}
else {//pj?
	$pjs=swGetAttachedFiles($p);
	if(isset($pjs[$p.'/'.$m])) {
		header('Location: '.'./pages/'.$p.'/'.$m.'.'.$pjs[$p.'/'.$m][0]);
	}
}
/* functions **************************/
function txt2html($txt,$page='') {
	global $s,$p,$cp,$fn,$fnc;//,$disqus;
	$cp=$page;
	$r='';
	$txt = htmlentities($txt);
	$lines = explode("\n",$txt);
	$previous = '';
	$emptyprev=true;
	$fnc=0;
	$fn='';
	foreach($lines as $line) {
		$line=str_replace("\r",'',$line);
		if (substr($line, 0, 1)==' ') {$current = 'pre';$line=substr($line,1);}
		elseif (substr($line, 0, 1)=='*') {$current = 'ul';$line=substr($line,1);}
		elseif (substr($line, 0, 1)=='#') {$current = 'ol';$line=substr($line,1);}
		elseif (substr($line, 0, 1)=='\\') {$current = 'p';$line=substr($line,1);}
		elseif (substr($line, 0, 4)=='&gt;') {$current = 'quote';$line=substr($line,4);}
		else $current='p';

		if ($previous=='')
			$r.= '<'.$current.'>';
		if ($current!=$previous && $previous != '')
			$r.= '</'.$previous.'>'."\n".'<'.$current.'>'."\n";
		if ($line=='' && !$emptyprev) {
			$r.= '</'.$previous.'>'."\n".'<'.$current.'>';
			$emptyprev = true;
		}
		if($line!='') $emptyprev=false;
		$previous=$current;
		
		if ($current!='pre') {
			$line = preg_replace('/[ ]+([:?!;](\s|$))/','&nbsp;$1',$line);
			$line = preg_replace('/1(st|er|&egrave;re|ere)\b/','1<sup>$1</sup>',$line);
			$line = preg_replace('/2(nd|&egrave;me|eme)\b/','2<sup>$1</sup>',$line);
			$line = preg_replace('/3(rd|&egrave;me|eme)\b/','3<sup>$1</sup>',$line);
			$line = preg_replace('/([014-9])(th|&egrave;me|eme)\b/','$1<sup>$2</sup>',$line);
			$line = preg_replace('/^[-=]{4}[-= ]*$/U', '<hr/>', $line);
			$line = preg_replace('/--(.*)--/U', '<del>$1</del>', $line);
			$line = preg_replace('/\+\+(.*)\+\+/U', '<ins>$1</ins>', $line);
			$line = preg_replace("/''(.*)''/U", '<em>$1</em>', $line);
			$line = preg_replace('/__(.*)__/U', '<strong>$1</strong>', $line);
			$line = preg_replace('/@@(.*)@@/U', '<code>$1</code>', $line);
			$line = preg_replace('/{{(.*)}}/U', '<q>$1</q>', $line);
			$line = preg_replace('/!!!(.*)!!!/U', '</'.$previous.'>'."\n".'<h2>$1</h2>'."\n".'<'.$current.'>', $line);
			$line = preg_replace('/!!(.*)!!/U', '</'.$previous.'>'."\n".'<h3>$1</h3>'."\n".'<'.$current.'>', $line);
			$line = preg_replace('/!(.*)!/U', '</'.$previous.'>'."\n".'<h4>$1</h4>'."\n".'<'.$current.'>', $line);
			$line = preg_replace('/\?\?([^\|]+)\|(.*)\?\?/U', '<acronym title="$2">$1</acronym>', $line);
			$line = preg_replace_callback('/\$\$(.*)\$\$/U','footnote',$line);
			$line = preg_replace_callback('/\(\(([^|]+\.(png|jpg|gif))\|?(.*)\)\)/Ui', 'img', $line);//TODO jpg and ?? external img??
			$line = preg_replace_callback('/\[([^\|]+)(\|.*)?\]/U', 'url', $line);
		}
		if ($current=='ul' || $current=='ol') $r.= '	<li>'.$line.'</li>'."\n";
		else $r.= ' '.$line."\n";
	}
	$r.= '</'.$previous.'>'."\n";
	if ($fn!='') $r.='<ul id="fn">'.$fn.'</ul>';
	//if ($disqus!='') $r.=$disqus;
	return $r;
}
function img($m) {
	global $cp;
	if(!preg_match('~https?://~',$m[1])) {//internal picture
		if(strpos($m[1],'/')) list($p,$img)=explode('/',$m[1]);
		else {global $p;$img=$m[1];}
		if($cp!='') $p=$cp;
		$pjs=swGetAttachedFiles($p);
		return '<a class="img" alt="'.substr($m[3],1).'" title="'.substr($m[3],1).'" href="./pages/'.$p.'/'.$img.'.'.$pjs[$p.'/'.$img][0].'">'.
					'<img alt="'.substr($m[3],1).'" title="'.substr($m[3],1).'" src="./pages/'.$p.'/'.$img.'.'.$pjs[$p.'/'.$img][0].'" style="max-height:;max-width:"/>'.
				'</a>';
	} else {// external picture
		return '<img alt="'.substr($m[2],1).'" title="'.substr($m[2],1).'" src="'.$m[1].'"/>';
	}
}
function url($m) {
	global $s,$p;
	if(isset($m[2])) {$url=substr($m[2],1);$title=$m[1];}//title present
	else $url=$title=$m[1];
	if (preg_match('/^#/',$url)) $url=$p.$url;
	if(preg_match('~^([:\.]?[\w \'!#\./]+)$~',$url)) $url=$s.'?'.$url;
	return '<a href="'.$url.'">'.$title.'</a>';
}
function footnote($m) {
	global $fn,$fnc;
	$fnc++;
	$fn.='<li><sup><a href="#fno'.$fnc.'">'.$fnc.'</a></sup> <a name="fn'.$fnc.'">'.$m[1].'</a></li>';
	return '<sup><a href="#fn'.$fnc.'" name="fno'.$fnc.'">'.$fnc.'</a></sup>';
}
function swSave($p,$c) {
	if (!file_exists(dirname(__FILE__).'/pages'))
		mkdir(dirname(__FILE__).'/pages');
	if (!file_exists(dirname(__FILE__).'/pages/'.$p))
		mkdir(dirname(__FILE__).'/pages/'.$p);
	$gz = gzopen(dirname(__FILE__).'/pages/'.$p.'/'.date('Ymd-His').'.gz','w9');
	gzwrite($gz, $_POST['c']);
	gzclose($gz);
}
function swGet($p,$v='') {
	if($v!='') $c = implode("", gzfile(dirname(__FILE__).'/pages/'.$p.'/'.$v.'.gz'));
	else {
		$a = glob(dirname(__FILE__).'/pages/'.$p.'/*.gz');
		if(count($a)>0) {
			rsort($a);
			$c = implode("", gzfile($a[0]));
		}
		else $c='';
	}
	return stripslashes($c);
}
function swSet($p,$v) {///restore an old version
	$d=dirname(__FILE__).'/pages/'.$p.'/';
	copy($d.$v.'.gz',$d.date('Ymd-His').'.gz');
}
function swDel($p,$v) {//delete a version
	global $s;
	$d=dirname(__FILE__).'/pages/'.$p.'/';
	unlink($d.$v.'.gz');
	if (count(scandir($d))==2) rmdir($d);
	header('Location: '.$s.'?'.$p.'/history');
}
function swVersions($p) {
	$a = glob(dirname(__FILE__).'/pages/'.$p.'/*.gz');
	$a = array_map('removeGz',array_map('basename', $a));
	rsort($a);
	return $a;
}
function removeDirname($str) {
	return str_replace(dirname(__FILE__).'/pages/','',$str);
}
function removeGz($str) {return str_replace('.gz','',$str);}
function swFooter() {
	global $s,$p, $_sw;
	echo '<ul id="footer">'."\n";
	echo '<li><a href="./'.$s.'">'.$_sw['title'].'</a></li>'."\n";
	if (isAuth() && substr($p,0,1)!=':'){
		echo '<li><a href="'.$s.'?'.$p.'/history">history</a></li>'."\n";
	}
	echo '<li><a href="'.$s.'?'.':all">all</a></li>'."\n";
	echo '<li><a href="'.$s.'?'.':last">last</a></li>'."\n";
	// echo '<li><a href="'.$s.'?'.':blog">blog</a></li>'."\n";
	echo '<li><form method="post" action="'.$s.'?:search"><input type="text" name="q" value="'.(isset($_POST['q'])?$_POST['q']:'').'"/></form></li>'."\n";
	if (substr($p,0,1)!=':'){
		echo '<li><a class="hidden" href="'.$s.'?'.$p.'/edit">edit</a></li>'."\n";
    } else {
		echo '<li><a class="hidden" href="'.$s.'?new/edit">new</a></li>'."\n";
    }
	echo '</ul>';
	echo '</body></html>';
}
function swPages() {
	$pages=glob(dirname(__FILE__).'/pages/*', GLOB_ONLYDIR);
	$pages = array_map('removeDirname',$pages);
	return $pages;
}
function swAuth() {// TODO better looking
	global $_sw,$s,$p;
	$form='<form method="post" action="'.$s.'?'.$p.'/edit">Username: <input type="text" name="login"/><br/>Password: <input type="password" name="pwd"/><br/><input type="submit"/></form>';
	if (isset($_POST['login']) && $_POST['login']==$_sw['login'] && $_POST['pwd']==$_sw['pwd'] && $_sw['pwd']!='changethis')
		$_SESSION['user'] = $_sw['login'];
	if(!isset($_SESSION['user']) || $_SESSION['user']!=$_sw['login']) {
	?>
	<!doctype html>
	<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="fr" lang="fr">
	<head>
	<title>Please authenticate</title>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	</head>
	<body>
	<?php echo $form;?>
	</body>
	<?php
		die;
	}
}
function isAuth() {
	global $_sw;
	return (isset($_SESSION['user']) && $_SESSION['user']==$_sw['login']);
}
function swHeader($title='') {
	global $p,$m,$s;
	$title=$title==''?$p.($m!=''?' - '.$m:''):$title;
	//TODO : css color variables
	//TODO : dark theme
	$color='449';//bleu
	$color='E70';//orange
	//$color='944';//rouge
	//$color='494';//vert
?>
<!doctype html>
<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="fr" lang="fr">
<head>
<title><?php echo $title; ?></title>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link rel="alternate" type="application/rss+xml" title="RSS 2.0" href="<?php echo $s; ?>?:rss" />
<style>
	body {color: #666;font-family: Georgia, 'Times New Roman', Times, serif;width: 80%;margin: 2em auto 2em;padding: 1em;border: 1px solid #<?php echo $color; ?>;}
	a[href] {color: #666;text-decoration: underline;}
	a[href]:hover {text-decoration: none;}
	quote {border-left: 0.3em solid #CCC;margin: 0.5em; padding: 0.3em;}
	form .br {text-align: center;}
	textarea {width: 95%; height: 20em;margin: auto;display: block;font-family: Georgia, 'Times New Roman', Times, serif;}
	form input[type=submit], form input[type=file], form button {outline:none; background-color: #fff;color: #<?php echo $color; ?>;padding: 0.2em;margin:0.2em;border: 1px solid #<?php echo $color; ?>;border-radius:0.3em;}
	form input[type=submit]:hover, form input[type=submit]:focus, form button:focus, input[type=file]:focus {background-color: #<?php echo $color; ?>;color:#fff;}
	#fn {border-top: 1px solid #<?php echo $color; ?>;padding-top: 1em;}
	#fn li {list-style: none;font-size: 70%;}
	#history {border-top: 1px solid #<?php echo $color; ?>;padding-top: 1em;}
	#history li.current {font-style: italic;}
	#footer {font-size: 80%;text-align: center;border-top: 1px solid #<?php echo $color; ?>;padding-top: 1em;}
	#footer li {display: inline; list-style: none;padding: 1em;}
	#footer a {color: #<?php echo $color; ?>;}
	#footer form {display: inline;}
	#search li span{color: #999;padding-left: 1em;}
	h1, h1 a[href], h2, h3, h4 {color: #<?php echo $color; ?>;letter-spacing: 0.1em;font-weight:normal;}
	form h1 input {width: 95%; margin:auto;letter-spacing: 0.1em;font-weight:normal;font-size:1em;font-family: Georgia, 'Times New Roman', Times, serif;}
	.hidden{display:none;}
	#footer a[href].hidden {display:inline;color:#fff}
	#footer:hover a[href].hidden {color:#<?php echo $color; ?>;}
	ul#footer {padding-left:0;}
</style>
</head>
<body>
<?php
}
function FormatRfc1123Date($strDate)
{
	$strYear = substr($strDate, 0, 4);
	$strMonth = substr($strDate, 4, 2);
	$strDay = substr($strDate, 6, 2);
	$strHour = substr($strDate, 9, 2);
	$strMinute = substr($strDate, 11, 2);
	$strSecond = substr($strDate, 13, 2);
	$date = mktime($strHour, $strMinute, $strSecond, $strMonth, $strDay, $strYear);
	return gmdate('D, d M Y H:i:s', $date) . ' GMT';
}
function FormatReadableDate($strDate)
{
	$strYear = substr($strDate, 0, 4);
	$strMonth = substr($strDate, 4, 2);
	$strDay = substr($strDate, 6, 2);
	$strHour = substr($strDate, 9, 2);
	$strMinute = substr($strDate, 11, 2);
	$strSecond = substr($strDate, 13, 2);
	$date = mktime($strHour, $strMinute, $strSecond, $strMonth, $strDay, $strYear);
	return gmdate('r', $date);
}
function swGetAttachedFiles($p) {
	$a = glob(dirname(__FILE__).'/pages/'.$p.'/*');
	rsort($a);
	$r=$m=array();
	foreach($a as $file){
		if(preg_match('/(.*)\.(\d{8}-\d{6})$/',$file,$m))
			$r[removeDirname($m[1])][] = $m[2]; //
	}
	return $r;

}
function swDisplay() {
	global $p;
	swHeader();
	echo '<h1>'.$p.'</h1>';
	echo txt2html(swGet($p));
	swFooter();
}
function swEdit() {
	global $p,$s;
	swAuth();
	if (isset($_POST['s']) && $_POST['s']!='preview') { //save or cancel
		if ($_POST['s']=='save') {
			swSave($p, stripslashes($_POST['c']));
			$a = array();
			preg_match('/^([:\.]?[\w ,\'!]+)/',$_POST['t'],$a);
			if (count($a)>1 && $a[1]!=$p) {
				rename(dirname(__FILE__).'/pages/'.$p, dirname(__FILE__).'/pages/'.$a[1]);//rename
				$p=$a[1];
			}
		} elseif ($_POST['s']=='upload') { //upload file
			if ($_FILES['pj']['size']!=0) {
				if(strtolower(substr($_FILES['pj']['name'],-3))=='php') //rename to phps to avoid code execution
					move_uploaded_file($_FILES['pj']['tmp_name'], dirname(__FILE__).'/pages/'.$p.'/'.basename($_FILES['pj']['name']).'s.'.date('Ymd-His'));
				// if(strtolower(substr($_FILES['pj']['name'],-3))=='jpg') {//create thumb
				// 	$jpg = dirname(__FILE__).'/pages/'.$p.'/'.basename($_FILES['pj']['name']).'.'.date('Ymd-His');
				// 	move_uploaded_file($_FILES['pj']['tmp_name'], $jpg);
				// 	$IF = new ImageFilter;
				// 	$IF->loadImage($jpg);
				// 	$IF->resize(350, 350, 'ratio');
				// 	$IF->output('JPEG', $jpg.'.350');
				// } else { //gz?
					move_uploaded_file($_FILES['pj']['tmp_name'], dirname(__FILE__).'/pages/'.$p.'/'.basename($_FILES['pj']['name']).'.'.date('Ymd-His'));
				// }
			}
		}
		header('Location: ./'.$s.'?'.$p);
	}
	swHeader();
	// preview
	if (isset($_POST['c'])) { 
		$c = $_POST['c'];
		$t = $_POST['t'];
	} else {
		$c = swGet($p);
		$t=$p;
	}
	echo '<form enctype="multipart/form-data" method="post" action="'.$s.'?'.$p.'/edit">';
	echo '<div class="br"><h1><!--'.$p.'--><input name="t" type="text" value="'.$t.'"/></h1></div>'."\n";
	echo txt2html(stripslashes($c));
	echo '<div class="br"><textarea name="c">'.stripslashes($c).'</textarea>';
	echo '<input type="submit" name="s" value="preview"/>';
	echo '<input type="submit" name="s" value="save"/>';
	echo '<input name="pj" type="file" />';
	echo '<input type="submit" name="s" value="upload"/>';
	echo '<input type="submit" name="s" value="cancel"/>';
	echo '<button onclick="document.getElementById(\'syntax\').classList.toggle(\'hidden\');return false;">syntax</button></div>';
	echo '</form>';
	$pjs=swGetAttachedFiles($p);
	if(count($pjs)!=0) echo '<ul id="pjs">';
	foreach($pjs as $name => $versions)
		echo '<li>'.$name.'</li>';
	if(count($pjs)!=0) echo '</ul>';
	echo '<div id="syntax" class="hidden">'.txt2html(swGet('Syntaxe')).'</div>';
}
function swHistory() {
	global $p,$v,$d,$s;
	swAuth();
	if ($d=='set') {swSet($p,$v);}
	if ($d=='delete') {swDel($p,$v);}
	$a = array();
	if (preg_match('/^delete\/(.*)$/',$d,$a)) {unlink(dirname(__FILE__).'/pages/'.$a[1].'.'.$v);$v='';}
	swHeader();
	echo '<h1>'.$p.'</h1>';
	echo txt2html(swGet($p,$v));

	$versions=swVersions($p);
	if(count($versions)>0) {
		echo '<ul id="history">'."\n";
		foreach($versions as $version)  {
			echo '	<li'.($version==$v?' class="current"':'').'><a href="'.$s.'?'.$p.'/history/'.$version.'">'.FormatReadableDate($version).'</a>'.
				($version==$v?' <a href="'.$s.'?'.$p.'/history/'.$version.'/set">set</a>':'').
				($version==$v?' <a href="'.$s.'?'.$p.'/history/'.$version.'/delete">delete</a>':'').'</li>'."\n";
		}
		echo '</ul>'."\n";
		$pjs=swGetAttachedFiles($p);
		foreach($pjs as $pj => $versions) {
			echo '<ul>';
			foreach($versions as $version)
				echo '<li>'.$pj.' - '.FormatReadableDate($version).
				' <a href="'.$s.'?'.$p.'/history/'.$version.'/delete/'.$pj.'">delete</a>'.
				'</li>';
			echo '</ul>';
		}
	}
	swFooter();
}
function swSpecialAll() {
	global $s;
	swHeader();
	echo '<h1>All pages</h1>';
	$pages = swPages();
	if(count($pages)!=0) echo '<ul>';
	foreach($pages as $page)
		echo '<li><a href="'.$s.'?'.$page.'">'.$page.'</a></li>';
	if(count($pages)!=0) echo '</ul>';
	swFooter();
}
function swSpecialLast() {
	global $s;
	$pages = swPages();
	foreach($pages as $page) {
		$versions = swVersions($page);
		if (isset($versions[0])) $last[$versions[0]] = $page;
	}
	krsort($last);
	swHeader();
	echo '<h1>Latest changes</h1>';
	if(count($last)!=0) echo '<ul>';
	foreach(array_slice($last,0,100) as $page)
		echo '<li><a href="'.$s.'?'.$page.'">'.$page.'</a></li>';
	if(count($last)!=0) echo '</ul>';
	swFooter();
}
function swSpecialBlog() {
	global $s,$_sw;
	$pages = swPages();
	foreach($pages as $page) {
		$versions = swVersions($page);
		if (isset($versions[0])) $last[$versions[count($versions)-1]] = $page;
		//if (isset($versions[0])) $last[$versions[0]] = $page; // version je change un mot => la page remonte.
	}
	krsort($last);
	swHeader($_sw['title']);
	foreach(array_slice($last,0,10) as $page) {
		echo '<h1><a href="'.$s.'?'.$page.'">'.$page.'</a></h1>';
		echo txt2html(swGet($page),$page);
	}
	swFooter();
}
function swSpecialRss() {
	global $_sw;
	$pages = swPages();
	foreach($pages as $page) {
		$versions = swVersions($page);
		if (isset($versions[0])) $last[$versions[count($versions)-1]] = $page;
	}
	krsort($last);
	$dates = array_keys($last);
	header('Content-Type: application/xml; charset=UTF-8');
	echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
	echo '<rss version="2.0">'."\n";
	echo '<channel>'."\n";
	echo '	<title>'.$_sw['title'].'</title>'."\n";
	echo '	<link>'.'http://' . $_SERVER['SERVER_NAME'].$_SERVER['PHP_SELF'].	'</link>'."\n";
	echo '	<description></description>'."\n";
	echo '	<lastBuildDate>'.FormatRfc1123Date($dates[0]).'</lastBuildDate>'."\n";

	foreach(array_slice($last,0,5) as $date => $page) {
		echo'	<item>'."\n";
		echo'		<title>'.$page.'</title>'."\n";
		echo'		<link>http://' . $_SERVER['SERVER_NAME'].$_SERVER['PHP_SELF'].'?'.$page.'</link>'."\n";
		echo'		<pubDate>'.FormatRfc1123Date($date).'</pubDate>'."\n";
		echo '<description>'.htmlentities(txt2html(swGet($page))).'</description>'."\n";
		echo'	</item>'."\n";
	}
	echo '</channel>'."\n";
	echo '</rss>';
}
function swSpecialSearch() {
	global $s;
	$a = array();
	$q=$_POST['q'];
	$r='';
	swHeader();
	echo '<h1>Search for "'.$q.'"</h1>';
	$pages = swPages();
	foreach($pages as $page)
		if (preg_match('/.{0,20}'.preg_quote($q).'.{0,20}/si',$page.' - '.swGet($page),$a)) {//ignorer accents?
			$r.='<li><a href="'.$s.'?'.$page.'">'.$page.'</a><br/><span>'.$a[0].'</span></li>';
		}
	if($r!='') echo '<ul id="search">'.$r.'</ul>';
	else echo 'No results!';
	swFooter();
}
// class ImageFilter {
// 	/*
// 	 * $colorsToWork :
// 	 * d?termine le nombre de couleur par d?faut sur lequel travaillerons les filtres
// 	 * Mettre une valeur faible peut avoir des comportement ?tonnant
// 	 * selon la version de la librairie et de la plateforme d'execution.
// 	 * En effet si manifestement sous Windows les couleurs sont bien choisit (l'image resultante est plutot fid?le)
// 	 * sous Linux le choix est parfois "particulier" et il n'est pas rare d'obtenir un image au teintes
// 	 * trop claire ou trop sombre (parfois m?me toutes identiques > donc plus d'image ^^)
// 	 *
// 	 * $GD_VERSION : Peut prendre la valeur 1 ou 2 selon la version de la librairie GD install? sur le serveur
// 	 *
// 	 */
// 	var $GD_VERSION = 2;
// 	var $colorsToWork = 256;

// 	function ImageFilter() {
// 		$this->resourceImage = NULL;
// 	}

// 	function setColorsToWork($nb) {
// 		$this->colorsToWork = $nb;
// 	}

// 	function clear() {
// 		imagedestroy($this->resourceImage);
// 	}

// 	/*
// 	 * Cr?ation d'une image vierge
// 	 * $w=largeur $h=hauteur
// 	 *
// 	 */
// 	function createImage($w, $h) {
// 		$this->resourceImage = $this->imagecreate($w, $h);
// 	}

// 	/*
// 	 * Chargement d'une image depuis un fichier
// 	 *
// 	 */
// 	function loadImage($path) {
// 		$this->resourceImage = $this->loadImageFile($path);
// 		return is_resource($this->resourceImage);
// 	}

// 	/*
// 	 * M?thode priv? (pas vraiment possible en PHP4) g?rant l'ouverture et la mise en m?moire d'une image depuis un fichier
// 	 * utilis? entre autre par loadImage(...)  et Stamp(...)
// 	 *
// 	 */
// 	function loadImageFile($path) {
// 		$info = getimagesize($path);
// 		switch ($info[2]) {
// 		case 3 :
// 			return imagecreatefrompng($path);
// 		case 2 :
// 			return imageCreateFromJpeg($path);
// 		case 1 :
// 			return imagecreatefromgif($path);
// 		default :
// 			return false;
// 		}
// 	}

// 	/*
// 	 * M?thode de lecture des dimension de l'image actuellement en court de traitement
// 	 * il est aussi possible de lui passer en param?tre un objet de type image
// 	 *
// 	 */
// 	function getImageSize($img = NULL) {
// 		if (is_resource($img)) {
// 			return array (
// 					   'w' => imagesx($img
// 									 ), 'h' => imagesy($img));
// 		} else {
// 			return array (
// 					   'w' => imagesx($this->resourceImage
// 									 ), 'h' => imagesy($this->resourceImage));
// 		}
// 	}

// 	/*
// 	 * M�thode de sortie
// 	 * Il est possible de g�n�rer soit des PNG soit des JPEG (gestion du niveau de qualit?)
// 	 * l'image peut soit ?tre envoy? soit en flux direct, soit enregistr? dans un fichier
// 	 * ex:
// 	 * $IF->output('JPEG',NULL,NULL,80); // JPEG Q80 en flux direct
// 	 * $IF->output('JPEG','cache.jpg',false,80); // JPEG Q80 enregistr? dans cache.jpg sans ?crasement si d?j? existant
// 	 */
// 	function output($type = 'PNG', $file = NULL, $overwrite = true, $JPG_Q = 90) {
// 		if ($file == NULL) {
// 			header('Cache-Control: no-store, no-cache, must-revalidate, post-check=0, pre-check=0');
// 			header('Expires: Thu, 19 Nov 1981 08:52:00 GMT');
// 			header('Pragma: no-cache');
// 			switch ($type) {
// 			case 'PNG' :
// 				header('Content-type: image/png');
// 				imagepng($this->resourceImage);
// 				return true;
// 			case 'JPEG' :
// 				header('Content-type: image/jpeg');
// 				imagejpeg($this->resourceImage, NULL, $JPG_Q);
// 				return true;
// 			default :
// 				return false;
// 			}
// 		} else {
// 			if ($overwrite || !file_exists($file)) {
// 				switch ($type) {
// 				case 'PNG' :
// 					return imagepng($this->resourceImage, $file);
// 				case 'JPEG' :
// 					return imagejpeg($this->resourceImage, $file, $JPG_Q);
// 				default :
// 					return false;
// 				}
// 			}
// 		}
// 	}

// 	/**
// 	 * M�thode de redimensionnement.
// 	 *
// 	 * Les deux param�tre $WIDTH et $HEIGHT peuvent ?tre pr?cis? soit en pixel (70) soit en % ('70%')
// 	 *
// 	 * 3 mode sont possibles : "force", "crop" ou "ratio"
// 	 *	- force = par d?formation
// 	 *	- crop = par recadrage au centre
// 	 *	- ratio = par conservation de l'aspect ratio ($WIDTH et $HEIGHT = Boite de travail)
// 	 *
// 	 * Le param?tre expand permet de pr?ciser si les agrandissement sont autoris?
// 	**/
// 	function resize($WIDTH, $HEIGHT, $MODE = 'force', $EXPAND = false) {
// 		$info = $this->getImageSize();
// 		$imgWidth = $info['w'];
// 		$imgHeight = $info['h'];

// 		$ratio = $imgWidth / $imgHeight;

// 		//gestion des dimension en %
// 		if (strpos($WIDTH, '%', 0))
// 			$WIDTH = $imgWidth * $WIDTH / 100;
// 		if (strpos($HEIGHT, '%', 0))
// 			$HEIGHT = $imgHeight * $HEIGHT / 100;

// 		//si pas de dimension pr?cis?es alors echec
// 		if ($WIDTH == 0 && $HEIGHT == 0)
// 			return false;

// 		//si jamais une dimension = 0 on d?termine la valeur la plus appropri?.
// 		if (min($WIDTH, $HEIGHT) == 0) {
// 			switch ($MODE) {
// 			case 'crop' : //on coupe en carr?
// 				$WIDTH = $HEIGHT = max($WIDTH, $HEIGHT);
// 				break;

// 			case 'force' : //on passe en ratio pour ?viter les d?formation
// 				$MODE = 'ratio';

// 			case 'ratio' : //on prend une taille tr?s grand pour ne pas limiter sur la cote non pr?cis?
// 				if ($WIDTH === 0)
// 					$WIDTH = 9999;
// 				else
// 					$HEIGHT = 9999;
// 				break;

// 			default :
// 				break;
// 			}
// 		}

// 		//on d?termine les dimension du resize ($_w et $_h)
// 		if ($MODE == 'ratio') {
// 			$_w = 99999;
// 			if ($HEIGHT > 0) {
// 				$_h = $HEIGHT;
// 				$_w = $_h * $ratio;
// 			}
// 			if ($WIDTH > 0 && $_w > $WIDTH) {
// 				$_w = $WIDTH;
// 				$_h = $_w / $ratio;
// 			}

// 			if (!$EXPAND && $_w > $imgWidth) {
// 				$_w = $imgWidth;
// 				$_h = $imgHeight;
// 			}
// 		} else {
// 			//par d?coupage de l'image source
// 			$_w = $WIDTH;
// 			$_h = $HEIGHT;
// 		}

// 		if ($MODE == 'force') {
// 			if (!$EXPAND && $_w > $imgWidth)
// 				$_w = $imgWidth;
// 			if (!$EXPAND && $_h > $imgHeight)
// 				$_h = $imgHeight;

// 			$cropW = $imgWidth;
// 			$cropH = $imgHeight;
// 			$decalW = 0;
// 			$decalH = 0;
// 		} else { //crop
// 			//on d?termine ensuite la zone d'affiche r?el pour l'image
// 			$innerRatio = $_w / $_h;
// 			if ($ratio >= $innerRatio) {
// 				$cropH = $imgHeight;
// 				$cropW = $imgHeight * $innerRatio;
// 				$decalH = 0;
// 				$decalW = ($imgWidth - $cropW) / 2;
// 			} else {
// 				$cropW = $imgWidth;
// 				$cropH = $imgWidth / $innerRatio;
// 				$decalW = 0;
// 				$decalH = ($imgHeight - $cropH) / 2;
// 			}
// 		}

// 		$img2 = $this->imagecreate($_w, $_h);
// 		$this->imagecopyresampled($img2, $this->resourceImage, 0, 0, $decalW, $decalH, $_w, $_h, $cropW, $cropH);
// 		imagedestroy($this->resourceImage);
// 		$this->resourceImage = $img2;
// 		return true;
// 	}
// 	/**
// 	 * On repasse en mode couleur vrai (24bits)
// 	 * !!! peut entrainer la suppression de la couche alpha
// 	 *
// 	 */
// 	function palettedToTrueColor() {
// 		$info = $this->getImageSize();
// 		$img2 = $this->imagecreate($info['w'], $info['h']);
// 		$this->imagecopyresampled($img2, $this->resourceImage, 0, 0, 0, 0, $info['w'], $info['h'], $info['w'], $info['h']);
// 		imagedestroy($this->resourceImage);
// 		$this->resourceImage = $img2;
// 	}

// 	/**
// 	 * M?thode de redimesionnement selon la version de librairie GD (1 ou 2)
// 	 * GD 1.x ne g?rant pas les images 24bits, elle ne fait pas de r?-?chantillonnage sur les redimensionnemnt
// 	 *
// 	 */
// 	function imagecopyresampled($out, $in, $dstX, $dstY, $srcX, $srcY, $dstW, $dstH, $srcW, $srcH) {
// 		if ($this->GD_VERSION == 2)
// 			return imagecopyresampled($out, $in, $dstX, $dstY, $srcX, $srcY, $dstW, $dstH, $srcW, $srcH);
// 		else
// 			return imagecopyresized($out, $in, $dstX, $dstY, $srcX, $srcY, $dstW, $dstH, $srcW, $srcH);
// 	}

// 	/**
// 	 * M?thode de cr?ation d'image  selon la version de librairie GD (1 ou 2)
// 	 * GD 1.x ne g?re pas les images en 24bits on cr?e alors une image 256 couleurs
// 	 *
// 	 */
// 	function imagecreate($w, $h) {
// 		if ($this->GD_VERSION == 2)
// 			return imagecreatetruecolor($w, $h);
// 		else
// 			return imagecreate($w, $h);
// 	}
// }

?>