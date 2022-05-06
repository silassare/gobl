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

namespace Gobl\ORM\Events;

use Gobl\DBAL\Table;
use Gobl\ORM\Exceptions\ORMRuntimeException;
use Gobl\ORM\Utils\ORMClassKind;
use OLIUP\CG\PHPClass;
use OLIUP\CG\PHPFile;
use OLIUP\CG\PHPNamespace;
use PHPUtils\Events\Event;

/**
 * Class ORMTableFilesGenerated.
 */
class ORMTableFilesGenerated extends Event
{
	/**
	 * ORMTableFilesGenerated constructor.
	 *
	 * @param \Gobl\DBAL\Table    $table
	 * @param \OLIUP\CG\PHPFile[] $files
	 */
	public function __construct(private Table $table, protected array $files)
	{
	}

	/**
	 * Return the table to which these files belongs to.
	 *
	 * @return \Gobl\DBAL\Table
	 */
	public function getTable(): Table
	{
		return $this->table;
	}

	/**
	 * Returns the generated files instances.
	 *
	 * @return \OLIUP\CG\PHPFile[]
	 */
	public function getFiles(): array
	{
		return $this->files;
	}

	/**
	 * Returns the generated file instance for a given class kind.
	 *
	 * @param \Gobl\ORM\Utils\ORMClassKind $kind
	 *
	 * @return \OLIUP\CG\PHPFile
	 */
	public function getFile(ORMClassKind $kind): PHPFile
	{
		return $this->files[$kind->value];
	}

	/**
	 * Returns the generated class instance for a given class kind.
	 *
	 * @param \Gobl\ORM\Utils\ORMClassKind $kind
	 *
	 * @return \OLIUP\CG\PHPClass
	 */
	public function getClass(ORMClassKind $kind): PHPClass
	{
		$file = $this->files[$kind->value];

		foreach ($file->getChildren() as $child) {
			if ($child instanceof PHPNamespace) {
				$ns = $child;
				foreach ($ns->getChildren() as $ns_member) {
					if ($ns_member instanceof PHPClass) {
						return $ns_member;
					}
				}
			}
		}

		throw new ORMRuntimeException(\sprintf('"%s" instance not found in "%s" for ORM entity class kind "%s".', PHPClass::class, PHPFile::class, $kind->value));
	}
}
