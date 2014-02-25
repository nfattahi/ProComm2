<?php

$basepath = 'lesson_data/fr351/Practice';
$filetypes = array("xml");
$lessons = array();
$index = 1;

foreach (new DirectoryIterator($basepath) as $dirInfo) {
    if($dirInfo->isDot()) continue;
	if(!$dirInfo->isDir()) continue;
    //echo $fileInfo->getPathname() . "<br>\n";
	//$lessons[$dirInfo->getFilename()] = array();
	foreach (new DirectoryIterator($dirInfo->getPathname()) as $fileInfo) {
		if($fileInfo->isDot()) continue;
		if($fileInfo->isDir()) continue;
		$filetype = pathinfo($fileInfo, PATHINFO_EXTENSION);
		if (in_array(strtolower($filetype), $filetypes)) {
			$lessons[$index][] = $fileInfo->getFilename();
		  //echo $fileInfo . ' - ' . $fileInfo->getSize() . ' bytes <br/>';
		}
	}
	$index++;
}
//print_r($lessons);
$lessons[0] = $basepath;

session_start();
$_SESSION['lessons_array'] = $lessons;
?>