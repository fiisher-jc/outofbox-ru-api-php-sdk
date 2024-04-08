<?php

namespace Outofbox\OutofboxSDK\Serializer;

use AllowDynamicProperties;
use Outofbox\OutofboxSDK\Model\Image;
use Outofbox\OutofboxSDK\Model\Product;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Symfony\Component\Serializer\Normalizer\DenormalizerAwareInterface;
use Symfony\Component\Serializer\Normalizer\DenormalizerAwareTrait;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;

#[AllowDynamicProperties]
//class ProductDenormalizer extends ObjectNormalizer
class ProductDenormalizer implements DenormalizerAwareInterface, DenormalizerInterface, LoggerAwareInterface
{
    use DenormalizerAwareTrait;
    use LoggerAwareTrait;

    /**
     * @inheritDoc
     */
    public function denormalize($data, $type, $format = null, array $context = []): mixed
    {

        /** @var Product $product */
        //$product = parent::denormalize($data, $type, $format, $context);
        $this->logger?->debug('ProductDenormalizer: start dernomalize');
        try {
            $product = $this->denormalizer->denormalize($data, $type, $format, $context);
        }catch(\Throwable $exception){
            $this->logger?->debug('ProductDenormalizer: denormalize error: '.$exception->getMessage());
        }

        $this->logger?->debug('ProductDenormalizer: after dernomalize - 0');

        foreach ($data['fields_names'] as $field_title => $field_name) {
            $product->{$field_name} = $data[$field_name];
        }

        $this->logger?->debug('ProductDenormalizer: after dernomalize - 1');

        $images = $product->getImages();

        $this->logger?->debug('ProductDenormalizer: after dernomalize - 2');

        $images_objects = [];
        foreach ($images as $image_data) {
            if ($image_data instanceof Image) {
                $images_objects[] = $image_data;
            } elseif (is_array($image_data) && isset($image_data['path'])) {
                $image = new Image();
                $image->path = $image_data['path'];
                if (isset($image_data['modifications'])) {
                    $image->modifications = $image_data['modifications'];
                }

                $images_objects[] = $image;
            }
        }

        $this->logger?->debug('ProductDenormalizer: after dernomalize - 3');


        $product
            ->setCreatedAt(new \DateTime($data['created_at']))
            ->setImages($images_objects)
        ;

        $this->logger?->debug('ProductDenormalizer: after dernomalize - 4');

        return $product;
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
        return $type === Product::class;
    }

    public function getSupportedTypes(?string $format): array
    {
        return [
              Product::class => true,
            ];
    }
}
