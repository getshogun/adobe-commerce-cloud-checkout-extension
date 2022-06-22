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
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Integration\Api\UserTokenReaderInterface;
use Magento\Quote\Model\QuoteFactory;
use Magento\Quote\Model\QuoteIdMaskFactory;
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
     * @var QuoteIdMaskFactory
     */
    protected $quoteIdMaskFactory;

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
        QuoteIdMaskFactory $quoteIdMaskFactory,
        Session $checkoutSession,
        UserTokenReaderInterface $userTokenReader
    ) {
        $this->customerRepository = $customerRepository;
        $this->customerSession = $customerSession;
        $this->quoteRepository = $quoteRepository;
        $this->quoteFactory = $quoteFactory;
        $this->quoteIdMaskFactory = $quoteIdMaskFactory;
        $this->checkoutSession = $checkoutSession;
        $this->userTokenReader = $userTokenReader;
    }

    /**
     * Syncs a cart for checkout
     *
     * @param string|null $customerToken
     * @param string $cartId
     * @return void
     */
    public function syncCart(?string $customerToken, string $cartId)
    {
        $customer = $this->getCustomer($customerToken);

        if ($customer) {
            $this->syncCustomerCart($customer, $cartId);
        } else {
            $this->syncGuestCart($cartId);
        }
    }

    /**
     * @param \Magento\Customer\Api\Data\CustomerInterface $customer
     * @param string $cartId
     * @return void
     */
    private function syncCustomerCart(\Magento\Customer\Api\Data\CustomerInterface $customer, string $cartId)
    {
        $this->customerSession->loginById($customer->getId());
        $quote = $this->findOrCreateCustomerQuote($customer, $cartId);
        $this->checkoutQuote($quote);
    }

    /**
     * @param string $cartId
     * @return void
     */
    private function syncGuestCart(string $cartId)
    {
        $quoteIdMask = $this->quoteIdMaskFactory->create()->load($cartId, 'masked_id');
        $quoteId = $quoteIdMask->getQuoteId();

        if ($quoteId) {
            $quote = $this->getCartQuote($quoteId);
            $this->checkoutQuote($quote);
        }
    }

    /**
     * @param $quote
     * @return void
     */
    private function checkoutQuote($quote)
    {
        $this->checkoutSession->replaceQuote($quote);
//        $this->checkoutSession->regenerateId();
    }

    /**
     * @param string|null $customerToken
     * @return \Magento\Customer\Api\Data\CustomerInterface|null
     */
    private function getCustomer(?string $customerToken = null): ?\Magento\Customer\Api\Data\CustomerInterface
    {
        if (empty($customerToken)) {
            return null;
        }

        $userToken = $this->userTokenReader->read($customerToken);
        $customerId = $userToken->getUserContext()->getUserId();

        try {
            return $this->customerRepository->getById($customerId);
        } catch (NoSuchEntityException|LocalizedException $e) {
            return null;
        }
    }

    /**
     * @param \Magento\Customer\Api\Data\CustomerInterface $customer
     * @param string $cartId
     * @return \Magento\Quote\Model\Quote
     */
    private function findOrCreateCustomerQuote(\Magento\Customer\Api\Data\CustomerInterface $customer, string $cartId): \Magento\Quote\Model\Quote
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
