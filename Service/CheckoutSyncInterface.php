<?php

/**
 *
 * Copyright © Shogun, Inc. All rights reserved.
 * See LICENSE.txt for license details.
 */

namespace Shogun\FrontendCheckout\Service;

/**
 * Sync carts with checkout sessions.
 */
interface CheckoutSyncInterface
{
    public function syncCart(?string $customerToken, string $cartId);
}
