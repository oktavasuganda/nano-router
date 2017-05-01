<?php

Router::route_404('POST|GET', function() {
	//Router::redirect(BASE_URL . '/404');

	echo "Not Found!";
});

//INDEX
Router::route('POST|GET', '/', function() {
	echo "INDEX";
});

Router::route('POST|GET', '/user/{0}/profile', function($user_id) {
	echo "Profile for User ID: $user_id";
});

//ROUTES
if(DEV_MODE) {
	Router::route('POST|GET', '/routes', function() {
		$routes = Router::getRoutes();
		foreach($routes as $method=>$paths) {
			if(empty($paths))
				unset($routes[$method]);

			$is_get_post = in_array($method, array('GET', 'POST'));

			if($is_get_post)
				$other_method = $method == 'GET' ? 'POST' : 'GET';

			foreach($paths as $path=>$callback) {
				if($is_get_post && isset($routes[$other_method]) && isset($routes[$other_method][$path])) {
					$routes['GET/POST'][$path] = $callback;
					unset($routes[$method][$path]);
					unset($routes[$other_method][$path]);
				}
			}
		}

		foreach($routes as $method=>$paths) {
			if(empty($paths))
				continue;

			echo "<h1>" . strtoupper($method) . "</h1>";
			echo "<ul>";
			foreach($paths as $path=>$callback) {
				echo "<li><a href='" . BASE_URL . $path ."'>" . $path . "</a></li>";
			}
			echo "</ul>";
		}
	});
}