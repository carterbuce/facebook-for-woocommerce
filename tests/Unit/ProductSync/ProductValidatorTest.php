<?php
/**
 * Copyright (c) Facebook, Inc. and its affiliates. All Rights Reserved
 *
 * This source code is licensed under the license found in the
 * LICENSE file in the root directory of this source tree.
 *
 * @package FacebookCommerce
 */

use PHPUnit\Framework\TestCase;
use WooCommerce\Facebook\ProductSync\ProductInvalidException;
use WooCommerce\Facebook\ProductSync\ProductValidator;


/**
 * Class ProductValidatorTest
 *
 * @package WooCommerce\Facebook\Tests\Unit\ProductSync
 */
class ProductValidatorTest extends TestCase {

	public function test_valid_simple_product() {
		$product = WC_Helper_Product::create_simple_product();

		$validator = new ProductValidator(
			new WC_Facebookcommerce_Integration(facebook_for_woocommerce()),
			$product
		);

		// Expect no exceptions to be thrown in this test
		$this->expectNotToPerformAssertions();

		$validator->validate();
	}

	public function test_invalid_external_product() {
		$product = WC_Helper_Product::create_external_product();

		$validator = new ProductValidator(
			new WC_Facebookcommerce_Integration(facebook_for_woocommerce()),
			$product
		);

		// Expect an exception to be thrown in this test
		$this->expectException(ProductInvalidException::class);
		$this->expectExceptionMessage('External products are not supported.');

		$validator->validate();
	}

	public function test_valid_product_variation() {
		// Create a variable product
		$variable_product = new WC_Product_Variable();
		$variable_product->set_name('Test Variable Product');
		$variable_product->save();

		// Define the color attribute
		$attribute = new WC_Product_Attribute();
		$attribute->set_id(0);
		$attribute->set_name('pa_color');
		$attribute->set_options(array('blue', 'red', 'yellow'));
		$attribute->set_visible(true);
		$attribute->set_variation(true);

		// Add the attribute to the variable product and save
		$variable_product->set_attributes(array('pa_color' => $attribute));
		$variable_product->save();

		// Create a variation with the color set to red
		$variation = new WC_Product_Variation();
		$variation->set_parent_id($variable_product->get_id());
		$variation->set_regular_price('10'); // Set price since a variation needs a price
		$variation->set_attributes(array('pa_color' => 'red'));
		$variation->save();

		// Initialize the ProductValidator with the variation
		$validator = new ProductValidator(
			new WC_Facebookcommerce_Integration(facebook_for_woocommerce()),
			$variation
		);

		// Expect no exceptions to be thrown in this test
		$this->expectNotToPerformAssertions();

		// Run the validator
		$validator->validate();
	}

	public function test_invalid_product_variation_attributes() {
		// Create a variable product
		$variable_product = new WC_Product_Variable();
		$variable_product->set_name('Test Variable Product');
		$variable_product->save();

		// Define the color attribute
		$attribute = new WC_Product_Attribute();
		$attribute->set_id(0);
		$attribute->set_name('pa_color');
		$attribute->set_options(array('blue', 'red', 'yellow'));
		$attribute->set_visible(true);
		$attribute->set_variation(true);

		// Add the attribute to the variable product and save
		$variable_product->set_attributes(array('pa_color' => $attribute));
		$variable_product->save();

		// Create a variation with the color attribute omitted
		$variation = new WC_Product_Variation();
		$variation->set_parent_id($variable_product->get_id());
		$variation->set_regular_price('10');
		$variation->set_attributes(array('pa_color' => ''));
		$variation->save();

		// Initialize the ProductValidator with the variation
		$validator = new ProductValidator(
			new WC_Facebookcommerce_Integration(facebook_for_woocommerce()),
			$variation
		);

		// Expect an exception to be thrown in this test
		$this->expectException(ProductInvalidException::class);
		$this->expectExceptionMessage(sprintf(
			'Variation for product %s must define a specific value for %s.',
			$variation->get_id(),
			'pa_color',
		));

		// Run the validator
		$validator->validate();
	}

}
