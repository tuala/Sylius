<?php

/*
 * This file is part of the Sylius package.
 *
 * (c) Paweł Jędrzejewski
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Sylius\Behat\Context\Setup;

use Behat\Behat\Context\Context;
use Sylius\Behat\Service\SharedStorageInterface;
use Sylius\Bundle\ApiBundle\Command\Cart\AddItemToCart;
use Sylius\Bundle\ApiBundle\Command\Cart\PickupCart;
use Sylius\Component\Core\Model\ProductInterface;
use Sylius\Component\Core\Model\ProductVariantInterface;
use Sylius\Component\Product\Model\ProductOptionInterface;
use Sylius\Component\Product\Model\ProductOptionValueInterface;
use Sylius\Component\Product\Resolver\ProductVariantResolverInterface;
use Sylius\Component\Resource\Generator\RandomnessGeneratorInterface;
use Symfony\Component\Messenger\MessageBusInterface;

final class CartContext implements Context
{
    /** @var MessageBusInterface */
    private $commandBus;

    /** @var ProductVariantResolverInterface */
    private $productVariantResolver;

    /** @var RandomnessGeneratorInterface */
    private $generator;

    /** @var SharedStorageInterface */
    private $sharedStorage;

    public function __construct(
        MessageBusInterface $commandBus,
        ProductVariantResolverInterface $productVariantResolver,
        RandomnessGeneratorInterface $generator,
        SharedStorageInterface $sharedStorage
    ) {
        $this->commandBus = $commandBus;
        $this->productVariantResolver = $productVariantResolver;
        $this->generator = $generator;
        $this->sharedStorage = $sharedStorage;
    }

    /**
     * @Given the customer has created empty cart
     */
    public function theCustomerHasTheCart(): void
    {
        $this->pickupCart();
    }

    /**
     * @Given /^I added (product "[^"]+") to the (cart)$/
     * @Given /^I have (product "[^"]+") in the (cart)$/
     * @Given /^I have (product "[^"]+") added to the (cart)$/
     * @Given /^the (?:customer|visitor) has (product "[^"]+") in the (cart)$/
     * @When /^the (?:customer|visitor) try to add (product "[^"]+") in the customer (cart)$/
     */
    public function iAddedProductToTheCart(ProductInterface $product, ?string $tokenValue): void
    {
        if ($tokenValue === null) {
            $tokenValue = $this->pickupCart();
        }

        $this->commandBus->dispatch(AddItemToCart::createFromData(
            $tokenValue,
            $product->getCode(),
            $this->productVariantResolver->getVariant($product)->getCode(),
            1
        ));

        $this->sharedStorage->set('product', $product);
    }

    /**
     * @Given /^I have ("[^"]+" variant of this product) in the (cart)$/
     */
    public function iHaveVariantOfProductInTheCart(ProductVariantInterface $productVariant, ?string $tokenValue): void
    {
        if ($tokenValue === null) {
            $tokenValue = $this->pickupCart();
        }

        $this->commandBus->dispatch(AddItemToCart::createFromData(
            $tokenValue,
            $productVariant->getProduct()->getCode(),
            $productVariant->getCode(),
            1
        ));

        $this->sharedStorage->set('product', $productVariant->getProduct());
    }

    /**
     * @Given /^I have (product "[^"]+") with (product option "[^"]+") ([^"]+) in the (cart)$/
     */
    public function iAddThisProductWithToTheCart(
        ProductInterface $product,
        ProductOptionInterface $productOption,
        string $productOptionValue,
        ?string $tokenValue
    ): void {
        if ($tokenValue === null) {
            $tokenValue = $this->pickupCart();
        }

        $this->commandBus->dispatch(AddItemToCart::createFromData(
            $tokenValue,
            $product->getCode(),
            $this
                ->getProductVariantWithProductOptionAndProductOptionValue(
                    $product,
                    $productOption,
                    $productOptionValue
                )
                ->getCode(),
            1
        ));
    }

    private function pickupCart(): string
    {
        $tokenValue = $this->generator->generateUriSafeString(10);

        $this->commandBus->dispatch(new PickupCart($tokenValue));

        $this->sharedStorage->set('cart_token', $tokenValue);

        return $tokenValue;
    }

    private function getProductVariantWithProductOptionAndProductOptionValue(
        ProductInterface $product,
        ProductOptionInterface $productOption,
        string $productOptionValue
    ): ?ProductVariantInterface {
        foreach ($product->getVariants() as $productVariant) {
            /** @var ProductOptionValueInterface $variantProductOptionValue */
            foreach ($productVariant->getOptionValues() as $variantProductOptionValue) {
                if (
                    $variantProductOptionValue->getValue() === $productOptionValue &&
                    $variantProductOptionValue->getOption() === $productOption
                ) {
                    return $productVariant;
                }
            }
        }

        return null;
    }
}
