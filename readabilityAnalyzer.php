<?php



class ReadabilityAnalyzer {
	var $endpoint = "https://en.wikipedia.org/w/api.php?action=query&format=json&utf8=1";
	var $categoryNamespace = "Category";
	var $curl;
	var $curlLog;

	function __construct() {
		$this->curl = curl_init();
		curl_setopt($this->curl, CURLOPT_USERAGENT, 'wikipedia-readability/1.0 (https://wasmitnetzen.de/wikipedia-readability; irgend-wikipedia-readability@wasmitnetzen.de)');
		curl_setopt($this->curl, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($this->curl, CURLOPT_VERBOSE, 1);

		$this->curlLog = fopen("curl.log", 'w');
		curl_setopt($this->curl, CURLOPT_STDERR, $this->curlLog);
	}

	function  __destruct() {
		fclose($this->curlLog);
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
		$categoryMembersCount = count($categoryMembers);
		#var_dump($categoryMembers);
		$pageIds = "";
		$articleCount = 0;
		$extractArray = array();
		foreach ($categoryMembers as $article) {
			// fetch article
			#var_dump($article);
			$pageId = $article->pageid;
			$title =  $article->title;
			#echo "Handling $title ($pageId)<br>";
			$pageIds .= $pageId."|";
			$articleCount++;
			if ($articleCount % 20 == 0 || $articleCount == $categoryMembersCount) {
				// cut off last |
				$pageIds = substr($pageIds,0,-1);
				$articleResponse = json_decode($this->fetchArticles($pageIds));
				$articles = $articleResponse->query->pages;

				foreach ($articles as $key => $value) {
					$extractArray[$key] = $value;
				}
				$pageIds = "";
			}
		}
		echo "Queried for $categoryMembersCount articles, got ".count($extractArray).".<br>";
		if ($categoryMembersCount !== count($extractArray)) {
			echo "Not all articles were returned by API.<br>";
		}
		#var_dump($extractArray);


		// calculate readability
		$extractArray = $this->calculateReadbilities($extractArray);

		// sort by readability
		usort($extractArray, 'readabilitySort');
		// build table
		$table = '<table class="table table-bordered"><thead><tr><th>PageId</th><th>Title</th><th>Readability Score</th><th>Extract</th></tr></thead><tbody>';
		foreach ($extractArray as $index => $article) {
			$table .= "<tr><td>".$article->pageid."</td><td>".$article->title."</td><td>".$article->readabilityScore."</td><td>".substr($article->extract, 0, 60)."...</td></tr>";
		}
		$table .= '</tbody></table>';
		return $table;
	}

	function fetchCategory($category) {
		curl_setopt($this->curl, CURLOPT_URL, $this->endpoint."&list=categorymembers&cmlimit=50&cmnamespace=0&cmtitle=".$category);
		$data = curl_exec($this->curl);
		#echo $data;
		return $data;
	}

	function fetchArticles($pageIds) {
		#echo "Querying for ".$pageIds." articles.<br>";
		curl_setopt($this->curl, CURLOPT_URL, $this->endpoint."&prop=extracts&explaintext=1&exchars=10000&exintro=1&exlimit=20&pageids=".$pageIds);
		$data = curl_exec($this->curl);
		#echo $data."<br>";
		return $data;
	}

	function calculateReadbilities($extractArray) {
		foreach ($extractArray as $key => $extract) {
			$extract->readabilityScore = $this->calculateReadbilityOnExtract($extract->extract);
		}
		return $extractArray;
	}

	function calculateReadbilityOnExtract($extract) {
		return strlen($extract);
	}
}

function readabilitySort($a, $b)
{
    return $a->readabilityScore > $b->readabilityScore;
}

?>