<?php

namespace Shogun\FrontendCheckout\Controller\Cart;

use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Integration\Model\Oauth\Token;
use Magento\Integration\Model\Oauth\TokenFactory;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\View\Result\Page;
use Magento\Framework\View\Result\PageFactory;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Quote\Model\QuoteFactory;
use Magento\Quote\Model\QuoteRepository;
use Magento\Checkout\Model\Session;
use Magento\Framework\Controller\Result\RedirectFactory;

class Sync implements HttpGetActionInterface
{
    /**
     * @var PageFactory
     */
    protected $pageFactory;

    /**
     * @var RequestInterface
     */
    protected $request;

    protected $tokenFactory;
    protected $customerRepository;
    /**
     * @var CustomerSession
     */
    protected $customerSession;
    protected $quoteRepository;
    /**
     * @var Session
     */
    protected $checkoutSession;
    /**
     * @var QuoteFactory
     */
    protected $quoteFactory;
    /**
     * @var RedirectFactory
     */
    protected $redirectFactory;
    protected $logger;

    public function __construct(
        PageFactory $pageFactory,
        RequestInterface $request,
        TokenFactory $tokenFactory,
        CustomerRepositoryInterface $customerRepository,
        CustomerSession $customerSession,
        QuoteRepository $quoteRepository,
        QuoteFactory $quoteFactory,
        Session $checkoutSession,
        RedirectFactory $redirectFactory
    )
    {
        $this->pageFactory = $pageFactory;
        $this->request = $request;
        $this->tokenFactory = $tokenFactory;
        $this->customerRepository = $customerRepository;
        $this->customerSession = $customerSession;
        $this->quoteRepository = $quoteRepository;
        $this->quoteFactory = $quoteFactory;
        $this->checkoutSession = $checkoutSession;
        $this->redirectFactory = $redirectFactory;
    }

    /**
     * Default customer account page
     *
     * @return void
     */
    public function execute()
    {
        $cartId = $this->request->getParam('cartId');
        $customerTokenParam = $this->request->getParam('token');

        // 1. Get Token from Token Key
        $token = $this->tokenFactory->create()->loadByToken($customerTokenParam);

        if (!$token->getId()) {
            echo "User not found", PHP_EOL;
            exit;
        }

        // 2. Get Customer from Token
        $customer = $this->customerRepository->getById($token->getCustomerId());

        // 3. Login customer
        $this->customerSession->loginById($customer->getId());

        // 4. Get Customer Quote or Cart Quote
        try {
            $quote = $this->quoteRepository->getForCustomer($customer->getId());
        } catch (NoSuchEntityException $e) {
            echo "Error: No quote!", $e->getMessage(), PHP_EOL;
            $quote = $this->quoteFactory->create();

            // TODO: TEST THIS!
            try {
                $cartQuote = $this->quoteRepository->getActive($cartId);
            } catch (NoSuchEntityException $e) {
                // TODO: REMOVE DEBUG LOG
                echo "Error: No cart quote!", $e->getMessage(), PHP_EOL;
                // $this->logger->error($e->getMessage());
                return $this->redirectFactory->create()->setPath("checkout");
            }

            // Merge Cart Quote into Customer Quote, if necessary
            if ($quote->getId() !== $cartQuote->getId()) {
                $quote->assignCustomerWithAddressChange(
                    $customer,
                    $cartQuote->getBillingAddress(),
                    $cartQuote->getShippingAddress()
                );
                $this->quoteRepository->save($quote->merge($cartQuote)->collectTotals());
            }
        }

        //5. Sync checkout to quote
        $quote->collectTotals();
        $this->checkoutSession->replaceQuote($quote);

        // 6. Redirect to checkout
        return $this->redirectFactory->create()->setPath("checkout");
    }
}
