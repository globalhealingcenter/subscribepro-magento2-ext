<?php

namespace Swarming\SubscribePro\Plugin\Quote;

use Swarming\SubscribePro\Api\Data\SubscriptionOptionInterface;
use Magento\Framework\App\State as AppState;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Quote\Model\Quote\Item as QuoteItem;

class QuoteItemUpdater
{
    /**
     * @var \Swarming\SubscribePro\Platform\Manager\Product
     */
    protected $platformProductManager;

    /**
     * @var \Swarming\SubscribePro\Model\Quote\SubscriptionOption\Updater
     */
    protected $subscriptionOptionUpdater;

    /**
     * @var \Magento\Catalog\Api\ProductRepositoryInterface
     */
    protected $productRepository;

    /**
     * @var \Swarming\SubscribePro\Helper\Product
     */
    protected $productHelper;

    /**
     * @var \Magento\Framework\Message\ManagerInterface
     */
    protected $messageManager;

    /**
     * @var \Magento\Framework\App\State
     */
    protected $appState;

    /**
     * @var \Psr\Log\LoggerInterface
     */
    protected $logger;

    /**
     * @param \Swarming\SubscribePro\Platform\Manager\Product $platformProductManager
     * @param \Swarming\SubscribePro\Model\Quote\SubscriptionOption\Updater $subscriptionOptionUpdater
     * @param \Magento\Catalog\Api\ProductRepositoryInterface $productRepository
     * @param \Swarming\SubscribePro\Helper\Product $productHelper
     * @param \Magento\Framework\Message\ManagerInterface $messageManager
     * @param \Magento\Framework\App\State $appState
     * @param \Psr\Log\LoggerInterface $logger
     */
    public function __construct(
        \Swarming\SubscribePro\Platform\Manager\Product $platformProductManager,
        \Swarming\SubscribePro\Model\Quote\SubscriptionOption\Updater $subscriptionOptionUpdater,
        \Magento\Catalog\Api\ProductRepositoryInterface $productRepository,
        \Swarming\SubscribePro\Helper\Product $productHelper,
        \Magento\Framework\Message\ManagerInterface $messageManager,
        \Magento\Framework\App\State $appState,
        \Psr\Log\LoggerInterface $logger
    )
    {
        $this->platformProductManager = $platformProductManager;
        $this->subscriptionOptionUpdater = $subscriptionOptionUpdater;
        $this->productRepository = $productRepository;
        $this->productHelper = $productHelper;
        $this->messageManager = $messageManager;
        $this->appState = $appState;
        $this->logger = $logger;
    }
    /**
     * @param \Magento\Quote\Model\Quote\Item\Updater $subject
     * @param \Closure $proceed
     * @param \Magento\Quote\Model\Quote\Item $item
     * @param array $info
     * @return bool
     */
    public function aroundUpdate(
        \Magento\Quote\Model\Quote\Item\Updater $subject,
        \Closure $proceed,
        \Magento\Quote\Model\Quote\Item $item,
        array $info
    ) {
        $return = $proceed($item, $info);
        $this->updateAdminQuoteItem($item, $info);
        return $return;
    }

    /**
     * @param \Magento\Quote\Model\Quote\Item $quoteItem
     * @param array $quoteItemParams
     */
    protected function updateAdminQuoteItem(QuoteItem $quoteItem, array $quoteItemParams)
    {
        $string = "\n\n Updating Quote Item \n\n";
        file_put_contents('/var/www/magento2/var/log/test.log', $string , FILE_APPEND | LOCK_EX);

        if (!$this->getSubscriptionOption($quoteItemParams) || !$this->getInterval($quoteItemParams)) {
            return;
        }

        $product = $quoteItem->getProduct();
        if ($quoteItem->getParentItem() && $quoteItem->getParentItem()->getProduct()) {
            $product = $quoteItem->getParentItem()->getProduct();
        }

        if (!$this->productHelper->isSubscriptionEnabled($product)) {
            return;
        }

        $platformProduct = $this->getPlatformProduct($product);
        if (!$platformProduct) {
            return;
        }

        $warnings = $this->subscriptionOptionUpdater->update(
            $quoteItem,
            $platformProduct,
            $this->getSubscriptionOption($quoteItemParams),
            $this->getInterval($quoteItemParams)
        );

        $string = "\n\n Found a subscription! \n\n";
        file_put_contents('/var/www/magento2/var/log/test.log', $string , FILE_APPEND | LOCK_EX);

        foreach ($warnings as $message) {
            $string = "\n\n $message \n\n";
            file_put_contents('/var/www/magento2/var/log/test.log', $string , FILE_APPEND | LOCK_EX);
            $this->messageManager->addWarningMessage($message);
        }
    }

    /**
     * @param \Magento\Catalog\Model\Product $product
     * @return \Swarming\SubscribePro\Api\Data\ProductInterface|null
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    protected function getPlatformProduct($product)
    {
        try {
            $platformProduct = $this->platformProductManager->getProduct($product->getData(ProductInterface::SKU));
        } catch (NoSuchEntityException $e) {
            if ($this->appState->getMode() === AppState::MODE_DEVELOPER) {
                throw $e;
            }
            $this->logger->critical($e->getLogMessage());
            $platformProduct = null;
        }
        return $platformProduct;
    }

    protected function getSubscriptionOption(array $quoteItemParams)
    {
        if (
            !isset($quoteItemParams['admin_subscription_option'])
            || !isset($quoteItemParams['admin_subscription_option']['option'])
            || $quoteItemParams['admin_subscription_option']['option'] == ""
        ) {
            return 'onetime_purchase';
        }
        return $quoteItemParams['admin_subscription_option']['option'];
    }

    protected function getInterval(array $quoteItemParams)
    {

        if (
            !isset($quoteItemParams['admin_subscription_option'])
            || !isset($quoteItemParams['admin_subscription_option']['interval'])
        ) {
            return false;
        }
        return $quoteItemParams['admin_subscription_option']['interval'];
    }
}
