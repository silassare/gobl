<?php

/**
 * Copyright (c) Emile Silas Sare.
 *
 * This file is part of the Gobl package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Gobl\DBAL\Diff\Actions;

use Gobl\DBAL\Diff\DiffAction;
use Gobl\DBAL\Diff\DiffActionType;
use Gobl\DBAL\Indexes\Index;
use Override;

/**
 * Class IndexAdded.
 */
final class IndexAdded extends DiffAction
{
	public function __construct(protected Index $index, string $reason = 'index added')
	{
		parent::__construct(DiffActionType::INDEX_ADDED, $reason);
	}

	/**
	 * @return Index
	 */
	public function getIndex(): Index
	{
		return $this->index;
	}

	#[Override]
	public function toArray(): array
	{
		return [
			'action'       => $this->type->value,
			'reason'       => $this->reason,
			'indexName'    => $this->index->getName(),
			'indexOptions' => $this->index->toArray(),
		];
	}
}
