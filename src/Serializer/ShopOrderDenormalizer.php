<?php

namespace Outofbox\OutofboxSDK\Serializer;

use AllowDynamicProperties;
use Outofbox\OutofboxSDK\Model\DictionaryValue;
use Outofbox\OutofboxSDK\Model\ShopOrder;
use Outofbox\OutofboxSDK\Model\ShopOrderItem;
use Symfony\Component\Serializer\Normalizer\DenormalizerAwareInterface;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Serializer\Normalizer\DenormalizerAwareTrait;

#[AllowDynamicProperties]
class ShopOrderDenormalizer implements DenormalizerAwareInterface, DenormalizerInterface
{
    use DenormalizerAwareTrait;

    /**
     * @inheritDoc
     */
    public function denormalize($data, $type, $format = null, array $context = []): mixed
    {
        /** @var ShopOrder $shopOrder */
        //$shopOrder = parent::denormalize($data, $type, $format, $context);
        $shopOrder = $this->denormalizer->denormalize($data, $type, $format, $context);

        if (isset($data['delivery_method'])) {
            $dictionaryValue = new DictionaryValue();
            $dictionaryValue->id = $data['delivery_method']['id'];
            $dictionaryValue->value = $data['delivery_method']['value'];
            $shopOrder->deliveryMethod = $dictionaryValue;
        }

        if (isset($data['payment_method'])) {
            $dictionaryValue = new DictionaryValue();
            $dictionaryValue->id = $data['payment_method']['id'];
            $dictionaryValue->value = $data['payment_method']['value'];
            $shopOrder->paymentMethod = $dictionaryValue;
        }

        $items = [];
        foreach ($shopOrder->items as $shopOrderItem) {
            if ($shopOrderItem instanceof ShopOrderItem) {
                $items[] = $shopOrderItem;
            } elseif (is_array($shopOrderItem)) {
                $items[] = $this->denormalizer->denormalize($shopOrderItem, ShopOrderItem::class, 'json');
            } else {
                $items[] = null;
            }
        }

        $shopOrder->items = $items;

        return $shopOrder;
    }

    /**
     * @param mixed $data
     * @param string $type
     * @param null $format
     * @param array $context
     * @inheritDoc
     */
    public function supportsDenormalization($data, $type, $format = null, array $context = []): bool
    {
        return $type === ShopOrder::class;
    }


    public function getSupportedTypes(?string $format): array
    {
        return [
            ShopOrder::class => true,
        ];
    }


}
