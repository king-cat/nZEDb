<?php
namespace nzedb;

use nzedb\utility\Misc;

class ADM
{
	/**
	 * Override if 18 years+ or older
	 * Define Adult DVD Marketplace url
	 * Needed Search Queries Constant
	 */
	const ADMURL = "http://www.adultdvdmarketplace.com";
	const IF18 = "http://www.adultdvdmarketplace.com/xcart/adult_dvd/disclaimer.php?action=enter&site=intl&return_url=";
	const TRAILINGSEARCH = "/xcart/adult_dvd/advanced_search.php?sort_by=relev&title=";

	/**
	 * Define a cookie file location for curl
	 * @var string string
	 */
	public $cookie = "";

	/**
	 * Direct Link given from outside url doesn't do a search
	 * @var string
	 */
	public $directLink = "";

	/**
	 * Set this for what you are searching for.
	 * @var string
	 */
	public $searchTerm = "";

	/**
	 * Sets the directurl for the return results array
	 * @var string
	 */
	protected $_directUrl = "";

	/**
	 * Simple Html Dom Object
	 *
	 * @var \simple_html_dom
	 */
	protected $_html;

	/**
	 * POST Paramaters for getUrl Method
	 */
	protected $_postParams;

	/**
	 * Results returned from each method
	 *
	 * @var array
	 */
	protected $_res = [];

	/**
	 * Curl Raw Html
	 */
	protected $_response;

	/**
	 * Add this to popurl to get results
	 * @var string
	 */
	protected $_trailUrl = "";

	/**
	 * This is set in the getAll method
	 *
	 * @var string
	 */
	protected $_title = "";

	public function __construct()
	{
		$this->_html = new \simple_html_dom();
		if (isset($this->cookie)) {
			$this->getUrl();
		}
	}

	/**
	 * Remove from memory.
	 */
	public function __destruct()
	{
		$this->_html->clear();
		unset($this->_response);
		unset($this->_res);
	}

	/**
	 * Get Box Cover Images
	 * @return array - boxcover,backcover
	 */
	public function covers()
	{
		$baseUrl = 'http://www.adultdvdmarketplace.com/';
		if ($ret = $this->_html->find('a[rel=fancybox-button]', 0)) {
			if (isset($ret->href)) {
				if (preg_match('/images\/.*[0-9]+\.jpg/i', $ret->href, $matches)
				) {
					$this->_res['boxcover'] = $baseUrl . $matches[0];
					$this->_res['backcover'] = $baseUrl . preg_replace('/front/i', 'back', $matches[0]);
				}
			}
		} elseif ($ret = $this->_html->find('img[rel=license]', 0)) {
			if (preg_match('/images\/.*[0-9]+\.jpg/i', $ret->src, $matches)) {
				$this->_res['boxcover'] = $baseUrl . $matches[0];
			}
		}
		return $this->_res;
	}

	/**
	 * Gets the sypnosis
	 * @return array
	 */
	public function sypnosis()
	{
		$this->_res['sypnosis'] = "N/A";
		foreach ($this->_html->find('h3') as $heading) {
			if (trim($heading->plaintext) == "Description") {
				$this->_res['sypnosis'] = trim($heading->next_sibling()->plaintext);
			}
		}

		return $this->_res;
	}

	/**
	 * Get Product Informtion and Director
	 *
	 *
	 * @return array
	 */
	public function productInfo()
	{
		foreach ($this->_html->find('ul.list-unstyled li') as $li) {
					$category = explode(":", $li->plaintext);
					switch (trim($category[0])) {
						case "Director":
							$this->_res['director'] = trim($category[1]);
							break;
						case "Format":
						case "Studio":
						case "Released":
						case "SKU":
							$this->_res['productinfo'][trim($category[0])] = trim($category[1]);
					}
				}
		return $this->_res;
	}

	/**
	 * Gets the cast members
	 * @return array
	 */
	public function cast()
	{
		$cast = [];
		foreach ($this->_html->find('h3') as $heading) {
			if (trim($heading->plaintext) == "Cast") {
				for ($next = $heading->next_sibling(); $next && $next->nodeName != 'h3'; $next = $next->next_sibling()) {
					if (preg_match_all('/search_performerid/', $next->href, $matches)) {
						$cast[] = trim($next->plaintext);
					}
				}
			}
		}
		$this->_res['cast'] = array_unique($cast);

		return $this->_res;
	}

	/**
	 * Gets categories
	 * @return array
	 */
	public function genres()
	{
		$genres = [];
		foreach ($this->_html->find('ul.list-unstyled li') as $li) {
			$category = explode(":", $li->plaintext);
			if (trim($category[0]) == "Category") {
				$g = explode(",", $category[1]);
				foreach ($g as $genre) {
					$genres[] = trim($genre);
				}
				$this->_res['genres'] = & $genres;
			}
		}

		return $this->_res;
	}

	/**
	 * Searches for match against searchterm
	 * @return bool - true if search = 100%
	 */
	public function search()
	{
		$result = false;
		if (isset($this->searchTerm)) {
			$this->_trailUrl = self::TRAILINGSEARCH . urlencode($this->searchTerm);
			if ($this->getUrl() !== false) {
				if ($ret = $this->_html->find('img[rel=license]')) {
					if (count($ret) > 0) {
						foreach ($this->_html->find('img[rel=license]') as $ret) {
							if (isset($ret->alt)) {
								$title = trim($ret->alt, '"');
								$title = preg_replace('/XXX/', '', $title);
								$comparetitle = preg_replace('/[^\w]/', '', $title);
								$comparesearch = preg_replace('/[^\w]/', '', $this->searchTerm);
								similar_text($comparetitle, $comparesearch, $p);
								if ($p == 100) {
									if (preg_match('/\/(?<sku>\d+)\.jpg/i', $ret->src, $matches)) {
										$this->_title     = trim($title);
										$this->_trailUrl  = "/dvd_view_" . (string)$matches['sku'] . ".html";
										$this->_directUrl = self::ADMURL . $this->_trailUrl;
										if ($this->getUrl() !== false) {
											$result = true;
										}
									}
								}
							}
						}
					}
				}
			}
		}
		return $result;
	}

	/**
	 * Gets all information
	 * @return array
	 */
	public function getAll()
	{
		$results = [];
		if (isset($this->_directUrl)) {
			$results['title'] = $this->_title;
			$results['directurl'] = $this->_directUrl;
		}

		if (is_array($this->sypnosis())) {
			$results = array_merge($results, $this->sypnosis());
		}

		if (is_array($this->productInfo())) {
			$results = array_merge($results, $this->productInfo());
		}

		if (is_array($this->cast())) {
			$results = array_merge($results, $this->cast());
		}

		if (is_array($this->genres())) {
			$results = array_merge($results, $this->genres());
		}

		if (is_array($this->covers())) {
			$results = array_merge($results, $this->covers());
		}

		$results = empty($results) ? false : $results;
		return $results;
	}

	/**
	 * Get Raw html of webpage
	 *
	 * @param bool $usepost
	 *
	 * @return bool
	 */
	private function getUrl($usepost = false)
	{
		if (isset($this->_trailUrl)) {
			$ch = curl_init(self::ADMURL . $this->_trailUrl);
		} else {
			$ch = curl_init(self::IF18);
		}

		if ($usepost === true) {
			curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
			curl_setopt($ch, CURLOPT_POST, 1);
			curl_setopt($ch, CURLOPT_POSTFIELDS, $this->_postParams);
		}

		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_HEADER, 0);
		curl_setopt($ch, CURLOPT_VERBOSE, 0);
		curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
		curl_setopt($ch, CURLOPT_USERAGENT, "Firefox/2.0.0.1");
		curl_setopt($ch, CURLOPT_FAILONERROR, 1);

		if (isset($this->cookie)) {
			curl_setopt($ch, CURLOPT_COOKIEJAR, $this->cookie);
			curl_setopt($ch, CURLOPT_COOKIEFILE, $this->cookie);
		}

		curl_setopt_array($ch, Misc::curlSslContextOptions());
		$this->_response = curl_exec($ch);

		if (!$this->_response) {
			curl_close($ch);
			return false;
		}

		curl_close($ch);
		$this->_html->load($this->_response);
		return true;
	}
}
