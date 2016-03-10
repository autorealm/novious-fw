<?PHP
//namespace middleware;

class TestMiddleware extends Middleware {
	
	public function call() {
		$request = $this->input;
		var_dump($request::data());
	}
	
}

?>