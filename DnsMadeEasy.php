<?php
function __autoload($class) {
    require_once $class . '.php';
}

class DnsMadeEasy extends DnsMadeEasyBase
{
	public function getDomains()
	{
		try {
			$apiResponse = $this->_curl('domains');
		}
		catch (Exception $e) {
			throw new DnsMadeEasyException('Unable to retrieve domain listing.', NULL, $e);
		}

		if ($this->_httpStatusCode == 200) {
			$domains = json_decode($apiResponse, TRUE);

			if (!empty($domains) && isset($domains['list'])) {
				$domains = $domains['list'];
			}
			else {
				$domains = array($apiResponse);
			}

			return $domains;
		}

		$this->_setErrors($apiResponse);

		return FALSE;
	}

	public function deleteAllDomains()
	{
		try {
			$apiResponse = $this->_curl('domains', DnsMadeEasyMethod::DELETE);
		}
		catch (Exception $e) {
			throw new DnsMadeEasyException('Unable to delete all domains.', NULL, $e);
		}

		if ($this->_httpStatusCode == 200) {
			return TRUE;
		}

		$this->_setErrors($apiResponse);

		return FALSE;
	}

	public function getDomain($domain)
	{
		if (empty($domain)) {
			throw new DnsMadeEasyException('The domain is required.');
		}

		try {
			$apiResponse = $this->_curl("domains/$domain");
		}
		catch (Exception $e) {
			throw new DnsMadeEasyException("Unable to retrieve domain info for: $domain.", NULL, $e);
		}

		if ($this->_httpStatusCode == 200) {
			return new DnsMadeEasyDomain(json_decode($apiResponse, TRUE));
		}

		$this->_setErrors($apiResponse);

		return FALSE;
	}

	public function deleteDomain($domain)
	{
		if (empty($domain)) {
			throw new DnsMadeEasyException('The domain is required.');
		}

		try {
			$apiResponse = $this->_curl("domains/$domain", DnsMadeEasyMethod::DELETE);
		}
		catch (Exception $e) {
			throw new DnsMadeEasyException("Unable to delete domain: $domain.", NULL, $e);
		}

		if ($this->_httpStatusCode == 200) {
			return TRUE;
		}

		$this->_setErrors($apiResponse);

		// API doesn't return an error message if domain doesn't exist, so manually add one.
		if ($this->_httpStatusCode == 404) {
			$this->_errors = array("Unable to delete domain: $domain. Domain not found.");
		}

		return FALSE;
	}

	public function addDomain($domain)
	{
		if (empty($domain)) {
			throw new DnsMadeEasyException('The domain is required.');
		}

		try {
			$apiResponse = $this->_curl("domains/$domain", DnsMadeEasyMethod::PUT);
		}
		catch (Exception $e) {
			throw new DnsMadeEasyException("Unable to add domain: $domain.", NULL, $e);
		}

		if ($this->_httpStatusCode == 201) {
			return new DnsMadeEasyDomain(json_decode($apiResponse, TRUE));
		}

		$this->_setErrors($apiResponse, 400);

		return FALSE;
	}

	public function getDnsRecords($domain, $type = NULL, $gtdLocation = NULL)
	{
		if (empty($domain)) {
			throw new DnsMadeEasyException('The domain is required.');
		}

		$url = "domains/$domain/records?";

		if (!empty($type)) {
			$url .= "type=$type&";
		}

		if (!empty($gtdLocation)) {
			$url .= "gtdLocation=$gtdLocation&";
		}

		try {
			$apiResponse = $this->_curl($url);
		}
		catch (Exception $e) {
			throw new DnsMadeEasyException("Unable to retrieve DNS records for: $domain.", NULL, $e);
		}

		if ($this->_httpStatusCode == 200) {
			$records = $this->_getRecords(json_decode($apiResponse, TRUE));

			return $records;
		}

		$this->_setErrors($apiResponse);

		return FALSE;
	}

	public function addDnsRecord($domain, $record)
	{
		if (empty($domain)) {
			throw new DnsMadeEasyException('The domain is required.');
		}

		if (empty($record)) {
			throw new DnsMadeEasyException('The record is required.');
		}

		try {
			$apiResponse = $this->_curl('domains/$domain/records', DnsMadeEasyMethod::POST, $record);
		}
		catch (Exception $e) {
			throw new DnsMadeEasyException(sprintf('Unable to add DNS record: %s (%s)', print_r($record, TRUE), $domain), NULL, $e);
		}

		if ($this->_httpStatusCode == 201) {
			return $this->requestId();
		}

		$this->_setErrors($apiResponse, 400);

		return FALSE;
	}

	private function _getRecords($recordsArray)
	{
		if (empty($recordsArray)) {
			return array();
		}

		$records = array();

		foreach($recordsArray as $record) {
			// TODO: i know there's a design pattern meant to address this...
			switch($record['type']) {
				case 'A':
					$records[] = new DnsMadeEasyARecord($record);
				break;

				case 'AAAA':
					$records[] = new DnsMadeEasyARecord($record);
				break;

				case 'HTTPRED':
					$records[] = new DnsMadeEasyHttpRedirectRecord($record);
				break;

				case 'MX':
					$records[] = new DnsMadeEasyMxRecord($record);
				break;

				case 'SRV':
					$records[] = new DnsMadeEasySrvRecord($record);
				break;

				default:
					$records[] = new DnsMadeEasyRecord($record);
			}
		}

		return $records;
	}
}
?>
