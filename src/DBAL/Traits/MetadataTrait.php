<?php

/**
 * Copyright (c) Emile Silas Sare <emile.silas@gmail.com>.
 *
 * This file is part of the Gobl package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Gobl\DBAL\Traits;

use Gobl\DBAL\Types\Utils\Map;

/**
 * Trait MetadataTrait.
 */
trait MetadataTrait
{
	private ?Map $meta = null;

	/**
	 * Gets the metadata.
	 *
	 * @return Map
	 */
	public function getMeta(): Map
	{
		if (null === $this->meta) {
			$this->meta = new Map();
		}

		return $this->meta;
	}

	/**
	 * Sets the metadata.
	 *
	 * @param array|Map|string $key   the meta key or the meta data/map
	 * @param null|mixed       $value the meta value
	 *
	 * @return $this
	 */
	public function setMeta(array|Map|string $key, mixed $value = null): static
	{
		$this->assertNotLocked();

		$meta = $this->getMeta();

		if (\is_string($key)) {
			$meta->set($key, $value);
		} else {
			$meta->merge($key);
		}

		return $this;
	}
}
