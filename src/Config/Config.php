<?php

/**
 * Part of the AmazonGiftCode package.
 * Author: Kashyap Merai <kashyapk62@gmail.com>
 *
 */


namespace kamerk22\AmazonGiftCode\Config;


class Config implements ConfigInterface
{

    /**
     * The current Endpoint version.
     *
     * @var string
     */
    private $_endpoint;

    /**
     * The AWS Access Key.
     *
     * @var string
     */
    private $_accessKey;

    /**
     * The AWS Secret.
     *
     * @var string
     */
    private $_secretKey;

    /**
     * The Amazon Gift Card Partner.
     *
     * @var string
     */
    private $_partnerId;

    /**
     * The Amazon Gift Card Currency.
     *
     * @var string
     */
    private $_currency;


    public function __construct($key, $secret, $partner, $endpoint, $currency)
    {
        // echo 'This is the currency var: ' . $currency;
        // exit();
        $this->setAccessKey($key ?: config('amazongiftcode.key'));
        $this->setSecret($secret ?: config('amazongiftcode.secret'));
        $this->setPartner($partner ?: config('amazongiftcode.partner'));
        $this->setEndpoint($endpoint ?: config('amazongiftcode.endpoint'));
        $this->setCurrency($currency ?: config('amazongiftcode.currency'));
    }

    /**
     * @return String
     */
    public function getEndpoint() 
    {
        return (string) $this->_endpoint;
    }


    /**
     * @param $endpoint
     * @return ConfigInterface
     */
    public function setEndpoint($endpoint)
    {
        $this->_endpoint = parse_url($endpoint, PHP_URL_HOST);

        return $this;
    }

    /**
     * @return String
     */
    public function getAccessKey()
    {
        return (string) $this->_accessKey;
    }

    /**
     * @param String $key
     * @return ConfigInterface
     */
    public function setAccessKey($key)
    {
        $this->_accessKey = $key;



        return $this;
    }

    /**
     * @return String
     */
    public function getSecret() 
    {
        return (string) $this->_secretKey;
    }

    /**
     * @param String $secret
     * @return ConfigInterface
     */
    public function setSecret($secret)
    {
        $this->_secretKey = $secret;

        return $this;
    }

    /**
     * @return String
     */
    public function getCurrency()
    {
        return (string) $this->_currency;
    }

    /**
     * @param String $currency
     * @return ConfigInterface
     */
    public function setCurrency($currency) 
    {
        $this->_currency = $currency;

        return $this;
    }

    /**
     * @return String
     */
    public function getPartner() 
    {
        return (string) $this->_partnerId;
    }

    /**
     * @param String $partner
     * @return ConfigInterface
     */
    public function setPartner($partner)
    {
        $this->_partnerId = $partner;

        return $this;
    }
}