<?php



class ReadabilityAnalyzer {
	var $host ="https://en.wikipedia.org";
	var $endpoint = "/w/api.php?action=query&format=json&utf8=1";
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

		// format name for mediawiki
		$category = str_replace(" ", "_",$category);

		// TODO: check if category exists

		// fetch data for category
		$categoryContent = json_decode($this->fetchCategory($category));
		// parse JSON
		$categoryMembers = $categoryContent->query->categorymembers;
		$categoryMembersCount = count($categoryMembers);

		$pageIds = "";
		$articleCount = 0;
		$extractArray = array();
		// go through each article
		foreach ($categoryMembers as $article) {
			$pageId = $article->pageid;
			$title =  $article->title;
			// build request string
			$pageIds .= $pageId."|";

			$articleCount++;
			// send request at every 20 articles (due to API limit) and at the end
			if ($articleCount % 20 == 0 || $articleCount == $categoryMembersCount) {
				// cut off last |
				$pageIds = substr($pageIds,0,-1);

				// fetch articles identified by $pageIds
				$articleResponse = $this->fetchArticles($pageIds);

				// store result
				$articles = $articleResponse->query->pages;
				foreach ($articles as $key => $value) {
					$extractArray[$key] = $value;
				}
				// reset $pageIds
				$pageIds = "";
			}
		}

		// check if results and query have the same amount of articles
		if ($categoryMembersCount !== count($extractArray)) {
			echo "Not all articles were returned by the API.<br>";
		}

		// calculate readability
		$extractArray = $this->calculateReadabilities($extractArray);

		// sort by readability
		usort($extractArray, 'readabilitySort');

		// build table
		$table = '<table class="table table-bordered table-striped"><thead><tr><th>Article</th><th><a title="Higher is better">Readability Score</a></th><th>Extract</th><th>Further Categories</th></tr></thead><tbody>';
		foreach ($extractArray as $index => $article) {
			$categoriesString = "";
			foreach ($article->categories as $key => $categoryObject) {
				// dont include current category
				if ($categoryObject->title == $category) {
					continue;
				}
				// link to category, remove namespace for the text
				$categoriesString .= "<a href='index.php?category=".$categoryObject->title."'>".substr($categoryObject->title,(strlen($this->categoryNamespace))+1)."</a> | ";
			}
			$categoriesString = substr($categoriesString, 0,-3);
			$table .= "<tr><td><a href='".$this->host."/wiki/".$article->title."'>".$article->title."</a></td><td>".$article->readabilityFormatted."</td><td>".substr($article->extract, 0, 60)."...</td><td>".$categoriesString."</td></tr>";
		}
		$table .= '</tbody></table>';
		return $table;
	}

	function fetchCategory($category) {
		curl_setopt($this->curl, CURLOPT_URL, $this->host.$this->endpoint."&list=categorymembers&redirects=1&cmlimit=50&cmnamespace=0&cmtitle=".$category);
		$data = curl_exec($this->curl);
		return $data;
	}

	function fetchArticles($pageIds) {
		// fetch extracts
		curl_setopt($this->curl, CURLOPT_URL, $this->host.$this->endpoint."&prop=extracts&redirects=1&explaintext=1&exchars=20000&exintro=1&exlimit=20&pageids=".$pageIds);
		$data = json_decode(curl_exec($this->curl));

		// also fetch categories
		curl_setopt($this->curl, CURLOPT_URL, $this->host.$this->endpoint."&prop=categories&redirects=1&clshow=!hidden&cllimit=500&pageids=".$pageIds);
		$data2 = json_decode(curl_exec($this->curl));
		$categories = $data2->query->pages;

		// attach categories to corresponding page object
		foreach ($data->query->pages as $key => $page) {
			$pageid = $page->pageid;
			$page->categories = $categories->$pageid->categories;
		}
		return $data;
	}

	function calculateReadabilities($extractArray) {
		foreach ($extractArray as $key => $extract) {
			// get readability for each extract
			$extract->readabilityScore = $this->calculateReadabilityOnExtract($extract->extract);
			// get nicely formatted number as percentage
			$extract->readabilityFormatted = number_format($extract->readabilityScore*100,2)."%";
		}
		return $extractArray;
	}

	function calculateReadabilityOnExtract($extract) {
		// cut off extract after first paragraph
		$extract = explode("\n",$extract)[0];

		// based on https://xkcd.com/1133/
		include("simpleWords.php");

		// divie extract into words
		$words = explode(" ", $extract);

		// count amount of simple words in extract
		$simpleWordsCount = 0;
		foreach ($words as $word) {
			if (in_array($word,$simpleWords)) {
				$simpleWordsCount++;
			}
		}
		// value is then the amount of simple words in the full text
		return floatval($simpleWordsCount)/floatval(count($words));
	}
}

function readabilitySort($a, $b)
{
    return $a->readabilityScore > $b->readabilityScore;
}

?>