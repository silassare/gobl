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

namespace Gobl\Tests\Fixtures;

use Gobl\DBAL\Interfaces\RDBMSInterface;
use Gobl\DBAL\Types\BaseType;
use Gobl\DBAL\Types\Exceptions\TypesInvalidValueException;
use Gobl\DBAL\Types\Interfaces\ValidationSubjectInterface;
use Gobl\DBAL\Types\TypeString;
use Gobl\ORM\ORMTypeHint;
use Override;

/**
 * A string whose validation reads a registry, as "this email must not be already registered" does: a
 * value the registry holds is rejected, and every validation is counted.
 *
 * It makes visible what re-validating a value that came from the database would do: the value is in the
 * registry, put there by the very row being saved.
 *
 * @internal
 *
 * @extends BaseType<mixed, null|string>
 */
final class RegistryCheckedType extends BaseType
{
	public const NAME = 'registry_checked';

	/** @var array<string, true> the values the registry holds */
	public static array $registry = [];

	/** How many values were validated, for a test to assert none was validated again. */
	public static int $validations = 0;

	public function __construct()
	{
		parent::__construct(new TypeString(0, 128));
	}

	public static function reset(): void
	{
		self::$registry    = [];
		self::$validations = 0;
	}

	#[Override]
	public static function getInstance(array $options): static
	{
		return new self();
	}

	#[Override]
	public function getName(): string
	{
		return self::NAME;
	}

	#[Override]
	public function getReadTypeHint(): ORMTypeHint
	{
		return ORMTypeHint::string();
	}

	#[Override]
	public function getWriteTypeHint(): ORMTypeHint
	{
		return ORMTypeHint::string();
	}

	#[Override]
	public function dbToPhp(mixed $value, RDBMSInterface $rdbms): ?string
	{
		return null === $value ? null : (string) $value;
	}

	#[Override]
	public function phpToDb(mixed $value, RDBMSInterface $rdbms): ?string
	{
		return null === $value ? null : (string) $value;
	}

	#[Override]
	protected function runValidation(ValidationSubjectInterface $subject): void
	{
		$value = (string) $subject->getUnsafeValue();

		++self::$validations;

		if (isset(self::$registry[$value])) {
			$subject->reject(new TypesInvalidValueException('VALUE_ALREADY_REGISTERED', ['value' => $value]));

			return;
		}

		$subject->accept($value);
	}
}
