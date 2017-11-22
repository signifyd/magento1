<?php

class Signifyd_Connect_Helper_Payment_Other extends Signifyd_Connect_Helper_Payment_Default
{
    /**
     * Keys for exact additional information search. Better performance
     * Will search for a exact match on additional information fields names
     * 
     * Order values with most popular keys first (for better performance)
     * 
     * @var array
     */
    protected $avsResponseCodeKeys = array('cc_avs_status', 'avs_status', 'avsstatus');

    /**
     * Expressions for additional information search
     * Will search for a expression match on additional information fields names
     * 
     * Order values with most popular expressions first (for better performance)
     * 
     * @var array
     */
    protected $avsResponseCodeExpressions = array('avs_status', 'avsstatus', 'avs');

    /**
     * Works the same way of $avsResponseCodeKeys
     * @var array
     */
    protected $cvvResponseCodeKeys = array('cc_cvv_status', 'cvv_status', 'cvvstatus');

    /**
     * Works the same way of $avsResponseCodeExpressions
     * @var array
     */
    protected $cvvResponseCodeExpressions = array('cvv_status', 'cvvstatus', 'cvv', 'cid_status', 'cid');

    /**
     * Works the same way of $avsResponseCodeKeys
     * @var array
     */
    protected $binKeys = array('cc_bin', 'bin');

    /**
     * Works the same way of $avsResponseCodeExpressions
     * @var array
     */
    protected $binExpressions = array('cc_bin', 'bin');

    /**
     * Works the same way of $avsResponseCodeKeys
     * @var array
     */
    protected $last4Keys = array('cc_last4', 'last4', 'saved_cc_last_4');

    /**
     * Works the same way of $avsResponseCodeExpressions
     * @var array
     */
    protected $last4Expressions = array('cc_last4', 'last4', 'last_4');

    /**
     * Works the same way of $avsResponseCodeKeys
     * @var array
     */
    protected $expiryMonthKeys = array('cc_expiry_month', 'expiry_month', 'expirymonth');

    /**
     * Works the same way of $avsResponseCodeExpressions
     * @var array
     */
    protected $expiryMonthExpressions = array('cc_expiry_month', 'expiry_month', 'expirymonth');

    /**
     * Works the same way of $avsResponseCodeKeys
     * @var array
     */
    protected $expiryYearKeys = array('cc_expiry_year', 'expiry_year', 'expiryyear');

    /**
     * Works the same way of $avsResponseCodeExpressions
     * @var array
     */
    protected $expiryYearExpressions = array('cc_expiry_year', 'expiry_year', 'expiryyear');


    /**
     * Search additional information for exact keys
     * 
     * @param $keys
     * @param null $filterMethod
     * @return null
     */
    public function performExactSearch($keys, $filterMethod = null)
    {
        foreach ($keys as $key) {
            $value = (is_array($this->additionalInformation) && isset($this->additionalInformation[$key])) ?
                $this->additionalInformation[$key] : null;
            
            // If we've got value and filterMethod, filter it
            if (!empty($value) && !empty($filterMethod)) {
                $value = $this->$filterMethod($value);
            }
            
            // If even after filtering the value still not empty, ise it
            if (!empty($value)) {
                return $value;
            }
        }
        
        return null;
    }

    /**
     * Search additional information for matches on keys based on expressions
     * 
     * @param $expressions
     * @param null $filterMethod
     * @return null
     */
    public function performExpressionSearch($expressions, $filterMethod = null)
    {
        foreach ($expressions as $expression) {
            foreach ($this->additionalInformation as $key => $value) {
                $found = preg_match("/{$expression}/", $key);
                
                if ($found > 0) {
                    // If we've got value and filterMethod, filter it
                    if (!empty($value) && !empty($filterMethod)) {
                        $value = $this->$filterMethod($value);
                    }

                    // If even after filtering the value still not empty, ise it
                    if (!empty($value)) {
                        return $value;
                    }
                }
            }
        }
        
        return null;
    }

    /**
     * @return null|string
     */
    public function getAvsResponseCode()
    {
        $avsResponseCode = parent::getAvsResponseCode();
        if (!empty($avsResponseCode)) {
            return $avsResponseCode;
        }

        $avsResponseCode = $this->performExactSearch($this->avsResponseCodeKeys, 'filterAvsResponseCode');
        if (empty($avsResponseCode)) {
            $avsResponseCode = $this->performExpressionSearch($this->avsResponseCodeExpressions, 'filterAvsResponseCode');
        }

        return $avsResponseCode;
    }

    /**
     * @return null|string
     */
    public function getCvvResponseCode()
    {
        $cvvResponseCode = parent::getCvvResponseCode();
        if (!empty($cvvResponseCode)) {
            return $cvvResponseCode;
        }

        $cvvResponseCode = $this->performExactSearch($this->cvvResponseCodeKeys, 'filterCvvResponseCode');
        if (empty($cvvResponseCode)) {
            $cvvResponseCode = $this->performExpressionSearch($this->cvvResponseCodeExpressions, 'filterCvvResponseCode');
        }

        return $cvvResponseCode;
    }

    /**
     * @return int|null
     */
    public function getBin()
    {
        $bin = parent::getBin();
        if (!empty($bin)) {
            return $bin;
        }

        $bin = $this->performExactSearch($this->binKeys, 'filterBin');
        if (empty($bin)) {
            $bin = $this->performExpressionSearch($this->binExpressions, 'filterBin');
        }

        return $bin;
    }

    /**
     * @return null|string
     */
    public function getLast4()
    {
        $last4 = parent::getLast4();
        if (!empty($last4)) {
            return $last4;
        }

        $last4 = $this->performExactSearch($this->last4Keys, 'filterLast4');
        if (empty($last4)) {
            $last4 = $this->performExpressionSearch($this->last4Expressions, 'filterLast4');
        }

        return $last4;
    }

    /**
     * @return null|string
     */
    public function getExpiryMonth()
    {
        $expiryMonth = parent::getExpiryMonth();
        if (!empty($expiryMonth)) {
            return $expiryMonth;
        }
        
        $expiryMonth = $this->performExactSearch($this->expiryMonthKeys, 'filterExpiryMonth');
        if (empty($expiryMonth)) {
            $expiryMonth = $this->performExpressionSearch($this->expiryMonthExpressions, 'filterExpiryMonth');
        }

        return $expiryMonth;
    }

    /**
     * @return int|null
     */
    public function getExpiryYear()
    {
        $expiryYear = parent::getExpiryYear();
        if (!empty($expiryYear)) {
            return $expiryYear;
        }

        $expiryYear = $this->performExactSearch($this->expiryYearKeys, 'filterExpiryYear');
        if (empty($expiryYear)) {
            $expiryYear = $this->performExpressionSearch($this->expiryYearExpressions, 'filterExpiryYear');
        }

        return $expiryYear;
    }
}