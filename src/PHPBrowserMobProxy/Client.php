<?php

/**
 * Class for interacting with BrowserMobProxy when using PHP
 * Relies on the Requests library (https://github.com/rmccue/Requests)
 *
 * @class PHPBrowserMobProxy_Client
 * @category Testing
 * @package  PHPBrowserMobProxy
 * @author   Adam Goucher <adam@element34.ca>
 * @author   Chris Hartjes <chartjes@littlehart.net>
 * @license  Apache 2.0
 * @link     https://github.com/chartjes/PHPBrowserMobProxy
 */
class PHPBrowserMobProxy_Client
{
    /**
     * Class constructor
     *
     * @param string $url URL for BrowserMobProxy instance
     */
    public function __construct($url)
    {
        $this->browsermob_url = $url;
    }

    /**
     * Open connection to the proxy
     *
     * @return void
     */
    public function open()
    {
        $parts = parse_url($this->browsermob_url);
        $this->hostname = $parts["host"];

        $response = Requests::post("http://" . $this->browsermob_url . "/proxy/");

        $decoded = json_decode($response->body, true);
        if ($decoded) {
            $this->port = $decoded["port"];
        }
        $this->url = $this->hostname . ":" . $this->port;
    }

    /**
     * Close connection to the proxy
     *
     * @return void
     */
    public function close()
    {
        $response = Requests::delete("http://{$this->browsermob_url}/proxy/{$this->port}");
    }

    /**
     * Private method for encoding an array of arguments, used internally
     *
     * @param array $args array of arguments to URLencode
     *
     * @return boolean|string
     */
    private function _encodeArray($args)
    {
        if (!is_array($args)) {
            return false;
        }
        $c = 0;
        $out = '';

        foreach ($args as $name => $value) {
            if ($c++ != 0) {
                $out .= '&';
            }
            $out .= urlencode("$name").'=';
            if (is_array($value)) {
                $out .= urlencode(serialize($value));
            } else {
                $out .= urlencode("$value");
            }
        }
        return $out;
    }

    /**
     * Magic method for handling the returnig of properties
     *
     * @param string $property class property
     *
     * @return mixed
     */
    public function __get($property)
    {
        switch($property) {
            case "har":
                $proxy_handle = curl_init();
                $har_url = "http://{$this->browsermob_url}/proxy/{$this->port}/har";
                curl_setopt($proxy_handle, CURLOPT_URL, $har_url);
                curl_setopt($proxy_handle, CURLOPT_RETURNTRANSFER, true);
                $result = curl_exec($proxy_handle);
                $decoded = json_decode($result, true);
                curl_close($proxy_handle);
                return $decoded;
            default:
                return $this->$property;
        }
    }

    /**
     * Method for creating a new HAR file
     *
     * @param string $label optional label
     *
     * @return string
     */
    public function newHar($label = '')
    {
        $data = "initialPageRef=" . $label;
        $url = "http://{$this->browsermob_url}/proxy/{$this->port}/har";
        $response = Requests::put(
            $url,
            array(),
            $data
        );
        return $response;
    }

    /**
     * Method for returning HAR info about one page
     *
     * @param string $label optional label for page
     *
     * @return string
     */
    public function newPage($label = '')
    {
        $data = "pageRef=" . $label;
        $url = "http://{$this->browsermob_url}/proxy/{$this->port}/har/pageRef";
        $response = Requests::put(
            $url,
            array(),
            $data
        );
        return $response;
    }

    /**
     * Add regex pattern to the proxy blacklist
     *
     * @param string  $regexp      regular expression
     * @param integer $status_code HTTP status code
     *
     * @return string
     */
    public function blacklist($regexp, $status_code)
    {
        $data = $this->_encodeArray(
            array(
                "regex" => $regexp,
                "status" => $status_code
            )
        );
        $url = "http://{$this->browsermob_url}/proxy/{$this->port}/blacklist";
        $response = Requests::put(
            $url,
            array(),
            $data
        );
        return $response;
    }

    /**
     * Add regex pattern to the proxy whitelist
     *
     * @param string  $regexp      regular expression
     * @param integer $status_code HTTP status code
     *
     * @return string
     */
    public function whitelist($regexp, $status_code)
    {
        $data = $this->_encodeArray(
            array(
                "regex" => $regexp,
                "status" => $status_code
            )
        );
        $url = "http://{$this->browsermob_url}/proxy/{$this->port}/whitelist";
        $response = Requests::put(
            $url,
            array(),
            $data
        );
        return $response;
    }

    /**
     * Handle basic authentication using the proxy
     *
     * @param string $domain  domain name
     * @param array  $options set of options for auth
     *
     * @return string
     */
    public function basicAuth($domain, $options)
    {
        $data = json_encode($options);
        $url = "http://{$this->browsermob_url}/proxy/{$this->port}/auth/basic/{$domain}";
        $response = Requests::post(
            $url,
            array('Content-Type' => 'application/json'),
            $data
        );
        return $response;
    }

    /**
     * Send header information to the proxy
     *
     * @param array $options array of header options
     *
     * @return string
     */
    public function headers($options)
    {
        $data = json_encode($options);
        $url = "http://{$this->browsermob_url}/proxy/{$this->port}/headers";
        $response = Requests::post(
            $url,
            array('Content-Type' => 'application/json'),
            $data
        );
        return $response;
    }

    /**
     * Send JavaScript to the response interceptor
     *
     * @param string $js JavaScript
     *
     * @return string
     */
    public function responseInterceptor($js)
    {
        $url = "http://{$this->browsermob_url}/proxy/{$this->port}/interceptor/response";
        $response = Requests::post(
            $url,
            array('Content-Type' => 'x-www-form-urlencoded'),
            $js
        );
        return $response;
    }

    /**
     * Send JavaScript to the request interceptor
     *
     * @param string $js JavaScript
     *
     * @return string
     */
    public function requestInterceptor($js)
    {
        $url = "http://{$this->browsermob_url}/proxy/{$this->port}/interceptor/request";
        $response = Requests::post(
            $url,
            array('Content-Type' => 'x-www-form-urlencoded'),
            $js
        );
        return $response;
    }

    /**
     * Set limits for the HTTP connection
     *
     * @param array $options options needed for limiting connections
     *
     * @return string
     */
    public function limits($options)
    {
        $keys = array(
            "downstreamKbps" => "downstreamKbps",
            "upstreamKbps" => "upstreamKbps",
            "latency" => "latency"
        );
        foreach (array_keys($options) as $option_name) {
            if (! array_key_exists($option_name, $keys)) {
                throw new Exception($option_name . " is not a valid 'limits' option");
            }
        }
        $data = $this->_encodeArray($options);
        $url = "http://{$this->browsermob_url}/proxy/{$this->port}/limit";
        $response = Requests::put(
            $url,
            array(),
            $data
        );
        return $response;
    }

    /**
     * Set proxy timeouts
     *
     * @param array $options options needed for timeouts
     *
     * @return string
     */
    public function timeouts($options)
    {
        $keys = array(
            "request" => "requestTimeout",
            "read" => "readTimeout",
            "connection" => "connectionTimeout",
            "dns" => "dnsCacheTimeout"
        );
        foreach (array_keys($options) as $option_name) {
            if (! array_key_exists($option_name, $keys)) {
                throw new Exception($option_name . " is not a valid 'timeouts' option");
            }
        }
        $data = $this->_encodeArray($options);
        $url ="http://{$this->browsermob_url}/proxy/{$this->port}/timeout";
        $response = Requests::put(
            $url,
            array(),
            $data
        );
        return $response;
    }

    /**
     * Method for remapping host names to IP addresses for local use
     *
     * @param string $address    URL to map
     * @param string $ip_address IP address to map to URL
     *
     * @return string
     */
    public function remapHosts($address, $ip_address)
    {
        $data = json_encode(array($address => $ip_address));
        $url = "http://{$this->browsermob_url}/proxy/{$this->port}/hosts";
        $response = Requests::post(
            $url,
            array('Content-Type' => 'application/json'),
            $data
        );
        return $response;
    }

    /**
     * Method for setting how long proxy should wait for traffic to stop
     *
     * @param integer $quiet_period time in milliseconds
     * @param integer $timeout      time in milliseconds
     *
     * @return string
     */
    function waitForTrafficToStop($quiet_period, $timeout)
    {
        $data = $this->_encodeArray(
            array(
                'quietPeriodInMs' => (string)($quiet_period * 1000),
                'timeoutInMs' => (string)($timeout * 1000)
            )
        );
        $url = "http://{$this->browsermob_url}/proxy/{$this->port}/wait";
        $response = Requests::put(
            $url,
            array(),
            $data
        );
        return $response;
    }

    /**
     * Method for clearing the DNS cache set with remapHosts
     *
     * @return string
     */
    public function clearDnsCache()
    {
        $url = "http://{$this->browsermob_url}/proxy/{$this->port}/dns/cache";
        $response = Requests::delete($url);
        return $response;
    }

    /**
     * Method for rewriting URL's sent to the proxy
     *
     * @param string $match   pattern to match on
     * @param string $replace string to replace matches with
     *
     * @return string
     */
    public function rewriteUrl($match, $replace)
    {
        $data = $this->_encodeArray(
            array('matchRegex' => $match, 'replace' => $replace)
        );
        $url = "http://{$this->browsermob_url}/proxy/{$this->port}/rewrite";
        $response = Requests::put(
            $url,
            array(),
            $data
        );
        return $response;
    }

    /**
     * Method to set how many times a request should be retried
     *
     * @param integer $retry_count how many times to retry a request
     *
     * @return string
     */
    public function retry($retry_count)
    {
        $data = $this->_encodeArray(array('retrycount' => $retry_count));
        $url = "http://{$this->browsermob_url}/proxy/{$this->port}/retry";
        $response = Requests::put(
            $url,
            array(),
            $data
        );
        return $response;
    }
}
?>
