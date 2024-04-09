<?php

namespace Outofbox\OutofboxSDK\Serializer;

use AllowDynamicProperties;
use Outofbox\OutofboxSDK\Model\DictionaryValue;
use Outofbox\OutofboxSDK\Model\ShopOrder;
use Outofbox\OutofboxSDK\Model\ShopOrderItem;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Symfony\Component\PropertyInfo\Extractor\ReflectionExtractor;
use Symfony\Component\Serializer\Normalizer\DenormalizerAwareInterface;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Serializer\Normalizer\DenormalizerAwareTrait;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;

#[AllowDynamicProperties]
class ShopOrderDenormalizer implements DenormalizerInterface, DenormalizerAwareInterface, LoggerAwareInterface
{
    use DenormalizerAwareTrait;
    use LoggerAwareTrait;
    /**
     * @inheritDoc
     */
    public function denormalize($data, $type, $format = null, array $context = []): mixed
    {
        $odn = new ObjectNormalizer(null, null, null, new ReflectionExtractor());

        /** @var ShopOrder $shopOrder */
        //$shopOrder = parent::denormalize($data, $type, $format, $context);
        //$this->logger?->debug('ShopOrderDenormalizer: start dernomalize');
        $shopOrder = $odn->denormalize($data, $type, $format, $context);

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
                $items[] = $odn->denormalize($shopOrderItem, ShopOrderItem::class, 'json');
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
