<?php
/**
 * index.php
 * Run time where all magic happens
 * @author Dory A.Azar
 * @copyright 2019
 * @version 1.0
 *
*/

use Caligrafy\Framework;

// Deprecation notices (often raised inside vendor libraries by new PHP versions) are never printed into pages
error_reporting(E_ALL & ~E_DEPRECATED & ~E_USER_DEPRECATED);

require_once "framework/core/Framework.php";

// load external vendors
require_once 'vendor/autoload.php';
       
// load environment variables
$dotenv = Dotenv\Dotenv::createUnsafeMutable(__DIR__);
$dotenv->load();

Framework::run();




