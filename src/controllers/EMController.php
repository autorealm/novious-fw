<?php
class EMController extends Controller {

	public function __construct() {
		parent::__construct();
	}
	
	function test() {
		// Load library
		$this->load->library('memcacher');
		
		// Lets try to get the key
		$results = $this->memcacher->get('test');
		
		// If the key does not exist it could mean the key was never set or expired
		if (!$results) {
			// Modify this Query to your liking!
			$query = $this->db->get('members', 7000);
			
			// Lets store the results
			$this->memcacher->add('test', $query->result());
			
			// Output a basic msg
			echo "Alright! Stored some results from the Query... Refresh Your Browser";
		} else {
			// Output
			var_dump($results);
			
			// Now let us delete the key for demonstration sake!
			$this->memcacher->delete('test');
		}
		
	}
	
	function stats() {
		$this->load->library('memcacher');
		
		echo $this->memcacher->getversion();
		echo "<br/>";
		
		// We can use any of the following "reset, malloc, maps, cachedump, slabs, items, sizes"
		$p = $this->memcacher->getstats("sizes");
		
		var_dump($p);
	}

	function sae() {
		$mmc = memcache_init();
		if ($mmc == false) {
			echo "mc init failed\n";
		} else {
			memcache_set($mmc,"key","value");
			echo memcache_get($mmc,"key");
		}
	}
	
}