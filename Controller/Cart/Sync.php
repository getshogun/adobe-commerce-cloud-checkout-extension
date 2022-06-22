<?php

/**
 *
 * Copyright © Shogun, Inc. All rights reserved.
 * See LICENSE.txt for license details.
 */

namespace Shogun\FrontendCheckout\Controller\Cart;

use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\RedirectFactory;
use Shogun\FrontendCheckout\Service\CheckoutSync;

/**
 * Handles GET requests to sync and checkout a customer cart
 */
class Sync implements HttpGetActionInterface
{
    /**
     * Name of the parameter holding the customer's access token
     */
    const SHOGUN_FRONTEND_CHECKOUT_TOKEN_PARAM = 'token';
    /**
     * Name of the parameter with the cart ID
     */
    const SHOGUN_FRONTEND_CHECKOUT_CART_PARAM = 'cartId';
    /**
     * Checkout URI path
     */
    const SHOGUN_FRONTEND_CHECKOUT_CHECKOUT_PATH = 'checkout';
    /**
     * @var RequestInterface
     */
    protected $request;
    /**
     * @var RedirectFactory
     */
    protected $redirectFactory;
    /**
     * @var CheckoutSync
     */
    protected $checkoutSync;

    /**
     * @param RequestInterface $request
     * @param RedirectFactory $redirectFactory
     * @param CheckoutSync $checkoutSync
     */
    public function __construct(
        RequestInterface $request,
        RedirectFactory $redirectFactory,
        CheckoutSync $checkoutSync
    ) {
        $this->request = $request;
        $this->redirectFactory = $redirectFactory;
        $this->checkoutSync = $checkoutSync;
    }

    /**
     * @return \Magento\Framework\App\ResponseInterface|\Magento\Framework\Controller\Result\Redirect|\Magento\Framework\Controller\ResultInterface
     */
    public function execute()
    {
        if ($this->hasRequiredParams()) {
            $this->checkoutSync->syncCart(
                $this->request->getParam(self::SHOGUN_FRONTEND_CHECKOUT_TOKEN_PARAM),
                $this->request->getParam(self::SHOGUN_FRONTEND_CHECKOUT_CART_PARAM)
            );
        }

        return $this->redirectFactory->create()->setPath(
            self::SHOGUN_FRONTEND_CHECKOUT_CHECKOUT_PATH
        );
    }

    /**
     * @return bool
     */
    private function hasRequiredParams(): bool
    {
        return !empty($this->request->getParam(self::SHOGUN_FRONTEND_CHECKOUT_CART_PARAM));
    }
}
