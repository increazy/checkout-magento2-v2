<?php
namespace Increazy\CheckoutV2\Controller\Auth;

use Increazy\CheckoutV2\Controller\Controller;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\StoreManagerInterface;

class Gettoken extends Controller
{

    public function __construct(
        Context $context,
        StoreManagerInterface $store,
        ScopeConfigInterface $scopeConfig
    )
    {
        parent::__construct($context, $store, $scopeConfig);
    }

    public function validate($body)
    {
        return isset($body->hash) && isset($body->id);
    }

    public function action($body)
    {
        if ($this->getHash() !== $body->hash) {
            $this->error('hash.not-found');
        }

        $token = $this->hashEncode($body->id);

        return [ 'token' => $token ];
    }
}
