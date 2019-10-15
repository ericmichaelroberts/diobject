<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

require_once(APPPATH."libraries/PHPExcel.php");
require_once(APPPATH."libraries/DMSA_DIObject.php");

if(!defined('DMSA_EXPORT_AUTOLOAD_REGISTERED')){

	spl_autoload_register(function($class){
		if(preg_match('/_((Spreadsheet|Workbook).*|Table)$/',$class)){
			$dir = __DIR__.'/Export/';
			$files = scandir($dir);
			$filename = "{$class}.php";
			$target = "{$dir}{$filename}";
			if(in_array($filename,$files)){
				include($target);
			}else{
				DMSA::CrashBlob(compact('dir','files','filename','target'));
			}
		}
	});

	define('DMSA_EXPORT_AUTOLOAD_REGISTERED',true);
}
