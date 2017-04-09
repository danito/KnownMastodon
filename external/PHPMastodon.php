<?php

	namespace PHPMastodon;

	class Mastodon {

		public $client_id;
		public $client_secret;
		public $api_url;
		public $redirect_url;
		public $oauth_url;
		public $base_url;

		public function __construct
		(
			$client_id, #Client ID, get this by creating an app
			$client_secret, #Client Secret, get this by creating an app
			$redirect_url = 'urn:ietf:wg:oauth:2.0:oob', #Callback URL for getting an access token. Default none
			$base_url = 'https://mastodon.social' #base url. Default Mastodon.social
		) {
			$this->api_url = $base_url.'/api/v1';
			$this->oauth_url = $base_url.'/oauth';
			$this->client_id = $client_id;
			$this->client_secret = $client_secret;
			$this->redirect_url = $redirect_url;
		}

		#Generate an request URL
		public function requestURL() {
			$u = $this->oauth_url . 'authorize?response_type=code';
			$c = '&client_id=' . urlencode($this->client_id);
			$r = '&redirect_uri=' . urlencode($this->redirect_url);
			$s = '&scope=' . urlencode(''); #
			$url = $u . $c . $s . $r;
			return $url;
		}
                
                
		/**
                 * returns id, client_id, client_secret
                 * @param type $app_name
                 * @return array
                 */
		public function register_application($app_name){
			$endpoint = '/apps';
			return $this->post(array('scopes' => 'write', 'client_name' => $app_name, 'redirect_uris' => $this->redirect_url), $endpoint);
		}

		#Validate access token
		public function validate_token($token) {
			$u = $this->oauth_url . 'tokeninfo?access_token=' . $token;
			$r = $this->get_http_response_code($u);
			if ($r === "200") {
				return json_decode($this->geturl($u), true);
			} else {
				return false;
			}
		}

		#Get access_token
		public function auth($request_token) {
				return $this->token($request_token, "authorization_code");
		}

		#Refresh access_token

		public function refresh($refresh_token) {
				return $this->auth_refresh($refresh_token, "refresh_token");
		}
		
		
		private function token() {
			$parameters = array('grant_type' => 'authorization_code', 'client_id' => $this->client_id, 'client_secret' => $this->client_secret, 'redirect_uri' => $this->redirect_url);	
			$result = $this->post($parameters, $this->oauth_url);
		}
				


        #Base request
		private function get($parameters, $endpoint) {
			return json_decode($this->geturl($this->api_url . $endpoint . '?' . http_build_query($parameters)), true);
		}
		
		private function post($parameters, $endpoint) {
			return json_decode($this->posturl($this->api_url . $endpoint, $parameters), true);
		}
		

		private function get_http_response_code($url) {
			$headers = get_headers($url);
			return substr($headers[0], 9, 3);
		}

		private function geturl($url) {
			$session = curl_init($url);
			curl_setopt($session, CURLOPT_RETURNTRANSFER, 1);
			$data = curl_exec($session);
			curl_close($session);
			return $data;
		}
		private function posturl($url, $parameters) {
			$ch = curl_init();
			curl_setopt($ch, CURLOPT_URL, $url);
			curl_setopt($ch, CURLOPT_POST, 1);
			curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($parameters));
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
			$result = curl_exec($ch);
			curl_close($ch);
			error_log($result);
			return $result;
		}

	}

	?>
