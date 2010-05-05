<?php
error_reporting(6135); // errors and warnings

include "../adminer/include/coverage.inc.php";

// disable filter.default
$filter = (!ereg('^(unsafe_raw)?$', ini_get("filter.default")) || ini_get("filter.default_flags"));
if ($filter) {
	foreach (array('_GET', '_POST', '_COOKIE', '_SERVER') as $val) {
		$unsafe = filter_input_array(constant("INPUT$val"), FILTER_UNSAFE_RAW);
		if ($unsafe) {
			$$val = $unsafe;
		}
	}
}

// used only in compiled file
if (isset($_GET["file"])) {
	include "../adminer/file.inc.php";
}

include "../adminer/include/functions.inc.php";

if (!isset($_SERVER["REQUEST_URI"])) {
	$_SERVER["REQUEST_URI"] = $_SERVER["ORIG_PATH_INFO"] . ($_SERVER["QUERY_STRING"] != "" ? "?$_SERVER[QUERY_STRING]" : ""); // IIS 5 compatibility
}

@ini_set("session.use_trans_sid", false); // protect links in export, @ - may be disabled
if (!ini_bool("session.auto_start")) {
	session_name("adminer_sid"); // use specific session name to get own namespace
	$params = array(0, preg_replace('~\\?.*~', '', $_SERVER["REQUEST_URI"]), "", $_SERVER["HTTPS"] && strcasecmp($_SERVER["HTTPS"], "off"));
	if (version_compare(PHP_VERSION, '5.2.0') >= 0) {
		$params[] = true; // HttpOnly
	}
	call_user_func_array('session_set_cookie_params', $params); // ini_set() may be disabled
	session_start();
}

// disable magic quotes to be able to use database escaping function
if (get_magic_quotes_gpc()) {
	$process = array(&$_GET, &$_POST, &$_COOKIE);
	while (list($key, $val) = each($process)) {
		foreach ($val as $k => $v) {
			unset($process[$key][$k]);
			if (is_array($v)) {
				$process[$key][stripslashes($k)] = $v;
				$process[] = &$process[$key][stripslashes($k)];
			} else {
				$process[$key][stripslashes($k)] = ($filter ? $v : stripslashes($v));
			}
		}
	}
	unset($process);
}
if (function_exists("set_magic_quotes_runtime")) {
	set_magic_quotes_runtime(false);
}
@set_time_limit(0); // @ - can be disabled

include "../adminer/include/lang.inc.php";
include "../adminer/lang/$LANG.inc.php";
include "../adminer/include/pdo.inc.php";
include "../adminer/drivers/sqlite.inc.php";
include "../adminer/drivers/pgsql.inc.php";
include "../adminer/drivers/mssql.inc.php";
include "../adminer/drivers/mysql.inc.php"; // must be included as last driver

define("SERVER", $_GET[DRIVER]); // read from pgsql=localhost
define("DB", $_GET["db"]); // for the sake of speed and size
define("ME", preg_replace('~^[^?]*/([^?]*).*~', '\\1', $_SERVER["REQUEST_URI"]) . '?'
	. (SID && !$_COOKIE ? SID . '&' : '') // !$_COOKIE - don't pass SID with permanent login
	. (SERVER !== null ? DRIVER . "=" . urlencode(SERVER) . '&' : '')
	. (isset($_GET["username"]) ? "username=" . urlencode($_GET["username"]) . '&' : '')
	. (DB != "" ? 'db=' . urlencode(DB) . '&' . (isset($_GET["ns"]) ? "ns=" . urlencode($_GET["ns"]) . "&" : "") : '')
);

include "../adminer/include/version.inc.php";
include "./include/adminer.inc.php";
include "../adminer/include/design.inc.php";
include "../adminer/include/xxtea.inc.php";
include "../adminer/include/auth.inc.php";
include "./include/connect.inc.php";
include "./include/editing.inc.php";
include "./include/export.inc.php";

session_cache_limiter(""); // to allow restarting session
if (!ini_bool("session.use_cookies") || @ini_set("session.use_cookies", false) !== false) { // @ - may be disabled
	session_write_close(); // improves concurrency if a user opens several pages at once, may be restarted later
}

$on_actions = array("RESTRICT", "CASCADE", "SET NULL", "NO ACTION"); ///< @var array used in foreign_keys()
$confirm = " onclick=\"return confirm('" . lang('Are you sure?') . "');\""; ///< @var string
