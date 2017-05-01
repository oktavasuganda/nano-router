<?php

class NanoRouter {
	private static $baseUrl;
	private static $baseDir;

	private static $map = array(
		'GET'=>array(),
		'POST'=>array(),
		'PUT'=>array(),
		'DELETE'=>array(),
		'OPTIONS'=>array(),
		'CONNECT'=>array()
	);

	private static $map_dynamic = array(
		'GET'=>array(),
		'POST'=>array(),
		'PUT'=>array(),
		'DELETE'=>array(),
		'OPTIONS'=>array(),
		'CONNECT'=>array()
	);

	private static $map_404 = array(
		'GET'=>array(),
		'POST'=>array(),
		'PUT'=>array(),
		'DELETE'=>array(),
		'OPTIONS'=>array(),
		'CONNECT'=>array()
	);

	private static $requestUri;
	private static $requestHeaders;
	private static $isDynamic = false;
	private static $dynamicParams = array();
	private static $dynamicPath;

	/**
	 * Register route callback for method / path.
	 *
	 * @param string|array 	$method - HTTP request method or array of methods.
	 *                                You can pass an array to apply route to multiple HTTP request methods,
	 *                                or you can pass formatted string like "POST|GET|..." or you can pass
	 *                                "ALL" to apply route to all available HTTP request methods.
	 *
	 * @param string $path          - Relative path relative to your base url.
	 *								  Dynamic paths are supported and you can defined them by using wildcards
	 *                                such as "/user/{user_id}/profile". You can put anything between { and }
	 *                                and you can define multiple variables (ex. "/forum/{forum_id}/topic/{topic_id}").
	 *
	 * @param callable $callable	- Callable method to be executed on self::response() if path matches.
	 *                           	  If you are routing dynamic path, make sure your callback function accepts the same
	 *                           	  number of params as in your dynamic path.
	 */
	static function route($method, $path, $callable) {
		if(!preg_match_all('/\{(.*?)\}/i', $path))
			self::_route(self::$map, $method, $path, $callable);
		else
			self::_route(self::$map_dynamic, $method, $path, $callable);
	}

	/**
	 * Remove callback for specific method / path.
	 *
	 * @param string|array 	$method - HTTP request method or array of methods.
	 *                                You can pass an array to apply route to multiple HTTP request methods,
	 *                                or you can pass formatted string like "POST|GET|..." or you can pass
	 *                                "ALL" to apply route to all available HTTP request methods.
	 *
	 * @param string $path          - Path to be removed.
	 */
	static function unroute($method, $path) {
		self::_unroute(self::$map, $method, $path);
	}

	/**
	 * Register callback for "not found" event. In case requested uri is not routed,
	 * this $callable will handle response.
	 *
	 * @param string|array 	$method - HTTP request method or array of methods.
	 *                                You can pass an array to apply route to multiple HTTP request methods,
	 *                                or you can pass formatted string like "POST|GET|..." or you can pass
	 *                                "ALL" to apply route to all available HTTP request methods.
	 *
	 * @param callable $callable	- Callable method to be executed on self::response() if requested uri is not routed.
	 */
	static function route_404($method, $callable) {
		self::_route(self::$map_404, $method, 0, $callable);
	}

	/**
	 * Remove callback for "not found" event for specific HTTP request method.
	 *
	 * @param string|array 	$method - HTTP request method or array of methods.
	 *                                You can pass an array to apply route to multiple HTTP request methods,
	 *                                or you can pass formatted string like "POST|GET|..." or you can pass
	 *                                "ALL" to apply route to all available HTTP request methods.
	 */
	static function unroute_404($method) {
		self::_unroute(self::$map_404, $method, 0);
	}

	/**
	 * Returns a response based on HTTP request and requested uri.
	 *
	 * If requested uri is not routed, function will trigger callback registered using self::route_404 method.
	 * If that fails as well, function returns FALSE.
	 *
	 * @return bool|mixed
	 */
	static function respond() {
		$path = self::getRequestUri();
		$method = self::getRequestMethod();

		//Static first
		if(array_key_exists($method, self::$map) && array_key_exists($path, self::$map[$method])) {
			$callable = self::$map[$method][$path];

			if(is_callable($callable))
				return call_user_func($callable);
		}
		//Dynamic
		if(array_key_exists($method, self::$map_dynamic) && !empty(self::$map_dynamic[$method])) {
			$path_fragments = explode('/', $path);
			foreach(self::$map_dynamic[$method] as $d_path=>$callback) {
				$d_path_fragments = explode('/', $d_path);
				if(count($d_path_fragments) != count($path_fragments))
					continue;

				$params = array();
				$match = true;
				for($x = 0; $x < count($d_path_fragments); $x++) {
					//is param?
					if(preg_match('/^\{(.*?)\}$/i', $d_path_fragments[$x])) {
						$params[] = $path_fragments[$x];
						continue;
					}
					if($d_path_fragments[$x] != $path_fragments[$x]) {
						$match = false;
						break;
					}
				}
				//found?
				if($match) {
					self::$dynamicParams = $params;
					self::$dynamicPath = $d_path;
					self::$isDynamic = true;
				}
			}

			if(self::$dynamicPath != '' && array_key_exists(self::$dynamicPath, self::$map_dynamic[$method])) {
				$callable = self::$map_dynamic[$method][self::$dynamicPath];

				if(is_callable($callable))
					return call_user_func_array($callable, self::getParams());
			}
		}
		//404
		if(array_key_exists($method, self::$map_404) && array_key_exists(0, self::$map_404[$method])) {
			$callable = self::$map_404[$method][0];

			if(is_callable($callable))
				return call_user_func($callable);
		}

		return false;
	}

	/**
	 * Checks if current requested uri is dynamic uri.
	 *
	 * Function is not available until self::respond() is fired.
	 *
	 * @return bool
	 */
	static function isDynamic() {
		return self::$isDynamic;
	}

	/**
	 * Returns routed path string based on current HTTP request method.
	 * (ex. "/user/{user_id}/profile" if current request uri is "/user/31/profile")
	 *
	 * Function is not available until self::respond() is fired.
	 *
	 * @return mixed
	 */
	static function getDynamicPath() {
		return self::$dynamicPath;
	}

	/**
	 * Returns array of HTTP request headers.
	 *
	 * @return array|false
	 */
	static function getRequestHeaders() {
		if(is_array(self::$requestHeaders))
			return self::$requestHeaders;

		if(function_exists('getallheaders')) {
			$headers = getallheaders();
		}
		else {
			$headers = array();
			foreach ($_SERVER as $name => $value) {
				if (substr($name, 0, 5) == 'HTTP_') {
					$headers[str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($name, 5)))))] = $value;
				}
			}
		}
		self::$requestHeaders = !is_array($headers) ? array() : $headers;

		return self::$requestHeaders;
	}

	/**
	 * Get dynamic params from request uri.
	 * Works only if self::isDynamic() is TRUE.
	 *
	 * Function is not available until self::respond() is fired.
	 *
	 * @return array
	 */
	static function getParams() {
		return self::$dynamicParams;
	}

	/**
	 * Get current HTTP request method.
	 *
	 * @return string
	 */
	static function getRequestMethod() {
		return strtoupper($_SERVER['REQUEST_METHOD']);
	}

	/**
	 * Get current request uri (without self::$baseDir part).
	 *
	 * @return string
	 */
	static function getRequestUri() {
		if(self::$requestUri !== null)
			return self::$requestUri;

		$uri = $_SERVER['REQUEST_URI'];

		if(strlen(self::$baseDir) && substr($uri, 0, strlen(self::$baseDir)) == self::$baseDir) {
			$uri = substr($uri, strlen(self::$baseDir));
		}
		if(strpos($uri, '?') !== false) {
			$uri = explode('?', $uri);
			$uri = $uri[0];
		}
		if($uri != '/')
			$uri = rtrim($uri, '/');

		self::$requestUri = $uri;

		return self::$requestUri;
	}

	/**
	 * Set base url of your application.
	 *
	 * @param $baseUrl
	 */
	static function setBaseUrl($baseUrl) {
		self::$baseUrl = $baseUrl;
	}
	static function getBaseUrl() {
		return self::$baseUrl;
	}

	/**
	 * If your application is not located in root directory,
	 * use relative path from root directory.
	 *
	 * Example:
	 * "/myApp" if your application is located in "/home/myUser/public_html/myApp"
	 * where "/home/myUser/public_html" is root directory of your server.
	 *
	 * @param string $baseDir
	 */
	static function setBaseDir($baseDir) {
		self::$baseDir = $baseDir;
	}
	static function getBaseDir() {
		return self::$baseDir;
	}

	static function getRoutes() {
		$routes = self::$map;
		foreach(self::$map_dynamic as $method=>$paths) {
			if(empty($paths))
				continue;

			foreach($paths as $path=>$callback) {
				$routes[$method][$path] = $callback;
			}
		}
		return $routes;
	}

	/**
	 * Perform HTTP redirect.
	 *
	 * @param      $path
	 * @param bool $prevent_loop - Prevents infinite redirect loop.
	 *
	 * @return bool
	 */
	static function redirect($path, $prevent_loop = true) {
		if($prevent_loop) {
			$test_path = '';

			//is local path?
			if(substr($path, 0, 1) == '/')
				$test_path = self::$baseUrl . $path;
			elseif(strtolower(substr($path, 0, 4)) != 'http') {
				$test_path = self::$baseUrl . '/' . $path;
			}
			else {
				//is local path with self::$baseUrl?
				if(strtolower(substr($path, 0, strlen(self::$baseUrl))) == strtolower(self::$baseUrl)) {
					$test_path = $path;
				}
			}

			if(!empty($test_path)) {
				$test_path = substr($test_path, strlen(self::$baseUrl));

				if($test_path == self::getRequestUri())
					return false;
			}
		}

		header('Location: ' . $path);
	}

	private static function _route(&$map, $method, $path, $callable) {
		$method = strtoupper($method);
		if($path != '/')
			$path = rtrim($path, '/');

		if($method == 'ALL') {
			$methods = array_keys($map);
		}
		elseif(strpos($method, '|') !== false) {
			$methods = explode('|', $method);
			$methods = array_map('trim', $methods);
		}
		else {
			if(!is_array($method))
				$methods = array($method);
			else
				$methods = $method;
		}

		foreach($methods as $m) {
			if(!array_key_exists($m, $map))
				continue;

			$map[$m][$path] = $callable;
		}
	}
	private static function _unroute(&$map, $method, $path) {
		$method = strtoupper($method);

		if($method == 'ALL') {
			$methods = array_keys($map);
		}
		elseif(strpos($method, '|') !== false) {
			$methods = explode('|', $method);
			$methods = array_map('trim', $methods);
		}
		else {
			$methods = array($method);
		}
		foreach($methods as $m) {
			if(empty($m) || !array_key_exists($m, $map))
				continue;

			unset($map[$m][$path]);
		}

		return true;
	}
}
