<?php

/**
 * Part of the AmazonGiftCode package.
 * Author: Kashyap Merai <kashyapk62@gmail.com>
 *
 */


namespace kamerk22\AmazonGiftCode\AWS;


use kamerk22\AmazonGiftCode\Client\Client;
use kamerk22\AmazonGiftCode\Config\Config;
use kamerk22\AmazonGiftCode\Exceptions\AmazonErrors;
use kamerk22\AmazonGiftCode\Response\CancelResponse;
use kamerk22\AmazonGiftCode\Response\CreateResponse;

class AWS
{
    public $SERVICE_NAME = 'AGCODService';
    public $ACCEPT_HEADER = 'accept';
    public $CONTENT_HEADER = 'content-type';
    public $HOST_HEADER = 'host';
    public $X_AMZ_DATE_HEADER = 'x-amz-date';
    public $X_AMZ_TARGET_HEADER = 'x-amz-target';
    public $AUTHORIZATION_HEADER = 'Authorization';
    public $AWS_SHA256_ALGORITHM = 'AWS4-HMAC-SHA256';
    public $KEY_QUALIFIER = 'AWS4';
    public $TERMINATION_STRING = 'aws4_request';
    public $CREATE_GIFT_CARD_SERVICE = 'CreateGiftCard';
    public $CANCEL_GIFT_CARD_SERVICE = 'CancelGiftCard';

    private $_config;


    /**
     * AWS constructor.
     * @param Config $config
     */
    public function __construct(Config $config)
    {
        $this->_config = $config;
    }


    public function getCode($amount)
    {
        $CREATE_GIFT_CARD_SERVICE = 'CreateGiftCard';
        $serviceOperation = $CREATE_GIFT_CARD_SERVICE;

        $payload = $this->getGiftCardPayload($amount);
        $canonicalRequest = $this->getCanonicalRequest($serviceOperation, $payload);
        $dateTimeString = $this->getTimestamp();
        $result = json_decode($this->makeRequest($payload, $canonicalRequest, $serviceOperation, $dateTimeString), true);
        return new CreateResponse($result);

    }

    /**
     * @param $creationRequestId
     * @param $gcId
     * @return CancelResponse
     */
    public function cancelCode($creationRequestId, $gcId)
    {
        $serviceOperation = $this->CANCEL_GIFT_CARD_SERVICE;
        $payload = $this->getCancelGiftCardPayload($creationRequestId, $gcId);
        $canonicalRequest = $this->getCanonicalRequest($serviceOperation, $payload);
        $dateTimeString = $this->getTimestamp();
        $result = json_decode($this->makeRequest($payload, $canonicalRequest, $serviceOperation, $dateTimeString), true);
        return new CancelResponse($result);
    }

    /**
     * @param $payload
     * @param $canonicalRequest
     * @param $serviceOperation
     * @param $dateTimeString
     * @return String
     */
    public function makeRequest($payload, $canonicalRequest, $serviceOperation, $dateTimeString)
    {
        $KEY_QUALIFIER = 'AWS4';
        $canonicalRequestHash = $this->buildHash($canonicalRequest);
        $stringToSign = $this->buildStringToSign($canonicalRequestHash);
        $authorizationValue = $this->buildAuthSignature($stringToSign);

        $secretKey = $this->_config->getSecret();
        $endpoint = $this->_config->getEndpoint();
        $regionName = $this->getRegion();

        $SERVICE_NAME = 'AGCODService';
        $serviceTarget = 'com.amazonaws.agcod.' . $SERVICE_NAME . '.' . $serviceOperation;
        $dateString = $this->getDateString();

        $signatureAWSKey = $KEY_QUALIFIER . $secretKey;

        $kDate = $this->hmac($dateString, $signatureAWSKey);
        $kDate_hexis = $this->hmac($dateString, $signatureAWSKey, false);
        $kRegion = $this->hmac($regionName, $kDate);
        $kRegion_hexis = $this->hmac($regionName, $kDate, false);
        $kService_hexis = $this->hmac($SERVICE_NAME, $kRegion, false);

        $url = 'https://' . $endpoint . '/' . $serviceOperation;
        $headers = $this->buildHeaders($payload, $authorizationValue, $dateTimeString, $serviceTarget);
        return (new Client())->request($url, $headers, $payload);
    }

    /**
     * @param $payload
     * @param $authorizationValue
     * @param $dateTimeString
     * @param $serviceTarget
     * @return array
     */
    public function buildHeaders($payload, $authorizationValue, $dateTimeString, $serviceTarget)
    {
        $ACCEPT_HEADER = $this->ACCEPT_HEADER;
        $X_AMZ_DATE_HEADER = $this->X_AMZ_DATE_HEADER;
        $X_AMZ_TARGET_HEADER = $this->X_AMZ_TARGET_HEADER;
        $AUTHORIZATION_HEADER = $this->AUTHORIZATION_HEADER;
        return [
            'Content-Type:' . $this->getContentType(),
            'Content-Length: ' . strlen($payload),
            $AUTHORIZATION_HEADER . ':' . $authorizationValue,
            $X_AMZ_DATE_HEADER . ':' . $dateTimeString,
            $X_AMZ_TARGET_HEADER . ':' . $serviceTarget,
            $ACCEPT_HEADER . ':' . $this->getContentType()
        ];
    }

    /**
     * @param $stringToSign
     * @return string
     */
    public function buildAuthSignature($stringToSign)
    {
        $AWS_SHA256_ALGORITHM = $this->AWS_SHA256_ALGORITHM;
        $SERVICE_NAME = $this->SERVICE_NAME;
        $TERMINATION_STRING = $this->TERMINATION_STRING;
        $ACCEPT_HEADER = $this->ACCEPT_HEADER;
        $HOST_HEADER = $this->HOST_HEADER;
        $X_AMZ_DATE_HEADER = $this->X_AMZ_DATE_HEADER;
        $X_AMZ_TARGET_HEADER = $this->X_AMZ_TARGET_HEADER;

        $awsKeyId = $this->_config->getAccessKey();
        $regionName = $this->getRegion();

        $dateString = $this->getDateString();
        $derivedKey = $this->buildDerivedKey();
        // Calculate signature per http://docs.aws.amazon.com/general/latest/gr/sigv4-calculate-signature.html
        $finalSignature = $this->hmac($stringToSign, $derivedKey, false);

        // Assemble Authorization Header with signing information
        // per http://docs.aws.amazon.com/general/latest/gr/sigv4-add-signature-to-request.html
        $authorizationValue =
            $AWS_SHA256_ALGORITHM
            . ' Credential=' . $awsKeyId
            . '/' . $dateString . '/' . $regionName . '/' . $SERVICE_NAME . '/' . $TERMINATION_STRING . ','
            . ' SignedHeaders='
            . $ACCEPT_HEADER . ';' . $HOST_HEADER . ';' . $X_AMZ_DATE_HEADER . ';' . $X_AMZ_TARGET_HEADER . ','
            . ' Signature='
            . $finalSignature;

        return $authorizationValue;
    }

    /**
     * @param $canonicalRequestHash
     * @return string
     */
    public function buildStringToSign($canonicalRequestHash)
    {
        $AWS_SHA256_ALGORITHM = $this->AWS_SHA256_ALGORITHM;
        $TERMINATION_STRING = $this->TERMINATION_STRING;
        $SERVICE_NAME = $this->SERVICE_NAME;
        $regionName = $this->getRegion();
        $dateTimeString = $this->getTimestamp();
        $dateString = $this->getDateString();
        $stringToSign = "$AWS_SHA256_ALGORITHM\n$dateTimeString\n$dateString/$regionName/$SERVICE_NAME/$TERMINATION_STRING\n$canonicalRequestHash";

        return $stringToSign;
    }

    /**
     * @param bool $rawOutput
     * @return string
     */
    public function buildDerivedKey($rawOutput = true)
    {
        $KEY_QUALIFIER = $this->KEY_QUALIFIER;
        $TERMINATION_STRING = $this->TERMINATION_STRING;
        $SERVICE_NAME = $this->SERVICE_NAME;

        $awsSecretKey = $this->_config->getSecret();
        // Append Key Qualifier, "AWS4", to secret key per http://docs.aws.amazon.com/general/latest/gr/signature-v4-examples.html
        $signatureAWSKey = $KEY_QUALIFIER . $awsSecretKey;
        $regionName = $this->getRegion();
        $dateString = $this->getDateString();

        $kDate = $this->hmac($dateString, $signatureAWSKey);
        $kRegion = $this->hmac($regionName, $kDate);
        $kService = $this->hmac($SERVICE_NAME, $kRegion);

        // Derived the Signing key (derivedKey aka kSigning)
        return $this->hmac($TERMINATION_STRING, $kService, $rawOutput);
    }

    /**
     * @return string
     */
    public function getRegion()
    {


        $endpoint = $this->_config->getEndpoint();
        $regionName = 'us-east-1';

        if ($endpoint === 'agcod-v2-eu.amazon.com' || $endpoint === 'agcod-v2-eu-gamma.amazon.com') {
            $regionName = 'eu-west-1';
        } else if ($endpoint === 'agcod-v2-fe.amazon.com' || $endpoint === 'agcod-v2-fe-gamma.amazon.com') {
            $regionName = 'us-west-2';
        }
        return $regionName;
    }


    /**
     * @param $amount
     * @return string
     */
    public function getGiftCardPayload($amount, $creationId = null)
    {
        $amount = trim($amount);
        $payload = [
           "creationRequestId" => $this->_config->getPartner() . "_" . time(),
            'partnerId' => $this->_config->getPartner(),
            'value' =>
                [
                    'currencyCode' => $this->_config->getCurrency(),
                    'amount' => (float)$amount
                ]
        ];
        return json_encode($payload);
    }

    /**
     * @param $creationRequestId
     * @param $gcId
     * @return string
     */
    public function getCancelGiftCardPayload($creationRequestId, $gcId)
    {
        $gcResponseId = trim($gcId);
        $payload = [
            'creationRequestId' => $creationRequestId,
            'partnerId' => $this->_config->getPartner(),
            'gcId' => $gcResponseId
        ];
        return json_encode($payload);
    }

    /**
     * @param $serviceOperation
     * @param $payload
     * @return string
     */
    public function getCanonicalRequest($serviceOperation, $payload)
    {
        $HOST_HEADER = 'host';
        $X_AMZ_DATE_HEADER = $this->X_AMZ_DATE_HEADER;
        $X_AMZ_TARGET_HEADER = $this->X_AMZ_TARGET_HEADER;
        $ACCEPT_HEADER = $this->ACCEPT_HEADER;
        $payloadHash = $this->buildHash($payload);
        $canonicalHeaders = $this->buildCanonicalHeaders($serviceOperation);
        $canonicalRequest = "POST\n/$serviceOperation\n\n$canonicalHeaders\n\n$ACCEPT_HEADER;$HOST_HEADER;$X_AMZ_DATE_HEADER;$X_AMZ_TARGET_HEADER\n$payloadHash";
        return $canonicalRequest;
    }

    /**
     * @param $data
     * @return string
     */
    public function buildHash($data)
    {
        return hash('sha256', $data);
    }

    /**
     * @return false|string
     */
    public function getTimestamp()
    {
        return gmdate('Ymd\THis\Z');
    }

    /**
     * @param $data
     * @param $key
     * @param bool $raw
     * @return string
     */
    public function hmac($data, $key, $raw = true)
    {
        return hash_hmac('sha256', $data, $key, $raw);
    }

    /**
     * @return bool|string
     */
    public function getDateString()
    {
        return substr($this->getTimestamp(), 0, 8);
    }

    /**
     * @return string
     */
    public function getContentType()
    {
        return 'application/json';
    }

    /**
     * @param $serviceOperation
     * @return string
     */
    public function buildCanonicalHeaders($serviceOperation)
    {
        $ACCEPT_HEADER = $this->ACCEPT_HEADER;
        $HOST_HEADER = $this->HOST_HEADER;
        $X_AMZ_DATE_HEADER = $this->X_AMZ_DATE_HEADER;
        $X_AMZ_TARGET_HEADER = $this->X_AMZ_TARGET_HEADER;
        $dateTimeString = $this->getTimestamp();
        $endpoint = $this->_config->getEndpoint();
        $contentType = $this->getContentType();
        return
            "$ACCEPT_HEADER:$contentType\n$HOST_HEADER:$endpoint\n$X_AMZ_DATE_HEADER:$dateTimeString\n$X_AMZ_TARGET_HEADER:com.amazonaws.agcod.AGCODService.$serviceOperation";
    }
}
