<?php

/**
 *
 * Copyright © Shogun, Inc. All rights reserved.
 * See LICENSE.txt for license details.
 */

namespace Shogun\FrontendCheckout\Service;

use Magento\Checkout\Model\Session;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\RedirectFactory;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\View\Result\PageFactory;
use Magento\Integration\Api\UserTokenReaderInterface;
use Magento\Integration\Model\Oauth\TokenFactory;
use Magento\Quote\Model\QuoteFactory;
use Magento\Quote\Model\QuoteRepository;

/**
 * Syncs customer carts for checkout
 */
class CheckoutSync implements CheckoutSyncInterface
{
    /**
     * @var CustomerRepositoryInterface
     */
    protected $customerRepository;
    /**
     * @var CustomerSession
     */
    protected $customerSession;
    /**
     * @var QuoteRepository
     */
    protected $quoteRepository;
    /**
     * @var QuoteFactory
     */
    protected $quoteFactory;
    /**
     * @var Session
     */
    protected $checkoutSession;
    /**
     * @var UserTokenReaderInterface
     */
    protected $userTokenReader;

    /**
     * @param CustomerRepositoryInterface $customerRepository
     * @param CustomerSession $customerSession
     * @param QuoteRepository $quoteRepository
     * @param QuoteFactory $quoteFactory
     * @param Session $checkoutSession
     * @param UserTokenReaderInterface $userTokenReader
     */
    public function __construct(
        CustomerRepositoryInterface $customerRepository,
        CustomerSession $customerSession,
        QuoteRepository $quoteRepository,
        QuoteFactory $quoteFactory,
        Session $checkoutSession,
        UserTokenReaderInterface $userTokenReader
    ) {
        $this->customerRepository = $customerRepository;
        $this->customerSession = $customerSession;
        $this->quoteRepository = $quoteRepository;
        $this->quoteFactory = $quoteFactory;
        $this->checkoutSession = $checkoutSession;
        $this->userTokenReader = $userTokenReader;
    }

    /**
     * Syncs a customer cart for checkout
     *
     * @param string $customerToken
     * @param string $cartId
     * @return void
     */
    public function syncCustomerCart(string $customerToken, string $cartId)
    {
        $customer = $this->getCustomer($customerToken);
        $this->customerSession->loginById($customer->getId());

        $quote = $this->getQuote($customer, $cartId);

        $this->checkoutSession->replaceQuote($quote);
        $this->checkoutSession->regenerateId();
    }

    /**
     * @param string $tokenParam
     * @return \Magento\Customer\Api\Data\CustomerInterface
     * @throws NoSuchEntityException
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    private function getCustomer(string $tokenParam): \Magento\Customer\Api\Data\CustomerInterface
    {
        $userToken = $this->userTokenReader->read($tokenParam);
        $customerId = $userToken->getUserContext()->getUserId();

        return $this->customerRepository->getById($customerId);
    }

    /**
     * @param \Magento\Customer\Api\Data\CustomerInterface $customer
     * @param string $cartId
     * @return \Magento\Quote\Model\Quote
     */
    private function getQuote(\Magento\Customer\Api\Data\CustomerInterface $customer, string $cartId): \Magento\Quote\Model\Quote
    {
        $quote = $this->getCustomerQuote($customer);
        $cartQuote = $this->getCartQuote($cartId);

        // If for some reason the cart and the customer quote isn't the same, e.g. if the front-end shifted
        // carts around between customers, we want to merge / transfer the two together.
        if ($cartQuote->getId() && ($quote->getId() !== $cartQuote->getId())) {
            $quote = $quote->merge($cartQuote);
        }

        // The quote could be a null object. Thus, it won't be persisted and its ID will be nil.
        // In that case, we want to persist the quote to be used in a second attempt to sync the cart.
        if (!$quote->getId()) {
            $quote->assignCustomer($customer);
            $quote->collectTotals();
            $this->quoteRepository->save($quote);
        }

        return $quote;
    }

    /**
     * @param $customer
     * @return \Magento\Quote\Model\Quote
     */
    private function getCustomerQuote($customer): \Magento\Quote\Model\Quote
    {
        try {
            return $this->quoteRepository->getForCustomer($customer->getId());
        } catch (NoSuchEntityException $e) {
            return $this->quoteFactory->create();
        }
    }

    /**
     * @param string $cartId
     * @return \Magento\Quote\Api\Data\CartInterface|\Magento\Quote\Model\Quote
     */
    private function getCartQuote(string $cartId)
    {
        try {
            return $this->quoteRepository->getActive($cartId);
        } catch (NoSuchEntityException $e) {
            return $this->quoteFactory->create();
        }
    }
}
