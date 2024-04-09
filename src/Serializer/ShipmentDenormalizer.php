<?php

namespace Outofbox\OutofboxSDK\Serializer;

use AllowDynamicProperties;
use Outofbox\OutofboxSDK\Model\Shipment;
use Outofbox\OutofboxSDK\Model\ShipmentState;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Symfony\Component\PropertyInfo\Extractor\ReflectionExtractor;
use Symfony\Component\Serializer\Normalizer\DenormalizerAwareInterface;
use Symfony\Component\Serializer\Normalizer\DenormalizerAwareTrait;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;

#[AllowDynamicProperties]
//class ShipmentDenormalizer extends ObjectNormalizer
class ShipmentDenormalizer implements  DenormalizerInterface, DenormalizerAwareInterface, LoggerAwareInterface
{
    use DenormalizerAwareTrait;
    use LoggerAwareTrait;
    /**
     * @inheritDoc
     */
    public function denormalize($data, $type, $format = null, array $context = []): mixed
    {
        $odn = new ObjectNormalizer(null, null, null, new ReflectionExtractor());

        /** @var Shipment $shipment */
        //$shipment = parent::denormalize($data, $type, $format, $context);
        $this->logger?->debug('ShipmentDenormalizer: start dernomalize');
        $shipment = $odn->denormalize($data, $type, $format, $context);

        if (isset($data['current_state'])) {
            $state = new ShipmentState();
            $state->type = $data['current_state']['type'];
            $state->value = $data['current_state']['value'];
            $state->title = $data['current_state']['title'];
            $shipment->currentState = $state;
        }

        if (isset($data['state_updated_at'])) {
            $shipment->setStateUpdatedAt(new \DateTime($data['state_updated_at']));
        }

        return $shipment;
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
        return $type === Shipment::class;
    }

    public function getSupportedTypes(?string $format): array
    {
        return [
            Shipment::class => true,
        ];
    }

}
