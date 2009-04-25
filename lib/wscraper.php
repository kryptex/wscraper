<?php

class WScraper {
	
	var $curl;
	
	function __construct() {
		$this->curl = curl_init();
		curl_setopt($this->curl, CURLOPT_RETURNTRANSFER, TRUE);
		curl_setopt($this->curl, CURLOPT_FOLLOWLOCATION, TRUE);
		curl_setopt($this->curl, CURLOPT_COOKIEFILE, '');
		curl_setopt($this->curl, CURLOPT_SSL_VERIFYPEER, FALSE);
		curl_setopt($this->curl, CURLOPT_SSL_VERIFYHOST, FALSE);
	}


	/**
	 * This function requests a url with a GET request.
	 *
	 * @param $curl        The curl handle which should be used.
	 * @param $url         The url which should be requested.
	 * @param $parameters  Associative array with parameters which should be appended to the url.
	 * @return The content of the returned page.
	 */
	function getURLraw($url, $parameters = array(), $type = 'get') {

		$p = '';
		foreach($parameters as $k => $v) {
			if($p != '')  $p .= '&';
			$p .= urlencode($k) . '=' . urlencode($v);
		}

		switch($type) {
			case 'post':
				curl_setopt($this->curl, CURLOPT_POSTFIELDS, $p);
				curl_setopt($this->curl, CURLOPT_POST, TRUE);
				break;
				
			
			case 'get':
			default :
			
				if(strpos($url, '?') === FALSE) {
					$url .= '?' . $p;
				} else {
					$url .= '&' . $p;
				}
				curl_setopt($this->curl, CURLOPT_HTTPGET, TRUE);
		}
		
		curl_setopt($this->curl, CURLOPT_URL, $url);
		
		$curl_scraped_page = curl_exec($this->curl);
		if($curl_scraped_page === FALSE) {
			#echo('Failed to get url: ' . $url . "\n");
			#echo('Curl error: ' . curl_error($curl) . "\n");
			return FALSE;
		}

		return $curl_scraped_page;
	}




	function getURL($url, $parameters = array(), $type = 'get') {
		$page = $this->getURLraw($url, $parameters, $type);
		
		$tidy = new tidy();
		$tidy->parseString($page,  array('output-xml' => true, 'input-xml' => FALSE), 'utf8');
		$tidy->cleanRepair();
		$tidied = tidy_get_output($tidy);
		$tidied = preg_replace('/&nbsp;/s', '', $tidied);
		$tidied = preg_replace('|<!DOCTYPE[^>]*>|', '', $tidied);
		$x = new SimpleXMLElement($tidied);
		
		// print_r($x);
		// exit;
		
		$result = new WResult($x);
		return $result;
	}

}

class WResult {
	public $content;
	function __construct($content) {
		$this->content = $content;
	}
	
	private function pn($level) {
		for($i = 0; $i < $level; $i++) echo ' ';
	}
	
	public function text($expr, $deep = FALSE, $plain = FALSE, $stripnewlines = FALSE) {
		$res = $this->content->xpath($expr);
		
		if ($res == FALSE)
			throw new Exception('No results on XPath search [' . $expr . ']');
			
		$res = $res[0];
				
		if ($deep) {
			$text = $res->asXML();			
		} else {
			$text = (string) $res;
		}
		if ($plain) {
			$text = trim(strip_tags($text));
		}
		if ($stripnewlines) {
			$text = preg_replace('/(\s)+/', ' ', $text);
		}


		return $text;
	}
	
	public function extractList($expr, array $map) {
		$results = array();
		$search = $this->xpath($expr, TRUE);
		foreach($search AS $s) {
			$results[] = $s->extract($map);
			
		}
		return $results;
	}
	
	public function extract(array $map) {
		$results = array();
		foreach($map AS $name => $expr) {
			try {
				$t = $this->text($expr, TRUE, TRUE, TRUE);
				$results[$name] = $t;
			} catch(Exception $e) {}
		}
		return $results;
	}
	
	public function xpath($expr, $multiple = FALSE) {
		$res = $this->content->xpath($expr);
		if ($res == FALSE)
			throw new Exception('No results on XPath search [' . $expr . ']');
		
		if ($multiple) {
			$results = array();
			if (!is_array($res)) $res = array($res);
			foreach($res AS $r) {
				if(!is_a($r, 'SimpleXMLElement')) 
					throw new Exception('Not valid XML result from XPath [' . $expr . ']');
				$results[] = new WResult(new SimpleXMLElement($r->asXML()));
			}
			return $results;
			
		} else {
			if (is_array($res)) $res = $res[0];
			if(!is_a($res, 'SimpleXMLElement')) 
				throw new Exception('Not valid XML result from XPath [' . $expr . ']');
			return new WResult(new SimpleXMLElement($res->asXML()));
		}
		
	}
	
	public function debugPage() {
		echo('<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN"
			"http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
		<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="en" lang="en">
		<head>
			<meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
			<title>Debug content</title>
		</head>
		<body><h1>Debug page</h1><pre>');		
		$this->debug($this->content);
		echo('</pre></body>
		</html>');
	}

	public function debug($res, $level = 0, $trace = '') {

		if(is_a($res, 'SimpleXMLElement')) {
			foreach($res AS $k => $v) {
				$this->pn($level);
				$vt = str_replace("\n", '', $v);

				echo('<a style="color: #600" title="' . $trace . '">' . $k . '</a>' );
				if ($vt) {
					echo(' <span style="color: #999">' .  $vt . '</span>');					
				}
				if (isset($res['id'])) 
					echo('<span style="color: #393">[' . $res['id'] . ']</span>');
				if (isset($res['class'])) 
					echo('<span style="color: #339">(' . $res['class'] . ')</span>');
				echo("\n");

				$this->debug($v, $level+1, $trace . '/' . $k);

			}
		} elseif(is_string($res)) {
			$this->pn($level);
			echo 'debug:' . $res;
			echo("\n");

		} else {
			echo('debug: '); print_r($res);
		}


	}
	
}





























