<?php

class Signifyd_Connect_Model_Cron
{
    public function update()
    {
        try {
            if (Mage::getStoreConfig('signifyd_connect/settings/retrieve_score')) {
                $cases = Mage::getModel('signifyd_connect/case')->getCollection();
                $cases->addFieldToFilter('status', 'PENDING');
                $time = time();
                
                foreach ($cases as $case) {
                    $created_at = $case->getCreatedAt();
                    $code = $case->getCode();
                    $status = $case->getStatus();
                    
                    if ($created_at && $code && $status == 'PENDING') {
                        $created_at_time = strtotime($created_at);
                        $delta = $time - $created_at_time;
                        
                        if ($delta > 300) {
                            $this->updateCase($case);
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
                $case->setStatus('ERROR');
            }
            
            return;
        }
        
        $data = json_decode($response->getRawResponse(), true);
        
        $case->setStatus($data['status']);
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
                $case->setStatus('ERROR');
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
    }
}
