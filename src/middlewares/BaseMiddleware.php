<?PHP
//namespace APP\Middleware;

abstract class Middleware {
	private $app = null;
	public $input = null;
	public $output = null;
	public $next = null;
	
	public function handle($app=null) {
		$this->app = isset($app) ? $app : App::get_instance();
		$this->input = Request::get_instance();
		$this->output = Response::get_instance();
		$this->call();
		
		if ($this->next instanceof Middleware) {
			$this->next->handle($app);
		}
	}
	
	public function set_next(Middleware $middleware) {
		$this->next = $middleware;
	}
	
	public function get_next() {
		return $this->next;
	}
	
	public function next() {
		if (is_null($this->next) or !method_exists($this->next, 'handle')) {
			return false;
		}
		return $this->next->handle();
	}
	
	abstract public function call();
	
}

?>