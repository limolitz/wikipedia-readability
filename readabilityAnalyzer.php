<?php

class ReadabilityAnalyzer {
	var $endpoint = "https://en.wikipedia.org/w/api.php?action=query&format=json&utf8=1";
	var $categoryNamespace = "Category";
	var $curl;

	function __construct() {
		$this->curl = curl_init();
		curl_setopt($this->curl, CURLOPT_USERAGENT, 'wikipedia-readability/1.0 (https://wasmitnetzen.de/wikipedia-readability; irgend-wikipedia-readability@wasmitnetzen.de)');
		curl_setopt($this->curl, CURLOPT_RETURNTRANSFER, 1);
	}

	function buildResults($category) {
		// check if we need to prepend namespace
		if (strpos($category,$this->categoryNamespace) === false) {
			$category = $this->categoryNamespace.":".$category;
		}
		// TODO: check if category exists
		// fetch data for category
		$categoryContent = json_decode($this->fetchCategory($category));
		// parse JSON
		$categoryMembers = $categoryContent->query->categorymembers;
		#var_dump($categoryMembers);
		$readabilityArray = array();
		foreach ($categoryMembers as $article) {
			// fetch article
			#var_dump($article);
			$pageid = $article->pageid;
			$title =  $article->title;
			echo "Handling $title ($pageid)<br>";
			// parse JSON
			$articleResponse = json_decode($this->fetchArticle($pageid));
			// calculate readability
			$readability = $this->calculateReadbility($articleContent);
		}
		// build table
	}

	function fetchCategory($category) {
		curl_setopt($this->curl, CURLOPT_URL, $this->endpoint."&list=categorymembers&cmlimit=50&cmnamespace=0&cmtitle=".$category);
		$data = curl_exec($this->curl);
		#echo $data;
		return $data;
	}

	function fetchArticle($pageid) {

	}

	function calculateReadbility($articleContent) {

	}
}

?>