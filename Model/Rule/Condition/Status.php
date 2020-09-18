<?php

namespace Swarming\SubscribePro\Model\Rule\Condition;

/**
 * Class Status
 * @package Swarming\SubscribePro\Model\Rule\Condition
 */
class Status extends Base
{
    /**
     * Load attribute options
     * @return $this
     */
    public function loadAttributeOptions()
    {
        $attributes = [
            'quote_item_part_of_subscription' => __('Subscription - Status'),

        ];
        $this->setAttributeOption($attributes);
        return $this;
    }

    /**
     * Get input type
     * @return string
     */
    public function getInputType()
    {
        return 'select';
    }

    /**
     * Get value element type
     * @return string
     */
    public function getValueElementType()
    {
        return 'select';
    }

    /**
     * Get value select options
     * @return array|mixed
     */
    public function getValueSelectOptions()
    {
        if (!$this->hasData('value_select_options')) {
            $this->setData(
                'value_select_options',
                [
                    ['value' => self::SUBSCRIPTION_STATUS_ANY, 'label' => __('Part of Subscription (New or Re-order)')],
                    ['value' => self::SUBSCRIPTION_STATUS_NEW, 'label' => __('Part of New Subscription Order')],
                    ['value' => self::SUBSCRIPTION_STATUS_REORDER, 'label' => __('Part of Subscription Re-order')],
                ]
            );
        }
        return $this->getData('value_select_options');
    }

    /**
     * Validate Customer First Order Rule Condition
     * @param \Magento\Framework\Model\AbstractModel $quoteItem
     * @return bool
     */
    public function validate(\Magento\Framework\Model\AbstractModel $quoteItem)
    {
        $this->logger->info('Validating status condition for subscription');
        $subscriptionParams = $this->quoteItemHelper->getSubscriptionParams($quoteItem);

        try {
            $this->logger->info('Attempting to match the status in the cart with the status needed for the coupon');
            $this->logger->info('Subscription params: ' . json_encode($subscriptionParams));
            $this->logger->info('Value to match on: ' . $this->getValueParsed());
            $this->logger->info('Operation: ' . $this->getOperatorForValidate());
            $matchResult = $this->discountRuleHelper->validateStatus(
                $subscriptionParams,
                // Get value set on rule condition
                // new & reorder, new, or reorder
                $this->getValueParsed(),
                // == or !=
                $this->getOperatorForValidate()
            );
            $this->logger->info('Status: ' . ($matchResult ? 'matched' : 'not matched'));
        } catch (\Exception $e) {
            $this->logger->info('Could not validate status condition due to error.');
            $this->logger->error($e->getMessage());
            $matchResult = false;
        }

        return $matchResult;
    }
}
