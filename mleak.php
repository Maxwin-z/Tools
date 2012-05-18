#!/usr/bin/php

<?php
define(WHITE_COLOR, 	chr(27) . '[1;37m');
define(RED_COLOR, 		chr(27) . '[1;31m');
define(GREEN_COLOR, 	chr(27) . '[1;32m');
define(PURPLE_COLOR, 	chr(27) . '[1;35m');
define(RESET_COLOR, 	chr(27) . '[0m');
define(CYAN_BG,			chr(27) . '[44m');
define(RED_BG,			chr(27) . '[41m');
define(GREEN_BG,		chr(27) . '[42m');

$arg = $argv[1];

$filePatten = '';
$dir = $arg;
if (!is_dir($arg)) {
	$dir = dirname($arg);
	$filePatten = substr($arg, strlen($dir) + 1);
	if (!is_dir($dir)) {
		die("dir[$dir]not exists\n");
	}
}

$handle = opendir($dir);
while ($file = readdir($handle)) {
	if (preg_match('/.+\\.h$/', $file, $matches)) {
		$clazz = substr($file, 0, strlen($file) - 2);	// remove .h, leave class name
		if ($filePatten == '' || ($filePatten != '' && strpos($file, $filePatten) === 0)) {
			checkClass($dir . '/' . $clazz);
		}
	}
}

function checkClass($clazz) {
	echo "====================================\n";
	echo PURPLE_COLOR . "check file[$clazz]" . RESET_COLOR . "\n";
	$hFile = $clazz . '.h';
	$mFile = $clazz . '.m';
	if (!file_exists($mFile)) {
		$mFile = $clazz . '.mm';
		if (!file_exists($mFile)) {
			echo "no source file for header[$clazz]\n";
			return ;
		}
	}
	// check 
	$hSrc = file_get_contents($hFile);
	$mSrc = file_get_contents($mFile);
	
	$source = addLineNum($hSrc, ' h') . "\n" . addLineNum($mSrc, ' m');
	$source = trimComment($source);
	// count alloc, retain, copy.  and autorelease, release
	$results = array();
	$retainCnt = $releaseCnt = 0;

	$lines = explode("\n", $source);
	for ($i = 0; $i != count($lines); ++$i) {
		$line = $lines[$i];
		if (preg_match_all('/[\\s\\]\\[,\\(](alloc|retain|copy|autorelease|release)[\\s\\]\\),]/s', $line, $matches, PREG_SET_ORDER)) {
			for ($j = 0; $j != count($matches); ++$j) {
				$match = $matches[$j];
				// print_r($match);
				$word = $match[1];
				// echo "word[$word]\n";
				if ($word == 'alloc' || $word == 'retain' || $word == 'copy') {
					$results[] = RED_COLOR . "\t+" . $line . RESET_COLOR;
					++$retainCnt;
				} else {
					$results[] = GREEN_COLOR . "\t-" . $line . RESET_COLOR;
					++$releaseCnt;
				}
			}
		}
	}
	
	echo implode("\n", $results) . "\n";
	$bgColor = ($retainCnt == $releaseCnt ? GREEN_BG : RED_BG);
	printf($bgColor . WHITE_COLOR . "\nSUMMARY: retain[%d], release[%d]" . RESET_COLOR . "\n\n", $retainCnt, $releaseCnt);
}

function addLineNum($source, $flag = '') {
	$lines = explode("\n", $source);
	for ($i = 0; $i != count($lines); ++$i) {
		$lines[$i] = sprintf("%5d%s\t%s", $i + 1, $flag, $lines[$i]);
	}
	return implode("\n", $lines);
}

function trimComment($source) {
	// the regx not work. no idea
	// $source = preg_replace('/\/\\*(((?!\/\\*)|\\n).)*\\*\//', '', $source);
	$sd = '/*'; $ed = '*/';
	$spos = strlen($source);
	while(($epos = strrpos($source, $ed, $spos - strlen($source))) !== false) {
		$epos2 = strrpos($source, $ed, $epos - 2 - strlen($source));
		if($epos2 === false) $epos2 = 0;
		$spos = strpos($source, $sd, $epos2);
		if ($spos !== false) {
			$source = substr($source, 0, $spos) . substr($source, $epos + 2);
		} else {
			$spos = $epos;
		}
	}
	
	$source = preg_replace('/\/\/.*?\\n/', "\n", $source);
	return $source;
}
