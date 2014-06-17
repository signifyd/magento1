<?php

class Signifyd_Connect_Model_Cron
{
    public function update()
    {
        try {
            if (Mage::getStoreConfig('signifyd_connect/settings/retrieve_score')) {
                $cases = Mage::getModel('signifyd_connect/case')->getCollection();
                $cases->addFieldToFilter('signifyd_status', 'PENDING');
                $time = time();
                
                foreach ($cases as $case) {
                    $created_at = $case->getCreatedAt();
                    $code = $case->getCode();
                    $status = $case->getSignifydStatus();
                    
                    if ($created_at && $code && $status == 'PENDING') {
                        $created_at_time = strtotime($created_at);
                        $delta = $time - $created_at_time;
                        
                        if ($delta > 300) {
                            $case = $this->updateCase($case);
                            
                            $score = $case->getScore();
                            
                            if ($case->getSignifydStatus() == 'PENDING' || $case->getSignifydStatus() == 'ERROR' || !$score || !is_numeric($score)) {
                                continue;
                            }
                            
                            try {
                                if (Mage::getStoreConfig('signifyd_connect/settings/hold_orders')) {
                                    $threshold = (int)Mage::getStoreConfig('signifyd_connect/settings/hold_orders_threshold');
                                    
                                    if ($threshold) {
                                        if ($score < $threshold) {
                                            $order = Mage::getModel('sales/order')->loadByIncrementId($case->getOrderIncrement());
                                            
                                            if ($order && $order->getId()) {
                                                $order->hold();
                                                $order->save();
                                            }
                                        }
                                    }
                                }
                            } catch (Exception $e) {
                                Mage::log($e->__toString(), null, 'signifyd_connect.log');
                            }
                        }
                    }
                }
            }
        } catch (Exception $e) {
            Mage::log('cron issue: ' . $e->__toString(), null, 'signifyd_connect.log');
        }
    }
    
    public function getAuth()
    {
        return Mage::getStoreConfig('signifyd_connect/settings/key');
    }
    
    public function getUrl($code)
    {
        return Mage::getStoreConfig('signifyd_connect/settings/url') . '/cases/' . $code;
    }
    
    public function getScore($case)
    {
        $helper = Mage::helper('signifyd_connect');
        $order_increment = $case->getOrderIncrement();
        $code = $case->getCode();
        
        $url = $this->getUrl($code);
        
        $response = $helper->request($url, null, $this->getAuth(), null, 'application/json');
        
        $response_code = $response->getHttpCode();
        if (substr($response_code, 0, 1) != '2') {
            if (substr($response_code, 0, 1) != '5') {
                $case->setSignifydStatus('ERROR');
            }
            
            return;
        }
        
        $data = json_decode($response->getRawResponse(), true);
        
        $case->setSignifydStatus($data['status']);
        $case->setScore($data['score']);
    }
    
    public function getEntries($case)
    {
        $helper = Mage::helper('signifyd_connect');
        $order_increment = $case->getOrderIncrement();
        $code = $case->getCode();
        
        $url = $this->getUrl($code) . '/entries';
        
        $response = $helper->request($url, null, $this->getAuth(), null, 'application/json');
        
        $response_code = $response->getHttpCode();
        if (substr($response_code, 0, 1) != '2') {
            if (substr($response_code, 0, 1) != '5') {
                $case->setSignifydStatus('ERROR');
            }
            
            return;
        }
        
        $data = json_decode($response->getRawResponse(), true);
        
        $case->setEntries(json_encode($data));
    }
    
    public function updateCase($case)
    {
        $this->getScore($case);
        $this->getEntries($case);
        
        $case->setUpdatedAt(strftime('%Y-%m-%d %H:%M:%S', time()));
        $case->save();
        
        return $case;
    }
}
