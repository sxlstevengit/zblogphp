<?php
/**
 * api
 * @author zsx<zsx@zsxsoft.com>
 * @package api/route
 * @php >= 5.2
 */
class API_Route {
	/**
	 * Instance
	 */
	private static $instance;
	/**
	 * Debug
	 * @var boolean
	 */
	public static $debug = false;
	/**
	 * Base path
	 * @var string
	 */
	private static $_base = '';
	/**
	 * Rule Tree
	 * Some web servers don't supper PUT/DELETE.
	 */
	private static $_ruleTree = array(
		"GET" => array(),
		"POST" => array(),
		"GLOBAL" => array(),
	);
	/**
	 * RegExp List
	 */
	private static $_regExList = array(
		"GET" => array(),
		"POST" => array(),
		"GLOBAL" => array(),
	);

	/**
	 * To return instance
	 * @return API_Route
	 */
	public static function getInstance() {

		if (is_null(self::$instance)) {
			$class = __CLASS__;
			self::$instance = new $class();
		}
		return self::$instance;
	}

	/**
	 * To avoid clone
	 */
	public function __clone() {
		throw new Exception("Singleton Class Can Not Be Cloned");
	}

	/**
	 * To build tree from path
	 * @param int $deep
	 * @param array $array
	 * @param array $tree
	 * @return bool
	 */
	private static function buildTree($deep, $array, &$tree, $callback) {

		if ($deep == count($array)) {
			$tree['__callback'] = $callback; // Register callback in the deepest path.
			return;
		}

		if (self::$debug) {echo 'deep = ' . $deep . ', $array[$deep] = ' . $array[$deep] . "\n";}
		$val = $array[$deep];

		$childTree = &$tree;
		if (!isset($tree[$val])) {
			$tree[$val] = array();
		}
		$childTree = &$tree[$val];

		self::buildTree($deep + 1, $array, $childTree, $callback); // To build deeper path
		return true;
	}

	/**
	 * To build RegExp list 
	 * @param string $string
	 * @param array $list
	 * @param callable $callback
	 * @return bool
	 */
	private static function buildRegExpList($regex, &$list, $callback) {
		$list[$regex] = $callback;
		return true;
	}
	
	/*
	 * To analyze path
	 * @param int $deep
	 * @param array $array
	 * @param array $tree
	 * @return bool
	 */
	private static function _analyze($deep, $array, $tree) {
		//echo $deep;

		$val = $array[$deep];
		foreach ($tree as $key => $child) {
			if ($key == "__callback") {
				continue;
			}
			$str = preg_quote($key);
			$str = str_replace('\\*', '.+', $str);
			$str = str_replace('\\?', '.', $str);
			$str = "/" . $str . "/"; // Build RegExp for test
			if (self::$debug) {
				echo "deep = " . $deep . ", RegEx = " . $str . ", Value = " . $val . ", Result = " . (preg_match($str, $val) ? "True" : "False") . "\n";
			}
			if (preg_match($str, $val)) {
				if ($deep == count($array) - 1 || count($child) == 0) {
					if (isset($tree[$key]['__callback'])) { 
						$tree[$key]['__callback'](); 
					}
					return true;
				}
				if (self::_analyze($deep + 1, $array, $child)) {
					return true;
				} else {
					continue;
				}
			} else {
				continue;
			}
		}
		return false;
	}
	/**
	 * Check RegExp
	 * @param string $path
	 * @param array $array
	 * @return bool
	 */
	private static function _checkRegExp($path, $array) {
		foreach ($array as $key => $value) {
			if (self::$debug) {echo "Check RegExp: " . $value . " , Result: " . (preg_match($value, $path) ? "True" : "False") . "\n";}
			if (preg_match($key, $path)) {
				$value();
				return true;
			}

		}
		return false;
	}

	/**
	 * Create Route
	 * @param  string   $url  
	 * @param  callable $callback
	 * @param  string $method
	 * @return boolean
	 */
	public static function route($url, $callback, $method = "GLOBAL") {
		if (@preg_match($url, null) === false) {
			// Test if string is not regex
			$urlArray = explode("/", $url);
			if (count($urlArray) <= 0) {
				return false;
			}

			if ($urlArray[0] == "") {
				array_Shift($urlArray);
			}

			return self::buildTree(0, $urlArray, self::$_ruleTree[$method], $callback);
		} else {
			return self::buildRegExpList($url, self::$_regExList[$method], $callback);
		}
	}

	/**
	 * Create GET Route
	 * @param  string   $url      
	 * @param  callable $callback 
	 * @return boolean
	 */
	public static function get($url, $callback) {
		// I have to write dulipated functions instead of __callStatic.
		// Because __callStatic was added since PHP 5.3.0.
		// So that, let's fuck PHP 5.2 together!
		return self::route($url, $callback, "GET");
	}

	/**
	 * Create POST Route
	 * @param  string   $url      
	 * @param  callable $callback 
	 * @return boolean
	 */
	public static function post($url, $callback) {
		return self::route($url, $callback, "POST");
	}

	/**
	 * Scan Route
	 * @param string $requestMethod
	 * @param string $url
	 * @return bool
	 */
	public static function scanRoute($requestMethod, $url) {

		$urlArray = explode("/", $url);
		if ($urlArray[0] == "") {
			array_Shift($urlArray);
		}

		// Firstly, we should check request method
		if ($requestMethod != 'POST' && $requestMethod != 'GET') {
			$requestMethod = "GET";
		}

		$checkList = array($requestMethod, 'GLOBAL');

		foreach ($checkList as $item) {
			self::_checkRegExp($url, self::$_regExList[$item]);
			self::_analyze(0, $urlArray, self::$_ruleTree[$item]);
		}

	}

}