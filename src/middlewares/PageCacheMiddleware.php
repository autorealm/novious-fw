<?php

class PageCacheMiddleware extends Middleware {
    protected $injectors = array(
        'domain'  => false,
        'prefix'  => 'page::',
        'key'     => false,
        'cache'   => false,
        'hash'    => true,
        'timeout' => 0
    );

    public function call() {
        // Check cache provider and input method
        if (!$this->injectors['cache'] || !$this->input->is("get")) {
            $this->next();
            return;
        }

        if ($this->injectors['domain']) {
            // If generate key with domain
            $key = $this->input->url();
        } elseif ($this->injectors['key']) {
            // Support create key dynamic
            if ($this->injectors['key'] instanceof \Closure) {
                $key = call_user_func($this->injectors['key'], $this->input);
            } else {
                $key = $this->injectors['key'];
            }
        } else {
            // Create key only with uri
            $key = $this->input->uri();
        }

        if ($this->injectors['hash']) {
            // Hash the key
            $key = sha1($this->injectors['hash']);
        }

        // Add prefix for the key
        $key = $this->injectors['prefix'] . $key;

        /** @var $cache \Pagon\Cache */
        $cache = $this->injectors['cache'];

        if ($page = $cache->get($key)) {
            // Try to get the page cacheF
            $page = json_decode($page, true);
            $this->output->header($page['header']);
            $this->output->display($page['body']);
            return;
        }

        // Next
        $this->next();

        $page = array();
        $page['header'] = $this->output->header();
        $page['body'] = $this->output->body();

        // Save data to cache
        $cache->set($key, json_encode($page), $this->injectors['timeout']);
    }
}