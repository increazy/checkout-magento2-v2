<?php
namespace Increazy\CheckoutV2\Controller;

use Magento\Framework\App\Action\Context;
use Magento\Framework\App\Action\Action;
use \Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\StoreManagerInterface;

abstract class Controller extends Action
{
    // const HASH = 'ZDk5MmQ3N2M0ODE0OWUyMGI0ODI2NjIzZTVlNmQ5ZTE6ODAwM2Q3M2E4MmUxMGNiN2EyMzNlMDI0ZTBkZmQ0OWY=';

    /**
     * @var StoreManagerInterface
     */
    protected $store;
    /**
     * @var ScopeConfigInterface
     */
    protected $scopeConfig;
    /**
     * @var Context
     */
    protected $context;

    public function __construct(Context $context, StoreManagerInterface $store, ScopeConfigInterface $scopeConfig) {
        $this->context = $context;
        $this->store = $store;
        $this->scopeConfig = $scopeConfig;
        parent::__construct($context);
    }

    public function execute()
    {
        try {
            $body = file_get_contents("php://input");
            $body = json_decode($body);
            if (!isset($body->store)) {
                $this->error('store.not-found');
            }

            // if (!isset(getallheaders()['token'])) {
            //     $this->error('access.not-found');
            // }

            // if ($this->hashDecode(getallheaders()['token']) !== 'increazy') {
            //     $this->error('access.not-found');
            // }

            $this->store->setCurrentStore($body->store);

            header('Content-Type:application/json');

            if (!$this->validate($body)) {
                $this->error('body.not-found');
            }

            $response = $this->action($body);
            if (isset($response['customer'])) {
                if (isset($response['customer']['rp_token'])) {
                    unset($response['customer']['rp_token']);
                }
            }

            echo json_encode($response);
        } catch (\Exception $e) {
            echo json_encode([
                'message' => $e->getMessage(),
            ]);
        }
    }

    public function error($msg)
    {
        http_response_code(400);
        throw new \Exception($msg);
    }

    protected function getHash()
    {
        return $this->scopeConfig->getValue('increazy_general/general/hash', \Magento\Store\Model\ScopeInterface::SCOPE_STORE);
    }

    public function hashEncode($str)
    {
        if ($str == '') return '';
        $token = base64_decode($this->getHash());
        $parts = explode(':', $token);
        $key = substr(hash('sha256', $parts[0]), 0, 32);
        $iv = substr(hash('sha256', $parts[1]), 0, 16);

        $encrypted_data = openssl_encrypt($str, 'aes-256-cbc', $key, OPENSSL_RAW_DATA, $iv);
        return base64_encode($encrypted_data);
    }

    public function hashDecode($str)
    {
        if ($str == '') return '';
        $token = base64_decode($this->getHash());
        $parts = explode(':', $token);
        $key = substr(hash('sha256', $parts[0]), 0, 32);
        $iv = substr(hash('sha256', $parts[1]), 0, 16);

        $str = base64_decode($str);
        $data = openssl_decrypt($str, 'aes-256-cbc', $key, OPENSSL_RAW_DATA, $iv);
        return $data;
    }

    public abstract function action($body);
    public abstract function validate($body);
}
